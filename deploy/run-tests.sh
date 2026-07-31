#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════════
# Roda a suite de testes usando a imagem de producao (mesmas extensoes PHP),
# em sqlite :memory: e SEM rede — nao ha como encostar no banco de producao.
# Uso:  /opt/gescon/deploy/run-tests.sh [--filter=NomeDoTeste]
#
# Pre-requisito (uma vez): instalar as dev-deps, que a imagem nao tem
# (o Dockerfile roda composer install --no-dev):
#   docker run --rm -v /opt/gescon/api:/app -w /app composer:2 install
# ═══════════════════════════════════════════════════════════════════════════
set -euo pipefail

REPO=/opt/gescon

if [ ! -d "$REPO/api/vendor/bin" ] || [ ! -f "$REPO/api/vendor/bin/phpunit" ]; then
  echo "ERRO: dev-deps ausentes. Rode primeiro:" >&2
  echo "  docker run --rm -v $REPO/api:/app -w /app composer:2 install" >&2
  exit 1
fi

# O repo inteiro e montado (nao so api/): AnaliticosApiTest le item3.3.xlsx da raiz.
exec docker run --rm \
  -v "$REPO:/repo" -w /repo/api \
  --network none \
  --entrypoint php \
  gescon-app:latest artisan test "$@"
