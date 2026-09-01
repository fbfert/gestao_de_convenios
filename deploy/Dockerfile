# syntax=docker/dockerfile:1

# ---------- 1) Build do frontend React (Vite) ----------
FROM node:22-alpine AS web
WORKDIR /web
COPY web/package.json web/package-lock.json ./
RUN npm ci
COPY web/ ./
# Mesma origem: SPA e API sob https://gescon.xiax.com.br
ENV VITE_API_URL=/api
RUN npm run build   # gera /web/dist

# ---------- 2) Dependências PHP (composer) ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY api/ ./
# --ignore-platform-req=ext-gd: esta imagem "composer:2" só resolve
# dependências, não roda a aplicação — o gd real é instalado no estágio
# "runtime" logo abaixo. Sem o ignore, phpoffice/phpspreadsheet (que
# declara gd como sugestão de plataforma) falha aqui mesmo já estando
# disponível em produção.
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-req=ext-gd

# ---------- 3) Runtime: php-fpm + nginx (supervisord) ----------
FROM php:8.4-fpm-bookworm AS runtime

# Extensões PHP
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions \
 && install-php-extensions pdo_mysql mbstring bcmath opcache zip gd intl pcntl exif

# nginx + supervisor
RUN apt-get update \
 && apt-get install -y --no-install-recommends nginx supervisor \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Código + vendor da API
COPY --from=vendor /app /var/www/html
# Build do frontend sobreposto no public/ (mantém index.php do Laravel)
COPY --from=web /web/dist/ /var/www/html/public/

# Configs
COPY deploy/nginx.conf       /etc/nginx/sites-available/default
COPY deploy/supervisord.conf /etc/supervisor/conf.d/app.conf
COPY deploy/php.ini          /usr/local/etc/php/conf.d/zz-app.ini
COPY deploy/entrypoint.sh    /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
 && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
