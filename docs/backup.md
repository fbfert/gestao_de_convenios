# Backup de produção — gescon

> Criado em 12/08/2026. Descreve o backup automático da instância em produção
> (`gescon.gestaonossa.com.br`, VPS `72.60.166.21`).

## Por que existe um backup próprio

A VPS já tinha um backup diário do **Virtualmin** (`/usr/local/sbin/backup-vps.sh`, 02:00), mas ele só
enxerga `/home/<domínio>`. O que existe em `/home/gescon.gestaonossa.com.br` são apenas os arquivos do
domínio — a aplicação de verdade mora em **`/opt/gescon`**, o banco vive num **volume Docker** e o
storage do Laravel **também**. Nada disso entrava no backup do Virtualmin.

Ou seja: até 12/08/2026, o gescon **não tinha backup nenhum**.

## O que roda

`/usr/local/sbin/backup-gescon.sh` — cron diário às **01:15**.

| | |
| --- | --- |
| Banco | `mariadb-dump --single-transaction --quick` do container `gescon-db` (base `gestao_convenios`) |
| Storage | volume `deploy_gescon_storage` (o `storage/` do Laravel: `app/`, `framework/`, `logs/`) |
| Segredos | `api/.env` (contém o **APP_KEY**) e `deploy/.secrets.env` (`DB_PASS`, `DB_ROOT`, `WORKER_TOKEN`) |
| Destino | `/backup/gescon/AAAA-MM-DD/` → Google Drive, subpasta `gescon/` (via `rclone`) |
| Retenção | 3 dias local · 30 dias no Drive |
| Log | `/var/log/backup-gescon.log` |
| Aviso de falha | escreve no stdout → o cron manda e-mail para o `MAILTO` do crontab |

### Detalhe que não é óbvio: o storage é um volume nomeado

No `deploy/docker-compose.prod.yml`, o storage é `gescon_storage` (volume nomeado), **não** um
bind-mount — não dá para empacotar direto do host. O script usa um container auxiliar só para ler o
volume:

```bash
docker run --rm -v deploy_gescon_storage:/data:ro mariadb:11 tar czf - -C /data .
```

O prefixo `deploy_` vem do nome do projeto do compose, que é o diretório do arquivo
(`/opt/gescon/deploy`). **Se o deploy mudar de pasta, o nome do volume muda** e o script falha com uma
mensagem explícita — confira com `docker volume ls`.

A imagem auxiliar é `mariadb:11` de propósito: já está em uso pelo `gescon-db`, então um
`docker image prune` nunca a remove e o backup não depende de baixar nada.

## Guardas

O script se recusa a salvar backup ruim:

- container `gescon-db` precisa estar `running`;
- `gzip -t` no dump **e** no storage;
- dump precisa ter **≥ 48 tabelas** (`MIN_TABLES`) — é um piso contra dump vazio, não um rastreador de
  schema. Migrations que criam tabelas não incomodam. Se um dia uma migration legitimamente **remover**
  tabelas e o backup começar a falhar, baixe o número — mas olhe antes;
- não envia nada ao Drive se o backup local falhou;
- não purga o remoto se o upload do dia não completou.

## Restauração

```bash
cd /opt/gescon
set -a; . deploy/.secrets.env; set +a

# banco
gunzip -c /backup/gescon/DATA/banco.sql.gz \
  | docker exec -i gescon-db mariadb -ugestao_convenios -p"$DB_PASS" gestao_convenios

# storage (volume nomeado — restaura por dentro de um container auxiliar)
docker run --rm -v deploy_gescon_storage:/data -v /backup/gescon/DATA:/bkp:ro \
  mariadb:11 sh -c 'tar xzf /bkp/storage.tar.gz -C /data'

docker restart gescon-app
```

> **O `APP_KEY` é parte do backup por necessidade.** Sem ele, tudo que o Laravel cifrou (sessões,
> campos `encrypted`) fica ilegível — um dump sozinho não é um restore completo. Por isso o
> `env-api.bak` vai junto, e por isso ele (e o `secrets.env.bak`) sobem para o Drive. Se isso não for
> aceitável, remova o passo 3 do script — mas guarde essas chaves em outro lugar seguro.

## Verificado em 12/08/2026

- Execução simulando o ambiente do cron (`env -i PATH=/usr/bin:/bin`): saída 0, stdout vazio.
- Dump com 48 tabelas (16K), storage 192K, tudo confirmado no Drive item a item.
- **Restauração testada**: o dump foi restaurado num MariaDB limpo e descartável e subiu íntegro
  (47 tabelas na primeira tentativa, feita no meio de um deploy — ver nota abaixo).

> ⚠️ **Nota sobre a primeira execução:** ela pegou o banco no meio de um deploy com migrations
> (o contador de tabelas subiu 46 → 47 → 48 em poucos minutos, batch 7 de migrations datadas de
> 12/08). O dump é internamente consistente (`--single-transaction`), então era um snapshot válido —
> só de um instante intermediário. O backup foi refeito depois que o deploy estabilizou. Vale lembrar
> disso ao restaurar um backup cuja data coincida com um dia de deploy.

## Backups da VPS — visão geral

| Hora | Script | Cobre |
| --- | --- | --- |
| 01:15 | `backup-gescon.sh` | gescon: banco + storage + segredos |
| 01:30 | `backup-gestaonossa.sh` | gestaonossa: banco + uploads + `.env` |
| 02:00 | `backup-vps.sh` | Virtualmin: domínios em `/home`, e-mail, config do painel |

Os três mandam para a mesma pasta do Drive, em subpastas separadas, e cada um purga só a sua.
