# v2-06 — Deploy na VPS de produção

Primeiro deploy do GESCON em `gescon.xiax.com.br`. Este documento é o registro
do que foi instalado e o runbook de operação.

---

## 1. Onde as coisas estão

| Item | Caminho |
|---|---|
| Repositório | `/opt/gescon` (branch `main`) |
| Compose de produção | `/opt/gescon/deploy/docker-compose.prod.yml` |
| Script de redeploy | `/opt/gescon/deploy/redeploy.sh` |
| Segredos do banco e do worker | `/opt/gescon/deploy/.secrets.env` (`600`) |
| Ambiente do Laravel | `/opt/gescon/api/.env` (`600`) |
| Vhost do domínio | `/etc/httpd/conf/httpd.conf` (gerado pelo Virtualmin) |
| Certificado TLS | `/etc/ssl/virtualmin/178587246817599/` |

Os dois arquivos de segredo estão cobertos pelo `.gitignore`
(`api/.gitignore:8` e `.gitignore:37`) e **nunca** devem ser comitados.
Nenhum valor de segredo aparece nesta documentação — apenas os caminhos.

---

## 2. Arquitetura nesta VPS

> **Divergência importante em relação ao plano original.** O plano previa nginx
> no host + `certbot --nginx`. Esta VPS **não usa nginx no host**: ela roda
> Apache sob Virtualmin, servindo três domínios (`xiax.com.br`,
> `gestaonossa.com.br` e `gescon.xiax.com.br`) nas portas 80/443. Instalar
> nginx exigiria parar o Apache e derrubaria dois sites em produção. O proxy
> reverso foi feito no Apache, que é o mesmo padrão já usado pelo
> `gestaonossa.com.br` (containers em `127.0.0.1:4101/4102`).

```
Internet
   │  443/80
   ▼
Apache (Virtualmin)  ── vhost gescon.xiax.com.br
   │   ProxyPass /.well-known !      ← ACME fica no host (renovação do cert)
   │   ProxyPass / → 127.0.0.1:4105
   │   RequestHeader X-Forwarded-Proto
   │   LimitRequestBody 52428800
   ▼
gescon-app  (127.0.0.1:4105 → :80)
   │  rede docker interna `gescon`
   ├──► gescon-db      MariaDB 11
   └──► gescon-worker  http://gescon-worker:8787
```

### Serviços Docker

| Serviço | Imagem | Porta | Propósito |
|---|---|---|---|
| `gescon-db` | `mariadb:11` | só rede interna | Banco `gestao_convenios` |
| `gescon-app` | build `deploy/Dockerfile` | `127.0.0.1:4105` | nginx + php-fpm + `queue:work` + `schedule:work` (supervisord) |
| `gescon-worker` | build `deploy/Dockerfile.worker` | **nenhuma publicada** | Automações Unimed (Playwright) |

O `gescon-worker` **não publica porta no host** de propósito: apenas o
`gescon-app` precisa alcançá-lo, e isso acontece pela rede interna. Publicá-lo
exporia um browser headless à internet.

O `gescon-app` **não** declara `depends_on` do worker: se o worker cair, o
sistema continua no ar e só as automações Unimed param. Isso é intencional.

### Melhoria conhecida (não aplicada)

O `deploy/supervisord.conf` não tem as seções `[unix_http_server]` e
`[supervisorctl]`, então `supervisorctl status` não funciona dentro do
`gescon-app`. Não é bloqueante — dá para inspecionar via `/proc` e pelos logs
(ver runbook) —, mas adicioná-las tornaria a operação bem mais confortável.

---

## 3. Versões escolhidas

- **Playwright:** `1.62.1` — versão exata resolvida em
  `worker-unimed/package-lock.json`.
- **Imagem do worker:** `mcr.microsoft.com/playwright:v1.62.1-jammy`. A tag
  exata existe na registry, então não foi preciso aproximar para outra variante.

