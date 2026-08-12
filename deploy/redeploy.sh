#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════════
# Redeploy do gescon: git pull + rebuild da imagem + recriar container.
# Uso:  /opt/gescon/deploy/redeploy.sh
# ═══════════════════════════════════════════════════════════════════════════
set -euo pipefail

REPO=/opt/gescon
DEPLOY="$REPO/deploy"
COMPOSE=(docker compose --env-file "$DEPLOY/.secrets.env" -f "$DEPLOY/docker-compose.prod.yml")

echo "==> git pull"
git -C "$REPO" pull --ff-only origin main

echo "==> build das imagens (gescon-app + gescon-worker)"
# Sem nome de servico: builda tudo que tem secao `build:`. Antes estava fixo em
# gescon-app, o que deixava o worker preso numa imagem velha apos o git pull.
"${COMPOSE[@]}" build

echo "==> recriar container"
"${COMPOSE[@]}" up -d

echo "==> aguardando o app subir"
sleep 12

echo "==> smoke test"
# O host tem que ser gescon.gestaonossa.com.br: e o unico com vhost apontando
# para 127.0.0.1:4105. gescon.xiax.com.br nao tem vhost nem DNS, cai no vhost
# padrao (o Next.js do gestaonossa) e devolvia 200 do app errado — o script
# reportava OK sem ter publicado nada.
URL=https://gescon.gestaonossa.com.br/
body=$(mktemp)
code=$(curl -s -o "$body" -w "%{http_code}" "$URL")
echo "   $URL -> HTTP $code"

# 200 sozinho nao prova nada: confirma que quem respondeu e a SPA do gescon.
if grep -q 'gestao-convenios-tema' "$body"; then
  identidade=ok
else
  identidade=falha
  echo "   ATENCAO: resposta nao parece o gescon (vhost errado?)"
fi
rm -f "$body"

# O worker nao tem porta publicada: so da para checa-lo de dentro da rede.
worker=$("${COMPOSE[@]}" ps --format '{{.Service}} {{.Status}}' 2>/dev/null | grep '^gescon-worker' || true)
echo "   gescon-worker -> ${worker:-ausente}"

"${COMPOSE[@]}" ps

if [ "$code" = "200" ] && [ "$identidade" = "ok" ]; then
  echo "==> OK"
else
  echo "==> ATENCAO: HTTP $code / identidade=$identidade — checar 'docker logs gescon-app'"
  exit 1
fi
