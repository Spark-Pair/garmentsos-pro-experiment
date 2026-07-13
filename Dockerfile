FROM node:20-bookworm AS assets
WORKDIR /app
COPY package*.json vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN if [ -f package.json ] && grep -q '"build"' package.json; then npm ci && npm run build; else echo "No frontend build script configured"; fi

FROM php:8.2-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends nginx supervisor sqlite3 libsqlite3-dev unzip git curl \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .
COPY --from=assets /app/public/build ./public/build
COPY docker/php.ini /usr/local/etc/php/conf.d/garmentsos.ini
COPY docker/nginx/default.conf /etc/nginx/sites-available/default
COPY docker/entrypoint.sh /usr/local/bin/garmentsos-entrypoint

RUN chmod +x /usr/local/bin/garmentsos-entrypoint \
    && composer config --global process-timeout 2000 \
    && (composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist \
        || composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-source) \
    && mkdir -p storage/app/license storage/app/private/backups storage/app/backups storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 8000
ENTRYPOINT ["garmentsos-entrypoint"]