> A tag da imagem e a versão do pacote npm **precisam andar juntas**: a imagem
> traz os binários dos navegadores pré-instalados em `/ms-playwright`, e a lib
> só funciona com navegadores da mesma versão. Ao subir o Playwright no
> `package.json`, suba a tag do `deploy/Dockerfile.worker` junto.

### Ajustes feitos no repositório para viabilizar produção

1. **`worker-unimed/package.json`** — `playwright` movido de `devDependencies`
   para `dependencies`; sem isso o `npm ci --omit=dev` do build de produção não
   instalaria a lib. O `package-lock.json` foi ressincronizado (o `npm ci` falha
   se lockfile e manifesto divergirem).

2. **`worker-unimed/src/server.js`** — o bind era fixo em `127.0.0.1`, o que
   dentro de um container só responderia a ele mesmo e deixaria o
   `gescon-app` sem alcançar o worker. Agora o host é configurável via
   `UNIMED_WORKER_HOST`, **mantendo `127.0.0.1` como padrão** (execução local
   não muda); o `Dockerfile.worker` e o compose passam `0.0.0.0`.

3. **`deploy/redeploy.sh`** — o build era fixo em `gescon-app`, o que deixaria o
   worker preso numa imagem velha após um `git pull`. Agora builda todos os
   serviços com seção `build:`.

---

## 4. Redeploy

```bash
/opt/gescon/deploy/redeploy.sh
```

Faz `git pull --ff-only`, rebuilda **as duas** imagens, recria os containers e
roda o smoke test. Já cobre o `gescon-worker` (ver ajuste 3 acima).

Para rebuildar só um serviço:

```bash
cd /opt/gescon
C="docker compose --env-file deploy/.secrets.env -f deploy/docker-compose.prod.yml"
$C build gescon-worker && $C up -d gescon-worker
```

---

## 5. Operação do dia a dia

```bash
cd /opt/gescon
C="docker compose --env-file deploy/.secrets.env -f deploy/docker-compose.prod.yml"

$C ps                      # estado de todos os serviços
$C logs -f gescon-app      # app (nginx, php-fpm, fila, scheduler)
$C logs -f gescon-worker   # automações Unimed
$C logs -f gescon-db       # banco
$C restart gescon-worker   # reiniciar um serviço
$C down                    # derrubar o stack (dados ficam nos volumes)
```

Logs do Apache (camada de proxy, fora do Docker):

```bash
tail -f /var/log/virtualmin/gescon.xiax.com.br_error_log
tail -f /var/log/virtualmin/gescon.xiax.com.br_access_log
```

Health do worker — **só de dentro da rede Docker**, ele não tem porta pública.
O `/health` passa pelo mesmo gate de autenticação do resto da API, então
precisa do Bearer:

```bash
docker exec gescon-worker node -e "fetch('http://127.0.0.1:8787/health',{headers:{authorization:'Bearer '+process.env.UNIMED_WORKER_TOKEN}}).then(r=>r.json()).then(console.log)"
```

---

## 6. Runbook de troubleshooting

### O worker caiu / está `unhealthy`

1. `$C ps` — confirme o estado e há quanto tempo.
2. `$C logs --tail=100 gescon-worker` — procure stack trace do Playwright
   (falta de memória e browser morto são as causas mais comuns).
3. Teste o `/health` pelo comando da seção 5. Se responder **401**, o
   `UNIMED_WORKER_TOKEN` do container divergiu do `WORKER_TOKEN` do
   `.secrets.env` — recrie com `$C up -d gescon-worker`.
4. `$C restart gescon-worker`. Se não voltar, `$C build gescon-worker` e suba
   de novo.
5. O app continua no ar com o worker fora: só as automações Unimed param.
   Isso é intencional — o `gescon-app` **não** depende do worker para subir.

### O certificado expirou / navegador reclama

O certificado é gerido pelo **Virtualmin**, não por `certbot --nginx`.

