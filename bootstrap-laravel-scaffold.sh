#!/usr/bin/env bash
set -euo pipefail

# Rode a partir da raiz do projeto (onde estão api/, web/, docs/,
# docker-compose.yml). Preenche api/ com o esqueleto real do Laravel 11 —
# artisan, composer.json, bootstrap/, config/, public/, routes/, etc. —
# SEM sobrescrever nada que você já colocou (app/Models, app/Scopes,
# app/Concerns, app/Http/Middleware, database/migrations).

if [ ! -d "api/app/Models" ]; then
  echo "Não encontrei api/app/Models — rode este script na raiz do projeto."
  exit 1
fi

echo "==> 1) Criando esqueleto Laravel 11 temporário..."
composer create-project laravel/laravel _laravel-base "^11.0"

echo "==> 2) Mesclando com api/ existente (só adiciona o que falta, nada é sobrescrito)..."
cp -an _laravel-base/. api/

echo "==> 3) Removendo pasta temporária..."
rm -rf _laravel-base

cd api

echo "==> 4) Instalando dependências base..."
composer install

echo "==> 5) Scaffolding de API + Sanctum (cria routes/api.php e publica migration do sanctum)..."
php artisan install:api --without-migration-prompt

echo "==> 6) Adicionando spatie/laravel-permission (sem publicar migrations ainda — só entra em uso na Etapa 2, ver ADR-08)..."
composer require spatie/laravel-permission

echo "==> 7) Adicionando Pint (formatação)..."
composer require --dev laravel/pint

cd ..

echo ""
echo "Falta fazer manualmente:"
echo " 1. Configurar api/.env: DB_CONNECTION=mysql, DB_HOST=127.0.0.1, DB_PORT=3306,"
echo "    DB_DATABASE=gestao_convenios, DB_USERNAME=gestao_convenios, DB_PASSWORD=gestao_convenios"
echo "    (bate com o docker-compose.yml)"
echo " 2. Subir o banco:  docker compose up -d"
echo " 3. Registrar o ResolveTenant em api/bootstrap/app.php (ver docs/notas-bloco1.md)"
echo " 4. Rodar:  cd api && php artisan migrate"
