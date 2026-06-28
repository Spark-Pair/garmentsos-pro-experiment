#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

need() {
  command -v "$1" >/dev/null 2>&1 || { echo "Missing required command: $1" >&2; exit 1; }
}

need php
need composer

php -m | grep -qi '^pdo_sqlite$' || { echo "PHP pdo_sqlite extension is required." >&2; exit 1; }

if [[ ! -f .env ]]; then
  cp .env.example .env
  echo "Created .env from .env.example. Review it before exposing the app on LAN."
fi

DB_PATH="$(php -r '$env=parse_ini_file(".env", false, INI_SCANNER_RAW); echo $env["DB_DATABASE"] ?? "database/database.sqlite";' 2>/dev/null || echo database/database.sqlite)"
mkdir -p "$(dirname "$DB_PATH")" storage/app/private storage/app/backups storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

if [[ ! -f "$DB_PATH" ]]; then
  touch "$DB_PATH"
  echo "Created SQLite database file: $DB_PATH"
fi

composer install --no-dev --optimize-autoloader

if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi

php artisan migrate --force
php artisan optimize:clear
php artisan config:cache

php artisan tinker --execute='app(App\Services\BackupService::class)->createManualBackup("post_install_backup");' || true

echo "Install complete. LAN test command:"
echo "php artisan serve --host=0.0.0.0 --port=8000"
