# Prompt de deploy na VPS — finalizar e conferir guias na Unimed

> Para colar numa sessão do Claude Code rodando **na VPS**, dentro de `/opt/gescon`.
> Copie tudo abaixo da linha.

---

Você está na VPS de produção do Gescon, em `/opt/gescon`. Vamos publicar quatro commits,
`950f1f8..9b0fca4`, que trazem duas changes: `automacao-unimed-finalizar-guia` e
`conferir-guias-finalizadas-unimed`.

**Existe um único tenant em produção (NeuroKids), com a automação da Unimed rodando em guias reais
todos os dias.** Quebrar isso custa atendimento de paciente. Um passo por vez, com a saída na tela
antes do próximo — não encadeie os passos.

## O que este deploy leva

| O quê | Detalhe |
|---|---|
| **Regras de agenda ao registrar sessão** | 50 min entre inícios, 8/dia em especialidade ABA, 1/dia nas demais. **Bloqueia.** |
| **Várias folhas de registro por guia** | O anexo deixa de ser um por remessa |
| **Finalizar a guia na Unimed pelo robô** | Operação nova, com **modo simulação ligado por padrão** |
| **Conferir guias já finalizadas na operadora** | Só consulta — não altera nada no portal |
| **Duas migrations** | `add_finalizar_guia_simulacao_to_configuracoes_globais_table` (booleana, default `true`) e `add_finalizada_na_operadora_to_guias_table` (duas datas nulas + índice) |
| **Duas operações novas no worker** | `finalizar_guia` e `conferir_guia_finalizada` — o `gescon-worker` precisa ser rebuildado |
| **Dependências novas** | Nenhuma, nem no backend nem no frontend |

As migrations rodam sozinhas: o `entrypoint.sh` executa `php artisan migrate --force` ao subir.

### O que muda para quem já usa o sistema — avise a clínica

Esta é a parte que a NeuroKids vai sentir **no primeiro dia**, e é mais intrusiva que a dos deploys
anteriores:

- **Registrar sessão pode passar a ser recusado.** Duas sessões do mesmo paciente a menos de 50
  minutos uma da outra, ou mais de uma por dia numa especialidade não-ABA, deixam de ser gravadas. A
  linha fica marcada em vermelho com o motivo, e **não há "confirmar assim mesmo"**. Se a clínica
  hoje lança sessões com horários aproximados ou repetidos, ela vai bater nisso — e o Passo 0 mede
  quanto disso já existe.
- **Guia da Unimed não finaliza mais à mão.** O botão Finalizar, para convênio `unimed_rda`, vira
  **Finalizar na Unimed**. A guia só fica finalizada no Gescon depois que a operadora aceitar.
  **Não existe escape manual** — se o portal estiver fora do ar, nenhuma guia Unimed finaliza.
- **Dois sinais novos podem aparecer.** O card "Sessões em conflito" no painel (passivo anterior às
  regras) e o selo "Finalizada na operadora" nas guias, depois da conferência.

### O que este deploy NÃO faz

**Não finaliza nenhuma guia na Unimed sozinho.** A finalização só roda por clique do operador, e
nasce em **modo simulação**: o robô preenche tudo no portal e **para antes de Gravar e Finalizar**.
Ligar a finalização de verdade é uma decisão separada, com roteiro próprio em
`docs/automacao-unimed/v2-08-homologacao-finalizar-guia.md`. **Não faça isso neste deploy.**

A conferência de guias finalizadas, ao contrário, **pode rodar de verdade hoje** — ela só lê.

---

## Passo 0 — Onde a VPS está

```bash
git -C /opt/gescon log --oneline -3
git -C /opt/gescon status --short
docker ps --format '{{.Names}}\t{{.Status}}'
```

Carregue os segredos sem imprimi-los:

```bash
set -a; . /opt/gescon/deploy/.secrets.env; set +a
```

E o retrato de antes:

