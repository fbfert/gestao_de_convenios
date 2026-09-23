# Prompt de deploy na VPS — change `ajustes-dashboard-lancamento-simulacao`

> Para colar numa sessão do Claude Code rodando **na VPS**, dentro de `/opt/gescon`.
> Copie tudo abaixo da linha.

---

Você está na VPS de produção do Gescon, em `/opt/gescon`. Vamos publicar a change
`ajustes-dashboard-lancamento-simulacao`: seis commits, `e4984d0..f46d85a`.

**Existe um único tenant em produção (NeuroKids), com a automação da Unimed rodando em guias reais
todos os dias.** Quebrar isso custa atendimento de paciente. **Faça este deploy fora do horário de
uso da clínica**: é bundle novo, e quem estiver com a tela aberta só recebe a versão nova no próximo
carregamento.

Um passo por vez, com a saída na tela antes do próximo — não encadeie os passos.

## O que este deploy leva

| O quê | Detalhe |
|---|---|
| **Card "Sessões em conflito"** | Passa a abrir só as guias em conflito. "Negadas", "Verificar Restrição" e "Senha vencendo" também voltam a chegar filtradas |
| **Dashboard** | Botão "Ver relatórios" ao lado de "Ver auditoria"; blocos "Antecipações elegíveis" e "Antecipações realizadas" no Resumo por área |
| **Lançamentos → Novo** | Orientação para conferir o número lido quando a guia não é encontrada; aviso do executante lido maior, com o nome em destaque |
| **Modo simulação da finalização Unimed** | Liga/desliga em **Automações → Configurações**, com confirmação ao desligar |
| **Migration** | **Uma**: `2026_09_23_180000_desliga_finalizar_guia_simulacao` — **desliga a simulação** em todas as clínicas |
| **Worker** | Sem mudança de código. O `redeploy.sh` rebuilda mesmo assim |
| **Dependências novas** | Nenhuma, nem no backend nem no frontend |

A migration roda sozinha: o `entrypoint.sh` executa `php artisan migrate --force` ao subir.

### ⚠️ O efeito que não se desfaz

**Depois deste deploy, "Finalizar na Unimed" grava e finaliza a guia na operadora de verdade.** Não
há como desfazer no portal. A migration desliga a simulação por decisão do responsável em
23/09/2026.

O roteiro `docs/automacao-unimed/v2-08-homologacao-finalizar-guia.md` pede que a simulação só seja
desligada **depois** de duas rodadas simuladas conferidas no portal (uma folha; depois duas folhas).
O Passo 0 confere se isso aconteceu. **Se não aconteceu, o deploy segue, mas a simulação é
religada pela tela no Passo 4** — a parte visual e os filtros não dependem dela.

### O que muda para quem já usa o sistema

- **Nenhum fluxo novo nem permissão nova.** Os blocos de antecipação usam `dashboard.antecipacoes`,
  que já existe no catálogo; o botão de relatórios aparece para quem já vê algum relatório.
- **O aviso amarelo "Modo simulação ligado"** some do painel de finalização (ou continua, se a
  simulação for religada no Passo 4).

## Passo 0 — Onde a VPS está

```bash
git -C /opt/gescon log --oneline -3
git -C /opt/gescon status --short
docker ps --format '{{.Names}}\t{{.Status}}'
```

O primeiro commit tem que ser `e4984d0` (ou posterior). Se o `git status` mostrar alteração local não
commitada, me mostre antes de qualquer pull: o `redeploy.sh` faz `--ff-only` e vai falhar.

Carregue os segredos sem imprimi-los:

```bash
set -a; . /opt/gescon/deploy/.secrets.env; set +a
```

A migration ainda **não** pode ter rodado, e a simulação tem que estar ligada:

```bash
docker exec gescon-app php artisan migrate:status | grep desliga_finalizar_guia_simulacao

docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT tenant_id, automacao_finalizar_guia_simulacao_ativo AS simulacao FROM configuracoes_globais;"
```

O `grep` tem que sair **vazio** (a migration é nova) e `simulacao` tem que ser `1`. Se a migration já
aparecer, a change subiu; **pare e me diga**.

### A homologação em simulação foi feita?

```bash
docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT e.id, e.guia_id, e.status, e.erro_codigo,
             JSON_EXTRACT(e.payload, '\$.simular') AS simular,
             (SELECT COUNT(*) FROM automacao_eventos v
               WHERE v.automacao_execucao_id = e.id AND v.tipo = 'finalizacao_simulada') AS simulada_ok,
             e.created_at
        FROM automacao_execucoes e
       WHERE e.tenant_id = 1 AND e.operacao = 'finalizar_guia'
       ORDER BY e.id DESC LIMIT 20;"
```

**Me mostre a saída e pare.** Preciso que o responsável responda antes de seguir:

