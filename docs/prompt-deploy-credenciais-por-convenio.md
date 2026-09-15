# Prompt de deploy na VPS — change `credenciais-por-convenio`

> Para colar numa sessão do Claude Code rodando **na VPS**, dentro de `/opt/gescon`.
> Copie tudo abaixo da linha.

---

Você está na VPS de produção do Gescon, em `/opt/gescon`. Vamos fazer o deploy da change
`credenciais-por-convenio`, que muda como as credenciais de automação são guardadas.

**Existe um único tenant em produção (NeuroKids), com a automação da Unimed rodando em guias reais
todos os dias.** Quebrar isso custa atendimento de paciente. Trabalhe um passo por vez e me mostre a
saída de cada um antes de seguir para o próximo — não encadeie os passos.

## O que esta change faz

Duas migrations novas criam `convenio_credenciais` e **copiam** para lá o conteúdo de
`unimed_rda_credentials`. A tabela antiga **não é removida** e as rotas `/configuracoes/unimed*`
continuam respondendo — é por isso que o rollback é barato.

O defeito corrigido: antes havia uma credencial por tenant, e o disjuntor pausava essa credencial
única. Em 14/09 uma falha num único item derrubou a automação inteira. Agora a credencial é por
convênio e a pausa alcança só o convênio da falha.

## Passo 0 — Situação atual

Antes de qualquer coisa, me mostre:

```bash
git -C /opt/gescon log --oneline -3
git -C /opt/gescon status --short
docker ps --format '{{.Names}}\t{{.Status}}'
docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT COUNT(*) AS tabelas FROM information_schema.tables WHERE table_schema='gestao_convenios';"
docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT id, tenant_id, login, ativo, automation_paused_at FROM unimed_rda_credentials;"
docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT id, nome, connector_type, connector_driver FROM convenios;"
```

O `DB_ROOT` está em `/opt/gescon/deploy/.secrets.env`. **Não imprima a senha na saída** — carregue
com `set -a; . /opt/gescon/deploy/.secrets.env; set +a`.

Guarde o resultado das duas últimas consultas: é contra ele que vamos conferir a migração.

## Passo 1 — Backup, no roteiro de 14/09

Nada começa sem isto. Quatro coisas, com carimbo de tempo no nome:

```bash
STAMP=pre_credenciais_por_convenio_$(date +%Y%m%d_%H%M%S)
mkdir -p /opt/gescon/deploy/backups
```

1. **Dump do banco** (`gestao_convenios`), comprimido
2. **Volume de storage** — o nome real é `deploy_gescon_storage` (não `gescon_storage`; veja o pino
   `name:` no `docker-compose.prod.yml`)
3. **`api/.env`** — contém o `APP_KEY`, sem o qual **nenhuma credencial cifrada volta a ser legível**
4. **`deploy/.secrets.env`**

Depois, obrigatoriamente:

- `gzip -t` em cada arquivo comprimido
- contagem de tabelas do dump conferida contra a do banco vivo
- registrar à parte o commit atual (`git rev-parse HEAD`), para recuperação de código
- rodar o `backup-gescon.sh` manualmente, para ter a cópia off-server no Drive coincidindo com este
  momento

Me mostre `ls -la` dos arquivos e a saída dos `gzip -t` antes de seguir.

## Passo 2 — Ensaio da migração (tarefa 10.4)

**Este é o passo que mais importa, e ele não pode tocar a produção.** Restaure o dump num banco
descartável e rode as migrations lá.

> **Cuidado que custa caro se for ignorado.** O `entrypoint.sh` roda `php artisan config:cache`, e
> com a config em cache o Laravel **ignora `env()`** — passar `DB_DATABASE=...` no `docker exec` do
> `gescon-app` não teria efeito nenhum, e a migration rodaria na **produção** achando que estava no
> ensaio. Por isso o ensaio roda num **container descartável**, criado da mesma imagem, com a config
> limpa. O container de produção não é tocado.

1. Crie o banco de ensaio e restaure o dump nele:

```bash
docker exec gescon-db mariadb -u root -p"$DB_ROOT" \
  -e "DROP DATABASE IF EXISTS gestao_convenios_ensaio; CREATE DATABASE gestao_convenios_ensaio;"
# ajuste o caminho do dump para o arquivo que você gerou no passo 1
gunzip -c /opt/gescon/deploy/backups/<dump>.sql.gz \
  | docker exec -i gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios_ensaio
```