```bash
docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT COUNT(*) AS tabelas FROM information_schema.tables WHERE table_schema='gestao_convenios';"

# As colunas das duas changes ainda NÃO podem existir — as duas consultas dão 0
docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT COUNT(*) AS ja_existe FROM information_schema.columns
       WHERE table_schema='gestao_convenios' AND table_name='configuracoes_globais'
         AND column_name='automacao_finalizar_guia_simulacao_ativo';"

docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT COUNT(*) AS ja_existe FROM information_schema.columns
       WHERE table_schema='gestao_convenios' AND table_name='guias'
         AND column_name='finalizada_na_operadora_em';"

# Quantas sessões existem hoje, e como estão as guias Unimed
docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT COUNT(*) AS sessoes FROM lancamentos WHERE tenant_id = 1;"

docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT g.status, COUNT(*) AS guias
        FROM guias g JOIN convenios c ON c.id = g.convenio_id
       WHERE g.tenant_id = 1 AND c.connector_driver = 'unimed_rda'
       GROUP BY g.status ORDER BY g.status;"
```

**As duas consultas de coluna têm que dar 0.** Se alguma vier 1, a change correspondente já subiu;
**pare e me diga**.

Se o `git status` mostrar alteração local não commitada, me mostre antes de qualquer pull: o
`redeploy.sh` faz `--ff-only` e vai falhar.

### Quanto passivo de agenda já existe — mede ANTES

Este número decide se o deploy pode seguir hoje. A consulta é uma aproximação em SQL da regra (o
sistema usa um avaliador em PHP, que é a fonte de verdade):

```bash
docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT COUNT(*) AS pares_colados
        FROM lancamentos a
        JOIN guias ga ON ga.id = a.guia_id
        JOIN lancamentos b ON b.id > a.id
        JOIN guias gb ON gb.id = b.guia_id
       WHERE a.tenant_id = 1 AND a.status='completed' AND b.status='completed'
         AND ga.paciente_id = gb.paciente_id
         AND a.hora_inicio IS NOT NULL AND b.hora_inicio IS NOT NULL
         AND ABS(TIMESTAMPDIFF(MINUTE,
               TIMESTAMP(a.data_sessao, a.hora_inicio),
               TIMESTAMP(b.data_sessao, b.hora_inicio))) < 50;"
```

**Se esse número for alto, pare e me diga antes de seguirmos.** Significa que a clínica lança sessões
com horários repetidos ou aproximados, e a regra nova vai atrapalhar o dia a dia dela a partir de
amanhã. Pode ser caso de avisar a clínica primeiro, ou de rever a regra — não de seguir em frente
calado. As regras **não são retroativas** (o que está gravado continua gravado), mas todo lançamento
novo passa a ser conferido.

## Passo 1 — Backup, e só ele

**Este passo é isolado de propósito. Termine-o, me mostre a conferência, e só então seguimos.**

Este deploy escreve no banco (duas migrations) e muda regra de gravação de sessão. O backup é o que
permite voltar.

```bash
set -a; . /opt/gescon/deploy/.secrets.env; set +a
STAMP=pre_unimed_finalizar_conferir_$(date +%Y%m%d_%H%M%S)
mkdir -p /opt/gescon/deploy/backups
cd /opt/gescon/deploy/backups
```

Quatro coisas, todas com o mesmo carimbo:

1. **Dump do banco**, comprimido:

```bash
docker exec gescon-db mariadb-dump -u root -p"$DB_ROOT" \
  --single-transaction --routines --triggers gestao_convenios \
  | gzip > "${STAMP}_gestao_convenios.sql.gz"
```

2. **Volume de storage** — o nome real é `deploy_gescon_storage` (veja o pino `name:` no
   `docker-compose.prod.yml`). Neste deploy ele importa mais que de costume: as folhas de registro de
   sessão vivem lá:

```bash
docker run --rm -v deploy_gescon_storage:/data:ro -v "$PWD":/backup alpine \
  tar czf "/backup/${STAMP}_storage.tar.gz" -C /data .
```

3. **`api/.env`** — contém o `APP_KEY`, sem o qual nenhuma credencial cifrada volta a ser legível:

```bash
cp /opt/gescon/api/.env "${STAMP}_api.env"
```

