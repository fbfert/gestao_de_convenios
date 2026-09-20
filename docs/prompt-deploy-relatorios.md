# Prompt de deploy na VPS — change `relatorios-de-uso`

> Para colar numa sessão do Claude Code rodando **na VPS**, dentro de `/opt/gescon`.
> Copie tudo abaixo da linha.

---

Você está na VPS de produção do Gescon, em `/opt/gescon`. Vamos publicar a change
`relatorios-de-uso`: sete commits, `d49bc1a..b168828`.

**Existe um único tenant em produção (NeuroKids), com a automação da Unimed rodando em guias reais
todos os dias.** Quebrar isso custa atendimento de paciente. Um passo por vez, com a saída na tela
antes do próximo — não encadeie os passos.

## O que este deploy leva

Uma tela nova, `/relatorios`, com quatro abas (Operação, Financeiro, Automações, Uso). O menu
"Gestão de Convênios" vira **grupo**, com dois cartões: Painel (o `/dashboard` de sempre) e
Relatórios.

| O quê | Detalhe |
|---|---|
| **Duas migrations** | `sync_relatorios_permissions_to_existing_roles` e `create_saude_componente_eventos_table` |
| **Dependência nova no backend** | `openspout/openspout`, para exportar XLSX |
| **Dependência nova no frontend** | `recharts`, para os gráficos |
| **Escrita nova no agendador** | `SaudeService::sincronizarEstados()`, a cada minuto |

As duas dependências entram **pelo build da imagem** (o `Dockerfile` já faz `composer install` e
`npm ci && npm run build`) — não há `composer`/`npm` para rodar à mão na VPS. As migrations rodam
sozinhas: o `entrypoint.sh` executa `php artisan migrate --force` ao subir o container.

### O que muda em quem já usa o sistema

- **O Painel ficou a um clique de distância.** Quem clicava em "Gestão de Convênios" e caía no
  painel agora cai numa página de escolha. O login continua levando direto ao `/dashboard`, e a URL
  `/dashboard` continua funcionando. É uma mudança de navegação que a NeuroKids vai notar no
  primeiro dia — vale avisar a clínica antes.
- **Quatro permissões novas.** A migration concede: `admin` recebe as quatro,
  `funcionario` recebe operação e automações, `profissional` não recebe nenhuma. Papel customizado
  que a clínica tenha criado **não recebe nada** e precisa ser ajustado na tela de Perfis e
  Permissões.

### Um alerta que NÃO se aplica aqui

Durante o desenvolvimento apareceu um erro fatal do Carbon (`CarbonPeriod::getIterator()`) com o
opcache ligado. **É específico do build Windows do PHP 8.4.25.** A imagem de produção
(`php:8.4-fpm-bookworm`, mesmo 8.4.25, opcache ligado) foi testada e **não reproduz**. Nada a fazer
aqui — está escrito só para você não estranhar se topar com a menção no repositório.

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

E o retrato de antes, contra o qual vamos conferir depois:

```bash
docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT COUNT(*) AS tabelas FROM information_schema.tables WHERE table_schema='gestao_convenios';"

docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT r.name AS papel, COUNT(p.id) AS permissoes
        FROM roles r
        LEFT JOIN role_has_permissions rp ON rp.role_id = r.id
        LEFT JOIN permissions p ON p.id = rp.permission_id
       GROUP BY r.name ORDER BY r.name;"

docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT COUNT(*) AS ja_existe FROM information_schema.tables
       WHERE table_schema='gestao_convenios' AND table_name='saude_componente_eventos';"
```

Anote os três resultados. O último tem que ser **0** — se vier 1, a change já subiu; **pare e me
diga**.

Se o `git status` mostrar alteração local não commitada, me mostre antes de qualquer pull: o
`redeploy.sh` faz `--ff-only` e vai falhar.

## Passo 1 — Backup, e só ele

**Este passo é isolado de propósito. Termine-o, me mostre a conferência, e só então seguimos.** Não
comece o deploy na mesma leva.

Este deploy escreve no banco (duas migrations, uma delas mexendo em permissões já concedidas), então
o backup não é formalidade — é o que permite voltar.