1. Há ao menos uma execução com `simulada_ok = 1`?
2. Essa rodada foi **conferida no portal** — guia certa, regime e tipo, datas de série com hora,
   folha nos anexos, guia ainda em aberto?
3. Houve rodada com **duas folhas** anexadas, também conferida?

- **Três "sim"** → seguimos, e a simulação fica desligada.
- **Qualquer "não"** → seguimos com o deploy, e no **Passo 4 a simulação é religada pela tela**. O
  responsável desliga depois, quando a homologação terminar.

## Passo 1 — Backup

Este deploy escreve no banco (a migration). O backup é o que permite voltar.

```bash
set -a; . /opt/gescon/deploy/.secrets.env; set +a
STAMP=pre_ajustes_dashboard_simulacao_$(date +%Y%m%d_%H%M%S)
mkdir -p /opt/gescon/deploy/backups
cd /opt/gescon/deploy/backups

docker exec gescon-db mariadb-dump -u root -p"$DB_ROOT" \
  --single-transaction --routines --triggers gestao_convenios \
  | gzip > "${STAMP}_gestao_convenios.sql.gz"

git -C /opt/gescon rev-parse HEAD | tee "${STAMP}_commit_antes.txt"
```

Conferência:

```bash
ls -la /opt/gescon/deploy/backups/${STAMP}_*
gzip -t "${STAMP}_gestao_convenios.sql.gz" && echo "dump integro"
```

**Me mostre as duas saídas antes de seguirmos.**

## Passo 2 — Deploy

```bash
/opt/gescon/deploy/redeploy.sh
```

Me mostre a saída inteira. Além do HTTP 200, olhe:

- **`git pull` trouxe `e4984d0..f46d85a`.**
- **O bundle servido é o da imagem nova.** Se o script disser que o servido difere do da imagem, as
  mudanças de tela **não estão valendo**, mesmo com HTTP 200.

## Passo 3 — A migration rodou e a API expõe o campo

```bash
docker exec gescon-app php artisan migrate:status | grep desliga_finalizar_guia_simulacao

docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT tenant_id, automacao_finalizar_guia_simulacao_ativo AS simulacao FROM configuracoes_globais;"
```

A migration tem que aparecer como `Ran` e `simulacao` tem que ser **`0`**.

E o log não pode ter erro novo desde a subida:

```bash
docker exec gescon-app tail -40 storage/logs/laravel.log
```

## Passo 4 — Só se a homologação NÃO estava completa no Passo 0

Religar pela interface, e não por SQL — é o caminho que fica na trilha de auditoria:

1. Logado como admin da NeuroKids, abra **Automações → Configurações**.
2. Em **"Modo simulação da finalização Unimed"**, marque **Ativa**.
3. Clique em **Salvar configurações**.

Confira:

```bash
docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT automacao_finalizar_guia_simulacao_ativo AS simulacao FROM configuracoes_globais WHERE tenant_id = 1;"
```

Tem que voltar `1`. Se a homologação estava completa, **pule este passo**.

## Passo 5 — Conferência pela interface

Logado como admin da NeuroKids:

1. **Dashboard → Acesso rápido**: "Ver relatórios" aparece ao lado de "Ver auditoria" e abre
   Relatórios.
2. **Dashboard → Resumo por área**: aparecem "Antecipações elegíveis" e "Antecipações realizadas".
   O número de **elegíveis tem que ser igual** à quantidade de entradas da fila na tela de
   Antecipações. O de realizadas mostra "X neste mês · Y dispensadas" e abre o histórico filtrado
   por gerada.
3. **Card de Guias**:
   - Se "Sessões em conflito" aparecer, clique: a lista mostra **só** essas guias, com o selo
     "Sessões em conflito ×". Clicar no selo volta à lista inteira.
   - Clique em "Negadas": selo "Somente pendentes". Em "Senha vencendo": selo "Senha vencendo".
4. **Lançamentos → Novo**:
   - Ao ler uma folha, o aviso "A folha diz:" aparece maior, com o nome destacado.
   - Se o número lido não achar guia, o modal diz também "Confira se o número foi lido corretamente
     no arquivo de origem e ajuste-o." **Não lance nada só para testar** — confira com uma leitura
     que já seria feita.
5. **Automações → Configurações**: a seção "Modo simulação da finalização Unimed" existe e está no
   estado esperado (desmarcada, ou marcada se o Passo 4 rodou). Desmarcar pede confirmação —
   **clique em Cancelar** se testar.

## Passo 6 — A primeira finalização de verdade

**Só se a simulação ficou desligada.** Não é parte do deploy técnico, mas é o primeiro uso do
efeito irreversível. Siga a **Rodada 3** do roteiro de homologação:

