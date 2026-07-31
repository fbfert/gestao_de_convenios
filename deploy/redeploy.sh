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

echo "==> build da imagem"
"${COMPOSE[@]}" build gescon-app

echo "==> recriar container"
"${COMPOSE[@]}" up -d

echo "==> aguardando o app subir"
sleep 12

echo "==> smoke test"
code=$(curl -s -o /dev/null -w "%{http_code}" https://gescon.xiax.com.br/)
echo "   https://gescon.xiax.com.br/ -> HTTP $code"
"${COMPOSE[@]}" ps

if [ "$code" = "200" ]; then
  echo "==> OK"
else
  echo "==> ATENCAO: HTTP $code — checar 'docker logs gescon-app'"
  exit 1
fi