2. Rode as migrations num container efêmero, com `config:clear` antes:

```bash
docker run --rm \
  --network gescon \
  -v /opt/gescon/api/.env:/var/www/html/.env:ro \
  -e DB_HOST=gescon-db \
  -e DB_DATABASE=gestao_convenios_ensaio \
  -w /var/www/html \
  --entrypoint sh \
  gescon-app:latest -c 'php artisan config:clear && php artisan migrate --force'
```

Confira que a saída diz `gestao_convenios_ensaio` — se disser `gestao_convenios`, **pare
imediatamente**: o override não pegou. A rede se chama `gescon` — pino explícito no
`docker-compose.prod.yml`; confira com `docker network ls` se der erro de rede.

3. Confira que a credencial atravessou **legível**:

```bash
docker run --rm \
  --network gescon \
  -v /opt/gescon/api/.env:/var/www/html/.env:ro \
  -e DB_HOST=gescon-db \
  -e DB_DATABASE=gestao_convenios_ensaio \
  -w /var/www/html \
  --entrypoint sh \
  gescon-app:latest -c 'php artisan config:clear >/dev/null && php artisan tinker --execute="
\$c = App\Models\ConvenioCredencial::withoutGlobalScopes()->first();
echo \$c ? sprintf(
    \"banco=%s convenio_id=%s driver=%s login=%s senha_len=%d ativo=%s pronta=%s\n\",
    config(\"database.connections.mysql.database\"),
    \$c->convenio_id, \$c->driver, \$c->campo(\"login\"),
    strlen(\$c->campo(\"password\") ?? \"\"), \$c->ativo ? \"sim\" : \"nao\",
    \$c->pronta() ? \"sim\" : \"nao\"
) : \"NENHUMA CREDENCIAL MIGRADA\n\";
"'
```

O `banco=` na primeira posição é proposital: é a confirmação de que você está lendo o ensaio, e não
a produção.

**O que precisa bater:**

- `convenio_id` é o id do convênio Unimed que você viu no passo 0
- `login` é igual ao da `unimed_rda_credentials`
- `senha_len` é maior que zero — se for 0, a senha **não** foi decifrada e a automação vai falhar
- `pronta=sim`

4. Confira que a tabela antiga continua intacta no banco de ensaio — é dela que o rollback depende:

```bash
docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios_ensaio \
  -e "SELECT COUNT(*) AS antigas FROM unimed_rda_credentials;
      SELECT COUNT(*) AS novas FROM convenio_credenciais;"
```

As duas contagens têm que bater. Se `novas` vier menor que `antigas`, algum tenant ficou sem convênio
de `connector_driver = 'unimed_rda'` — a migration registra isso no log em vez de falhar. O log do
container efêmero morreu junto com ele, então repita o passo 2 acrescentando
`&& cat storage/logs/laravel.log | grep -i credenciais-por-convenio` ao final do comando para ler o
aviso.

**Se `senha_len` vier 0, se nenhuma credencial for migrada, ou se as contagens não baterem, PARE e me
diga.** Não siga para o deploy.

5. Só depois de tudo conferido, derrube o banco de ensaio:

```bash
docker exec gescon-db mariadb -u root -p"$DB_ROOT" -e "DROP DATABASE gestao_convenios_ensaio;"
```

## Passo 3 — Deploy

```bash
/opt/gescon/deploy/redeploy.sh
```

O script faz `git pull --ff-only origin main` → build → `up -d` → smoke test. A change já está na
`main`, no merge `b499f73` — o `git log` do passo 0 deve mostrar um commit anterior a ele, e o pull
traz os 14 commits da change.

As migrations rodam no `entrypoint.sh`, **antes** do supervisord subir php-fpm e nginx. Isso é
proposital: nada escreve em `convenio_credenciais` durante a conversão.

Me mostre a saída inteira do script. Ele confere três coisas além do HTTP 200 — identidade da SPA,
hash do bundle servido contra o da imagem, e o estado do `gescon-worker`. Se o bundle não bater, o
container no ar não é o que foi buildado.