4. **`deploy/.secrets.env`**:

```bash
cp /opt/gescon/deploy/.secrets.env "${STAMP}_secrets.env"
```

E o ponto de retorno do código:

```bash
git -C /opt/gescon rev-parse HEAD | tee "${STAMP}_commit_antes.txt"
```

### Conferência do backup — obrigatória antes de seguir

```bash
ls -la /opt/gescon/deploy/backups/${STAMP}_*
gzip -t "${STAMP}_gestao_convenios.sql.gz" && echo "dump integro"
gzip -t "${STAMP}_storage.tar.gz" && echo "storage integro"
gunzip -c "${STAMP}_gestao_convenios.sql.gz" | grep -c "^CREATE TABLE"
```

Rode também o `backup-gescon.sh` manualmente, para a cópia off-server no Drive coincidir com este
momento.

**Me mostre o `ls -la`, os dois `gzip -t` e a contagem de tabelas.** Se qualquer um falhar, ou se a
contagem não bater com o Passo 0, **pare**.

## Passo 2 — Deploy

```bash
/opt/gescon/deploy/redeploy.sh
```

Me mostre a saída inteira. O script faz `git pull --ff-only origin main` → build das imagens →
`up -d` → smoke test.

Três coisas para olhar, além do HTTP 200:

- **As duas migrations rodaram.** Em `docker logs gescon-app | tail -60`, procure
  `add_finalizar_guia_simulacao_to_configuracoes_globais_table` e
  `add_finalizada_na_operadora_to_guias_table`.
- **O bundle servido é o da imagem nova.** Há frontend novo: se o script disser que o servido difere
  do da imagem, a tela no ar não é a que foi buildada — mesmo com HTTP 200.
- **O `gescon-worker` subiu.** Ele foi rebuildado, e é ele que ganhou as duas operações.

## Passo 3 — O worker conhece as operações novas

Antes de qualquer coisa na interface, confirme que o worker recebeu o código:

```bash
docker exec gescon-worker ls -la /app/src/operations/ | grep -E 'finalizarGuia|conferirGuiaFinalizada'
```

E que as rotas respondem com a recusa das **próprias operações** — não com o eco genérico de mock:

```bash
docker exec gescon-app sh -c 'curl -s -X POST \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $UNIMED_WORKER_TOKEN" \
  -d "{\"execution_id\":0,\"idempotency_key\":\"probe\",\"payload\":{}}" \
  http://gescon-worker:8787/operations/finalizar_guia'

docker exec gescon-app sh -c 'curl -s -X POST \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $UNIMED_WORKER_TOKEN" \
  -d "{\"execution_id\":0,\"idempotency_key\":\"probe\",\"payload\":{\"guias\":[]}}" \
  http://gescon-worker:8787/operations/conferir_guia_finalizada'
```

**A primeira tem que voltar `"error_code":"NUMERO_GUIA_AUSENTE"`; a segunda,
`"resumo":{"finalizadas":0,...}`.** Se alguma vier `"mock":true`, o worker no ar é o antigo — pare e
me diga. (Nenhuma das duas abre navegador nem toca o portal: as duas recusam antes do login.)

## Passo 4 — Conferência no banco

```bash
set -a; . /opt/gescon/deploy/.secrets.env; set +a

# 1. A simulação nasceu LIGADA
docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT tenant_id, automacao_finalizar_guia_simulacao_ativo AS simulacao
        FROM configuracoes_globais ORDER BY tenant_id;"

# 2. As colunas da marca nasceram vazias
docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT
        SUM(finalizada_na_operadora_em IS NOT NULL) AS marcadas,
        SUM(conferida_na_operadora_em IS NOT NULL) AS conferidas
      FROM guias WHERE tenant_id = 1;"

# 3. Nenhuma sessão foi perdida
docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT COUNT(*) AS sessoes FROM lancamentos WHERE tenant_id = 1;"

# 4. Nenhuma guia mudou de status
docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT g.status, COUNT(*) AS guias
        FROM guias g JOIN convenios c ON c.id = g.convenio_id
       WHERE g.tenant_id = 1 AND c.connector_driver = 'unimed_rda'
       GROUP BY g.status ORDER BY g.status;"
```

