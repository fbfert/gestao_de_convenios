#!/bin/bash
set -e
cd /var/www/html

DB_HOST="${DB_HOST:-gescon-db}"
DB_PORT="${DB_PORT:-3306}"

# Garante o esqueleto de storage (o volume montado pode vir vazio) + permissões
mkdir -p storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/logs storage/app/public bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

echo "[entrypoint] aguardando o banco ${DB_HOST}:${DB_PORT}..."
until php -r '$c=@fsockopen($argv[1],(int)$argv[2]); exit($c?0:1);' "$DB_HOST" "$DB_PORT"; do
  sleep 2
done
echo "[entrypoint] banco disponível."

# composer install foi --no-scripts; redescobre pacotes agora (com .env presente)
php artisan package:discover --ansi || true

echo "[entrypoint] migrations..."
php artisan migrate --force

php artisan storage:link || true

# Caches de produção — SEM route:cache (há rotas com Closure em web.php)
php artisan config:clear
php artisan config:cache
php artisan view:cache || true

echo "[entrypoint] iniciando php-fpm + nginx (supervisord)."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/app.conf