1. `certbot certificates` — veja a validade de `gescon.xiax.com.br`.
2. Confirme que a renovação automática está ligada:
   `grep letsencrypt_renew /etc/webmin/virtual-server/domains/178587246817599`
   (precisa ser `=1`; vazio significa renovação desligada e expiração
   silenciosa).
3. Confirme que o ACME não está sendo capturado pelo proxy — o
   `ProxyPass /.well-known !` **precisa** vir antes do `ProxyPass /` no vhost:
   ```bash
   curl -o /dev/null -w '%{http_code}\n' http://gescon.xiax.com.br/.well-known/acme-challenge/x
   ```
   Tem que responder `404` (tratado no host). Se responder `502`/`503`, foi
   para o container e a renovação vai falhar.
4. Renovação manual: `virtualmin generate-letsencrypt-cert --domain gescon.xiax.com.br --renew ...`
5. Depois de renovar, confira que a cadeia ficou completa — já houve caso de o
   `ssl.combined` sair só com a folha e quebrar o TLS:
   ```bash
   grep -c 'BEGIN CERTIFICATE' /etc/ssl/virtualmin/178587246817599/ssl.combined  # esperado: 3
   openssl s_client -connect 127.0.0.1:443 -servername gescon.xiax.com.br </dev/null 2>/dev/null | grep 'Verify return code'
   ```

### A fila parou

A fila roda **dentro** do `gescon-app`, via supervisord
(`queue:work --queue=default,automacoes`).

1. Confirme que o processo está vivo. **`supervisorctl` não funciona nesta
   imagem** — o `deploy/supervisord.conf` não declara `[unix_http_server]`,
   então não há socket de controle. Use o `/proc` (a imagem também não tem
   `ps`):
   ```bash
   docker exec gescon-app sh -c 'for p in /proc/[0-9]*; do tr "\0" " " < $p/cmdline 2>/dev/null | grep -q "queue:work" && echo vivo; done'
   ```
   Ou olhe o estado que o supervisord reporta no log:
   ```bash
   docker logs gescon-app 2>&1 | grep -E 'queue.*(RUNNING|exited|FATAL|BACKOFF)'
   ```
2. `$C logs --tail=100 gescon-app | grep -i queue`.
3. Jobs falhados: `docker exec gescon-app php artisan queue:failed`.
4. Reprocessar: `docker exec gescon-app php artisan queue:retry all`.
5. Reiniciar os workers: `docker exec gescon-app php artisan queue:restart`
   (o supervisord sobe de novo automaticamente).
6. `QUEUE_CONNECTION=database` — se a fila não anda e não há erro, confirme
   que o banco responde: `$C exec gescon-db mariadb -u root -p`.

### O site responde 502 ou 503

Significa que o Apache está de pé mas o container não responde na 4105.

1. `$C ps` — o `gescon-app` está `Up`?
2. `ss -lntp | grep 4105` — alguém escutando?
3. `$C logs --tail=50 gescon-app`.
4. Erro de migration no boot trava o entrypoint antes do nginx subir — é a
   causa mais comum num primeiro deploy.

---

## 7. Firewall

O host usa **firewalld** (padrão do AlmaLinux), não ufw. Estado esperado:

```
ssh, http, https   liberados
4105, 8787         NÃO liberados
```

O `4105` só escuta em `127.0.0.1` e o `8787` só existe na rede interna do
Docker. Nenhuma regra precisa ser aberta para eles — se algum dia aparecerem
no `firewall-cmd --list-all`, é erro de configuração.

---

## 8. Pendências para o operador humano

- Guardar `DB_PASS`, `DB_ROOT` e `WORKER_TOKEN` num cofre de senhas (os valores
  vivem só em `/opt/gescon/deploy/.secrets.env`, sem backup automático).
- Cadastrar a credencial real da Unimed em Configurações.
- Decidir quando rodar a Etapa 5 de homologação assistida com o worker já em
  produção (ver `v2-05-homologacao-real.md`).
- Decidir quando dar `git push` — o commit deste deploy foi feito localmente,
  **sem push**.