**O que precisa bater:**

- Consulta 1: `simulacao` = **1** para todos os tenants. Se vier 0, **pare** — o próximo operador que
  clicar em Finalizar na Unimed finalizaria de verdade.
- Consulta 2: as duas em **0**. Ninguém conferiu nada ainda.
- Consultas 3 e 4: idênticas ao Passo 0.

## Passo 5 — Limpar o cache de aplicação

As novidades que anunciam as telas ficam em cache por uma hora, e o manual é lido do arquivo:

```bash
docker exec gescon-app php artisan cache:clear
```

> Não rode `config:cache` nem `route:cache` à mão: o `entrypoint.sh` já cuida do primeiro, e o
> segundo quebra o app (há rotas com Closure em `web.php`).

## Passo 6 — Conferência pela interface

Logado como o admin da NeuroKids:

1. **Registrar sessão continua funcionando.** Em Sessões → Nova, escolha uma guia e preencha uma
   linha com data e hora. Deve gravar normalmente.
2. **O conflito é pego.** Na mesma grade, preencha duas linhas no mesmo dia com 30 minutos de
   diferença. A segunda fica **vermelha**, com a mensagem dizendo quantos minutos faltam, e
   "Registrar sessões" **desabilita**. Corrija para 1 hora e confirme que a marca some.
3. **Folhas de registro na guia.** No detalhe de uma guia existe a seção **Folhas de registro**.
   Anexe um PDF qualquer, confirme que aparece com data e seu nome, e remova.
4. **O botão certo, no convênio certo.** Em Sessões, no grupo de uma guia **da Unimed**, o botão é
   **Finalizar na Unimed**. Num convênio manual, continua "Finalizar".
5. **O painel de finalização avisa da simulação.** Clique em "Finalizar na Unimed" e confirme o aviso
   **amarelo**. **NÃO confirme o envio** — isso é o roteiro de homologação, no Passo 9.
6. **As novidades apareceram.** No painel, o card de Novidades traz as duas: a da finalização e a da
   conferência.
7. **O card de conflitos.** Se o Passo 0 encontrou pares colados, o painel mostra **Sessões em
   conflito** no grupo de Guias. Se deu zero, o card **não** deve aparecer.

## Passo 7 — O primeiro lote de conferência

A conferência é **só consulta** — não altera nada no portal —, então pode rodar de verdade agora.

Em **Guias**, clique em **Conferir finalizadas na Unimed**. O robô abre os *Exames finalizados*,
limpa a data inicial do filtro e procura cada guia pelo número. Anote o que a tela disser sobre o
convênio coberto e sobre guias restantes de outros convênios.

Quando terminar:

```bash
set -a; . /opt/gescon/deploy/.secrets.env; set +a

docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT
        SUM(finalizada_na_operadora_em IS NOT NULL) AS finalizadas,
        SUM(finalizada_na_operadora_em IS NULL AND conferida_na_operadora_em IS NOT NULL) AS nao_finalizadas,
        SUM(conferida_na_operadora_em IS NULL) AS nunca_conferidas
      FROM guias WHERE tenant_id = 1;"
```

**Um lote que devolva ZERO finalizadas é sinal de alerta, não de boa notícia.** A clínica finalizou
guias no portal durante meses; zero quase certamente significa que o filtro de data (`s_dt_ini`) não
foi limpo, e todas as guias antigas ficaram de fora da busca.

Nesse caso:

1. Em `/automacoes`, veja se a execução traz **`FILTRO_DATA_NAO_LIMPO`** — o worker foi escrito para
   acusar exatamente isso em vez de concluir "não finalizada" em silêncio.
2. Se **não** trouxer esse código e ainda assim vier zero, **pare e me diga**: a tela pode ser
   diferente do presumido, e o resultado seria um falso negativo em massa.

**Se precisar refazer**, existe o botão **Reconferir todas** (com confirmação), que devolve ao lote
inclusive as guias já conferidas. Sem ele, desfazer um lote errado custaria uma conferência avulsa
por guia.