## Passo 4 — Conferência pós-deploy

1. A credencial migrada em **produção**. Aqui o `gescon-app` serve, porque é o banco dele mesmo que
   queremos ler — sem override nenhum:

   ```bash
   docker exec gescon-app sh -c 'cd /var/www/html && php artisan tinker --execute="
   \$c = App\Models\ConvenioCredencial::withoutGlobalScopes()->first();
   echo \$c ? sprintf(
       \"banco=%s convenio_id=%s login=%s senha_len=%d pronta=%s\n\",
       config(\"database.connections.mysql.database\"),
       \$c->convenio_id, \$c->campo(\"login\"),
       strlen(\$c->campo(\"password\") ?? \"\"), \$c->pronta() ? \"sim\" : \"nao\"
   ) : \"NENHUMA CREDENCIAL\n\";
   "'
   ```

   `banco=gestao_convenios` e `senha_len` maior que zero.
2. A tabela antiga continua lá, com os dados:
   ```bash
   docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
     -e "SELECT COUNT(*) FROM unimed_rda_credentials; SELECT COUNT(*) FROM convenio_credenciais;"
   ```
3. A permissão nova chegou aos papéis que tinham a antiga:
   ```bash
   docker exec gescon-app sh -c 'cd /var/www/html && php artisan tinker --execute="
   foreach (Spatie\Permission\Models\Role::with(\"permissions\")->get() as \$r) {
       echo sprintf(\"%s: antiga=%s nova=%s\n\", \$r->name,
           \$r->permissions->contains(\"name\", \"configuracoes.unimed.manage\") ? \"sim\" : \"nao\",
           \$r->permissions->contains(\"name\", \"configuracoes.convenios.manage\") ? \"sim\" : \"nao\");
   }"'
   ```
   Todo papel com `antiga=sim` precisa ter `nova=sim`.
4. Pela interface, logado: **Configurações → Convênios e credenciais** deve abrir, mostrar a Unimed
   com "Automação ligada" e "Credencial pronta", e o campo de senha com o texto "Já configurada".
5. A rota antiga ainda responde: abra `/configuracoes/unimed` direto pela URL (ela saiu do menu de
   propósito) e confirme que carrega.

## Passo 5 — O teste que realmente importa

A automação precisa rodar de verdade com a credencial migrada. Envie **um** item para a Unimed pela
tela de Solicitações e acompanhe em `/automacoes`.

Se falhar com `CREDENTIAL_MISSING`, a credencial não foi encontrada para aquele convênio — me diga
antes de mexer em qualquer coisa.

## Rollback

Enquanto `unimed_rda_credentials` existir e as rotas antigas responderem, reverter é **voltar o
deploy**: nenhum dado precisa ser restaurado.

```bash
git -C /opt/gescon checkout <commit-anterior>
/opt/gescon/deploy/redeploy.sh
```

As tabelas novas ficam no banco sem incomodar ninguém. Só restaure o dump se algo tiver corrompido
dado — e nesse caso o `APP_KEY` do backup é indispensável para as credenciais voltarem legíveis.

## Depois que estiver tudo certo — cadastro do SC Saúde

Pela tela de Convênios, **não pelo banco**:

1. Novo convênio, nome `SC Saúde`
2. **Conector em manual — não selecione driver nenhum.** Este é o ponto crítico: ligar o
   `connector_driver` hoje acionaria uma automação que não existe, falharia em toda guia nova e
   tiraria as guias do convênio da verificação diária de guias, sem ninguém notar.
3. `carteirinha_blocos` na máscara de 17 dígitos com zero à esquerda: `0306XXXXXXXXXXXXX`
4. Confira em Convênios e credenciais que ele aparece com o aviso de autenticação pendente, sem
   formulário de credencial
5. Confira que a Unimed continua funcionando e que uma guia do SC Saúde entra no fluxo manual

## Regras para esta sessão

- Um passo por vez, com a saída na tela antes do próximo
- Nunca imprima senha, `APP_KEY` ou conteúdo de `.secrets.env`
- Não rode `migrate:fresh`, `migrate:rollback`, `db:wipe` nem qualquer `DROP` na `gestao_convenios`
- Se algo divergir do esperado, **pare e me diga** em vez de tentar corrigir por conta própria
