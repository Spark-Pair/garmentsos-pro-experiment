#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

if [[ ! -f .env && "${GARMENTSOS_FIRST_INSTALL:-false}" == "true" && -f .env.example ]]; then
  cp .env.example .env
  echo "Created .env from .env.example because GARMENTSOS_FIRST_INSTALL=true."
fi

if [[ ! -f .env ]]; then
  echo "Missing .env. Mount a client-specific .env file into /var/www/html/.env." >&2
  exit 1
fi

mkdir -p \
  database \
  storage/app/license \
  storage/app/private/backups \
  storage/app/private/restore-jobs \
  storage/app/backups \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

echo "Repairing Laravel writable storage permissions."
chown -R www-data:www-data storage bootstrap/cache database || true
chmod -R ug+rwX storage bootstrap/cache database || true
find storage/framework/cache/data -mindepth 1 -maxdepth 10 -exec rm -rf {} + 2>/dev/null || true

DB_PATH="$(php -r '$env=parse_ini_file(".env", false, INI_SCANNER_RAW); echo $env["DB_DATABASE"] ?? "database/runtime/database.sqlite";')"
if [[ "$DB_PATH" != /* ]]; then
  DB_PATH="/var/www/html/$DB_PATH"
fi
mkdir -p "$(dirname "$DB_PATH")"
if [[ ! -f "$DB_PATH" ]]; then
  touch "$DB_PATH"
  chown www-data:www-data "$DB_PATH"
  chmod ug+rw "$DB_PATH"
  echo "Created missing SQLite database file at $DB_PATH"
fi

chown -R www-data:www-data storage bootstrap/cache database || true
chmod -R ug+rwX storage bootstrap/cache database || true

php artisan optimize:clear || true

if ! grep -q '^APP_KEY=base64:' .env && [[ "${GARMENTSOS_GENERATE_APP_KEY:-false}" == "true" ]]; then
  php artisan key:generate --force
fi

if [[ "${RUN_MIGRATIONS_ON_START:-false}" == "true" ]]; then
  echo "RUN_MIGRATIONS_ON_START=true: creating backup before migrations."
  php artisan tinker --execute='$r = app(App\Services\BackupService::class)->createManualBackup("docker_pre_migration_backup"); if (!($r["success"] ?? false)) { fwrite(STDERR, $r["message"].PHP_EOL); exit(1); }'
  php artisan migrate --force
fi

if [ ! -L public/storage ]; then
    mkdir -p storage/app/public
    php artisan storage:link || true
fi
php artisan optimize:clear || true
php artisan optimize || true

php-fpm -D
exec nginx -g 'daemon off;'