Conferindo certo, as guias marcadas passam a mostrar o selo **Finalizada na operadora** em Guias e
aparecem recolhidas em Solicitações. **O selo não muda o status delas** — é marca própria, e isso é
deliberado.

## Passo 8 — A automação de sempre continua de pé

Este deploy rebuilda o worker, então confirme que o que já rodava continua rodando:

```bash
docker ps --format '{{.Names}}\t{{.Status}}' | grep gescon-worker
docker exec gescon-app php artisan tinker --execute="
echo App\Models\AutomacaoExecucao::whereDate('created_at', today())->count().' execucoes hoje'.PHP_EOL;
echo App\Models\AutomacaoExecucao::whereIn('status',['queued','running'])->count().' ativas'.PHP_EOL;
"
docker exec gescon-app tail -40 storage/logs/laravel.log
```

E — importante depois de rodar o lote — confirme que a credencial **não** foi pausada pelo disjuntor:

```bash
docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT convenio_id, ativo, automation_paused_at, automation_paused_reason
        FROM convenio_credenciais WHERE tenant_id = 1;"
```

Se `ativo = 0`, alguma execução falhou com código estrutural e o disjuntor pausou a automação do
convênio inteiro. Reative pela tela de credenciais e **me diga qual foi o motivo** antes de rodar
mais nada.

## Passo 9 — A homologação da finalização é OUTRA sessão

**Pare aqui.** A finalização de verdade tem roteiro próprio, em
`docs/automacao-unimed/v2-08-homologacao-finalizar-guia.md`, e ele começa com uma rodada em simulação
contra uma guia escolhida a dedo.

Não desligue `automacao_finalizar_guia_simulacao_ativo` neste deploy. O portal real nunca foi visto
em desenvolvimento — a Unimed não tem ambiente de teste —, e três coisas ainda são desconhecidas: o
formato que `dt_serie_N` aceita, o caminho até a tela de busca de guia, e o HTML do popup de anexos.
É a rodada de simulação que responde as três.

**Me diga quando o Passo 8 terminar e eu paro por aqui.**

## Rollback

**Se o problema for de tela ou de regra** (registrar sessão passou a recusar o que não devia, algo na
interface quebrado), volte o código e deixe as migrations onde estão. As colunas são inertes para
quem não usa as telas novas:

```bash
git -C /opt/gescon checkout $(cat /opt/gescon/deploy/backups/<STAMP>_commit_antes.txt)
/opt/gescon/deploy/redeploy.sh
```

Isso já basta para as regras de agenda pararem de bloquear: elas vivem no código, não no banco.

**Se precisar desfazer as migrations também:**

```bash
docker exec gescon-app php artisan migrate:rollback --step=2 --force
```

**Se o banco ficar inconsistente**, restaure o dump do Passo 1:

```bash
gunzip -c /opt/gescon/deploy/backups/<STAMP>_gestao_convenios.sql.gz \
  | docker exec -i gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios
```

**Se alguma folha de registro sumir**, restaure o storage:

```bash
docker run --rm -v deploy_gescon_storage:/data -v /opt/gescon/deploy/backups:/backup alpine \
  sh -c "cd /data && tar xzf /backup/<STAMP>_storage.tar.gz"
```

## Regras para esta sessão

- Um passo por vez, com a saída na tela antes do próximo
- **O Passo 1 termina sozinho.** Só começamos o deploy depois de eu ver a conferência do backup
- **Não desligue o modo simulação.** Isso é o Passo 9, e é outra sessão
- Nunca imprima senha, `APP_KEY` ou conteúdo de `.secrets.env`
- Não rode `migrate:fresh`, `db:wipe` nem `migrate:rollback` sem eu pedir
- Não rode `route:cache` (há rotas com Closure em `web.php`)
- Se algo divergir do esperado, **pare e me diga** em vez de tentar corrigir por conta própria

## Executado em

_(preencher depois da execução, como nos deploys anteriores: o que bateu, o que divergiu, e o que
ficou pendente.)_