```bash
set -a; . /opt/gescon/deploy/.secrets.env; set +a
STAMP=pre_relatorios_$(date +%Y%m%d_%H%M%S)
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

2. **Volume de storage** — o nome real é `deploy_gescon_storage`, e não `gescon_storage` (veja o
   pino `name:` no `docker-compose.prod.yml`):

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

# Contagem de tabelas do dump contra a do banco vivo — tem que bater com o Passo 0
gunzip -c "${STAMP}_gestao_convenios.sql.gz" | grep -c "^CREATE TABLE"
```

Rode também o `backup-gescon.sh` manualmente, para a cópia off-server no Drive coincidir com este
momento.

**Me mostre o `ls -la`, os dois `gzip -t` e a contagem de tabelas.** Se qualquer um falhar, ou se a
contagem não bater com o Passo 0, **pare** — não seguimos com backup duvidoso.

## Passo 2 — Deploy

```bash
/opt/gescon/deploy/redeploy.sh
```

Me mostre a saída inteira. O script faz `git pull --ff-only origin main` → build das imagens →
`up -d` → smoke test.

Três coisas para olhar na saída, além do HTTP 200:

- **As migrations rodaram.** Procure as duas linhas de `[entrypoint] migrations...` nos logs do
  container (`docker logs gescon-app | tail -40`). Têm que aparecer
  `sync_relatorios_permissions_to_existing_roles` e `create_saude_componente_eventos_table`.
- **O bundle servido é o da imagem nova.** É um deploy com frontend novo: se o script disser que o
  servido difere do da imagem, o container no ar não é o que acabou de ser buildado, e a tela nova
  não está valendo — mesmo com HTTP 200.
- **O `gescon-worker` subiu.** Ele também é rebuildado.

## Passo 3 — Limpar o cache de aplicação

A novidade que anuncia a tela fica em cache por uma hora, e o manual é lido do arquivo. Sem isto, a
novidade só aparece para a clínica daqui a até 60 minutos:

```bash
docker exec gescon-app php artisan cache:clear
```

Isto também limpa o cache de 5 minutos dos relatórios, que acabou de nascer vazio — inofensivo.

> Não rode `config:cache` nem `route:cache` à mão: o `entrypoint.sh` já cuida do primeiro, e o
> segundo quebra o app (há rotas com Closure em `web.php`).

## Passo 4 — Conferência no banco

```bash
set -a; . /opt/gescon/deploy/.secrets.env; set +a

# 1. A tabela de histórico de saúde nasceu
docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT COUNT(*) AS eventos FROM saude_componente_eventos;"

# 2. As quatro permissões existem
docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT name FROM permissions WHERE name LIKE 'relatorios.%' ORDER BY name;"

# 3. E chegaram nos papéis certos
docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT r.name AS papel, p.name AS permissao
        FROM roles r
        JOIN role_has_permissions rp ON rp.role_id = r.id
        JOIN permissions p ON p.id = rp.permission_id
       WHERE p.name LIKE 'relatorios.%'
       ORDER BY r.name, p.name;"
```

**O que precisa bater:**

- Consulta 2 devolve exatamente quatro linhas: `relatorios.automacoes`, `relatorios.financeiro`,
  `relatorios.operacao`, `relatorios.uso`.
- Consulta 3: `admin` com as quatro; `funcionario` com `relatorios.operacao` e
  `relatorios.automacoes`; `profissional` não aparece.
- Consulta 1 pode devolver **0** logo após o deploy — a tabela nasce vazia. Ela se preenche sozinha;
  confira de novo no passo 6.

Compare também a contagem de permissões por papel com a do Passo 0: **nenhum papel pode ter
perdido** permissão. Se algum perdeu, pare e me diga.

## Passo 5 — Conferência pela interface

Logado como o admin da NeuroKids:

1. **O menu.** "Gestão de Convênios" abre uma página com dois cartões, **Painel** e **Relatórios**.
   Clicar em Painel mostra o painel como ele sempre foi.
2. **`/relatorios` abre nas quatro abas.** Em cada uma, ao menos um indicador com número.
   - É esperado que muita coisa apareça como **—** e que gráficos digam **"Sem dados no período"**:
     a clínica tem menos de dois meses de histórico, e travessão significa "sem base para calcular",
     não erro.
   - **"Horas fora do ar", na aba Automações, tem que aparecer como —** neste primeiro dia. O
     registro de queda dos componentes começa agora; zero ali seria mentira.
