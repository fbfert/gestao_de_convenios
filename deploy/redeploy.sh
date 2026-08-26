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
# Guardado para a comparacao de bundle logo abaixo, antes de descartar.
corpo_html=$(cat "$body")
rm -f "$body"

# ── O container servido e mesmo o que acabou de ser buildado? ────────────────
#
# HTTP 200 + identidade nao provam isso: as duas coisas passam com um container
# antigo ainda de pe. Ja aconteceu de `up -d` reportar "Running" em vez de
# "Recreated" e o deploy sair "OK" sem ter publicado nada.
#
# O teste compara o nome do bundle servido com o que existe DENTRO da imagem
# nova. Os assets do Vite tem hash de conteudo no nome, entao nomes iguais
# significam bytes iguais. A comparacao e entre iguais de proposito: hash de
# build feito em outra maquina difere por versao de Node e de dependencia, e
# nao serve como referencia.
publicado=$(echo "$corpo_html" | grep -oE '/assets/index-[A-Za-z0-9_-]+\.js' | head -1)
# `|| true` obrigatorio: com `set -e`, atribuicao a partir de comando que
# falha derruba o script — e aqui falhar so significa "nao consegui comparar".
naimagem=$("${COMPOSE[@]}" exec -T gescon-app sh -c 'ls /var/www/html/public/assets/index-*.js 2>/dev/null | head -1' 2>/dev/null || true)
naimagem=$(printf '%s' "$naimagem" | tr -d '\r')
naimagem=${naimagem##*/}

if [ -z "$publicado" ] || [ -z "$naimagem" ]; then
  # Nao da para afirmar nem negar: avisa, mas nao reprova o deploy por isso.
  echo "   bundle -> nao consegui comparar (servido='${publicado:-?}' imagem='${naimagem:-?}')"
  atualizado=indeterminado
elif [ "${publicado##*/}" = "$naimagem" ]; then
  echo "   bundle -> $naimagem (servido = imagem)"
  atualizado=ok
else
  echo "   bundle -> SERVIDO ${publicado##*/} != IMAGEM $naimagem"
  echo "   ATENCAO: o container no ar nao e o que acabou de ser buildado."
  echo "   Corrija com: ${COMPOSE[*]} up -d --force-recreate gescon-app"
  atualizado=falha
fi

# O worker nao tem porta publicada: so da para checa-lo de dentro da rede.
worker=$("${COMPOSE[@]}" ps --format '{{.Service}} {{.Status}}' 2>/dev/null | grep '^gescon-worker' || true)
echo "   gescon-worker -> ${worker:-ausente}"

"${COMPOSE[@]}" ps

if [ "$code" = "200" ] && [ "$identidade" = "ok" ] && [ "$atualizado" != "falha" ]; then
  echo "==> OK"
else
  echo "==> ATENCAO: HTTP $code / identidade=$identidade / bundle=$atualizado — checar 'docker logs gescon-app'"
  exit 1
fi