1. Escolha a guia menos arriscada: sessões iguais às autorizadas, uma folha, horários bem espaçados
   — de preferência a mesma guia da rodada simulada.
2. **Finalizar na Unimed**. O aviso amarelo de simulação **não** pode aparecer.
3. Confira **no portal** que a guia ficou finalizada.
4. Confira **no Gescon** que a guia está finalizada, com data de finalização, e que o histórico de
   status registra origem **automação**.

**Se algo der errado**, religue a simulação em Automações → Configurações (como no Passo 4). Isso
desarma o passo irreversível sem precisar de deploy.

Ao final, registre em `docs/automacao-unimed/v2-08-homologacao-finalizar-guia.md`, seção "Depois da
homologação": o formato real de `dt_serie_N`, o caminho da tela de busca e o HTML do popup de
anexos.

## Passo 7 — A automação continua de pé

```bash
docker ps --format '{{.Names}}\t{{.Status}}' | grep gescon-worker

docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT convenio_id, ativo, automation_paused_at FROM convenio_credenciais WHERE tenant_id = 1;"
```

No painel, o card de saúde dos componentes deve continuar verde.

## Rollback

**Para voltar a simulação**, não é preciso rollback: religue em Automações → Configurações.

**Para voltar o código:**

```bash
git -C /opt/gescon checkout $(cat /opt/gescon/deploy/backups/<STAMP>_commit_antes.txt)
/opt/gescon/deploy/redeploy.sh
```

**Antes de voltar o código, religue a simulação pela tela.** O código antigo não tem esse controle,
e a migration não é desfeita pelo checkout — a simulação ficaria desligada sem ter onde religar.

## Regras para esta sessão

- Um passo por vez, com a saída na tela antes do próximo
- **Pare no fim do Passo 0** e espere a resposta sobre a homologação
- **Fora do horário de uso da clínica**: é bundle novo
- Nunca imprima senha, `APP_KEY` ou conteúdo de `.secrets.env`
- Não rode `migrate:rollback`; para religar a simulação, use a tela
- Não rode `route:cache` (há rotas com Closure em `web.php`)
- Não finalize guia na Unimed para testar o deploy — o Passo 6 é uma decisão do responsável, com
  guia escolhida
- Se algo divergir do esperado, **pare e me diga** em vez de tentar corrigir por conta própria

## Executado em 23/09/2026

Rodado numa sessão do Claude Code na própria VPS.

- **Desvio no Passo 0, só percebido no Passo 2.** O prompt esperava a produção em `e4984d0`; ela
  estava em `04f6425`. O `git pull` trouxe `04f6425..32f22c9`, e com ele a change
  `isolamento-entre-clinicas` (`8f43d01`, `3dc8ae7`, `7c1a217`, `e4984d0`, de 22/09), que estava na
  `main` sem ter sido publicada e não era citada no prompt. A falha foi do prompt: tomou como
  publicado o último commit da `main` sem conferir o que a produção tinha. A conferência "`e4984d0`
  ou posterior" do Passo 0 deveria ter parado, e também não parou.
  - A sessão da VPS parou depois do `redeploy.sh` e perguntou. O responsável decidiu seguir.
  - A change extra é pequena em código de produção: a validação de `profissional_id` (lançamentos,
    importação de transcrição), dos filtros da conciliação e do `convenio_id` do cadastro rápido de
    paciente passa a recusar id de outra clínica. Estava com todas as tasks fechadas e com a suíte
    verde.
  - Suspeitou-se que ela recusaria ids da clínica acessada por um super admin em "Acessar". Não
    procede: com o token de acesso, `$user->tenant_id` devolve a clínica-alvo. Ficou coberto por
    `TenantsApiTest::test_super_admin_em_acesso_valida_ids_pela_clinica_acessada`.
- **Passos 1 a 3 bateram.** `redeploy.sh` ok (HTTP 200, bundle servido = imagem, `gescon-worker`
  saudável); migration `desliga_finalizar_guia_simulacao` como `Ran`; `simulacao = 0` nos dois
  tenants; nenhum erro novo no log depois da subida (18:46 UTC).
- **Passo 0, homologação**: a rodada com duas folhas ainda não tinha sido feita. Pelo roteiro, a
  simulação é religada pela tela no Passo 4.
- **Pendente no momento deste registro**: Passo 4 (religar a simulação em Automações →
  Configurações e confirmar `simulacao = 1`; os dois tenants foram desligados pela migration), e os
  Passos 5 e 7. O Passo 6 fica para depois da homologação.

**Para os próximos prompts de deploy**: o intervalo de commits sai de
`git -C /opt/gescon rev-parse HEAD` na VPS, e não do último commit conhecido localmente. O Passo 0
deve listar `git log --oneline HEAD..origin/main` depois de um `git fetch` e parar se aparecer
commit que o prompt não descreve.