3. **O período e os filtros vão para o endereço.** Troque para "Mês anterior" e confirme que a URL
   ganha `preset=mes_anterior` com `de=` e `ate=`. Copie o endereço, abra numa aba nova e confirme
   que reproduz o mesmo recorte.
4. **Exportar.** Numa tabela qualquer, clique em **Exportar CSV** e abra o arquivo no Excel: acentos
   corretos, uma coluna por campo, vírgula decimal. Depois **Exportar XLSX** e confirme que abre.
5. **A exportação ficou na auditoria.** Em Logs de Auditoria, procure a ação **"Relatório
   exportado"**, com o seu nome, a aba, a tabela e os filtros.
6. **A permissão por aba funciona.** Entre com um usuário de papel `funcionario`: ele vê Operação e
   Automações, e **não vê** Financeiro nem Uso. Com `profissional`, a entrada Relatórios não aparece
   no menu, e `/relatorios` digitado na barra devolve ao painel.
7. **A novidade apareceu.** No painel, o card de Novidades traz "Relatórios por período, com
   comparação e exportação". E no Manual existe a seção **4.1 Relatórios**.

## Passo 6 — O agendador, uns minutos depois

O histórico de saúde depende do agendador: a varredura que enxerga a **queda** de um componente roda
a cada minuto, junto do carimbo do `scheduler`. O heartbeat sozinho não vê queda nenhuma — ele só
acontece quando o componente está vivo.

Espere de 3 a 5 minutos depois do deploy e rode:

```bash
docker exec gescon-app php artisan schedule:list | head -20

set -a; . /opt/gescon/deploy/.secrets.env; set +a
docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT c.nome, e.estado, e.ocorrido_em
        FROM saude_componente_eventos e
        JOIN saude_componentes c ON c.id = e.saude_componente_id
       ORDER BY e.id DESC LIMIT 10;"
```

**Tem que haver ao menos uma linha por componente monitorado**, com o estado de partida. Se a tabela
continuar vazia depois de 5 minutos, o `schedule:work` não está rodando — confira
`docker exec gescon-app supervisorctl status` e me diga o que aparecer.

Confira também que o agendador não passou a estourar erro, já que a varredura nova roda dentro da
mesma tarefa do carimbo:

```bash
docker exec gescon-app tail -40 storage/logs/laravel.log
```

## Passo 7 — A automação continua de pé

Este deploy não mexe na automação, mas ela é o que não pode parar. Confirme:

```bash
docker ps --format '{{.Names}}\t{{.Status}}' | grep gescon-worker
docker exec gescon-app php artisan tinker --execute="
echo App\Models\AutomacaoExecucao::whereDate('created_at', today())->count().' execucoes hoje'.PHP_EOL;
"
```

E, no painel, o card de saúde dos componentes deve continuar verde.

## Rollback

**Se o problema for só de tela** (algo na interface quebrado, automação e dados intactos), volte o
código e deixe as migrations onde estão. A tabela de eventos e as quatro permissões são inertes para
quem não abre a tela nova:

```bash
git -C /opt/gescon checkout $(cat /opt/gescon/deploy/backups/<STAMP>_commit_antes.txt)
/opt/gescon/deploy/redeploy.sh
```

**Se precisar desfazer as migrations também** — e só nesse caso, porque o `down()` da migration de
permissões **apaga** as quatro permissões de todos os papéis:

```bash
docker exec gescon-app php artisan migrate:rollback --step=2 --force
```

**Se o banco ficar inconsistente**, restaure o dump do Passo 1:

```bash
gunzip -c /opt/gescon/deploy/backups/<STAMP>_gestao_convenios.sql.gz \
  | docker exec -i gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios
```

## Regras para esta sessão

- Um passo por vez, com a saída na tela antes do próximo
- **O Passo 1 termina sozinho.** Só começamos o deploy depois de eu ver a conferência do backup
- Nunca imprima senha, `APP_KEY` ou conteúdo de `.secrets.env`
- Não rode `migrate:fresh`, `db:wipe` nem `migrate:rollback` sem eu pedir — as migrations deste
  deploy rodam sozinhas pelo `entrypoint.sh`
- Não rode `route:cache` (há rotas com Closure em `web.php`)
- Se algo divergir do esperado, **pare e me diga** em vez de tentar corrigir por conta própria
