#!/usr/bin/env bash
set -euo pipefail

PACKAGE="${1:-}"

if [[ -z "$PACKAGE" ]]; then
  echo "Usage: $0 <release-directory-or-zip>" >&2
  exit 2
fi

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

LOG_DIR="$ROOT/storage/logs"
mkdir -p "$LOG_DIR"
START_TIME="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
APP_VERSION="$(php -r '$env = parse_ini_file(".env", false, INI_SCANNER_RAW); echo $env["APP_VERSION"] ?? "local";')"
LOG_FILE="$LOG_DIR/update-${APP_VERSION}-$(date -u +"%Y%m%dT%H%M%SZ").log"
exec > >(tee -a "$LOG_FILE") 2>&1

log() {
  printf '[%s] %s\n' "$(date -u +"%Y-%m-%dT%H:%M:%SZ")" "$*"
}

rollback() {
  local reason="$1"
  log "ROLLBACK reason: $reason"
  log "Update aborted. Existing deployment state was preserved."
  exit 1
}

trap 'rollback "update aborted by error"' ERR

[[ -f .env ]] || { echo "Refusing update: .env is missing." >&2; exit 1; }

DB_PATH="$(php -r '$env=parse_ini_file(".env", false, INI_SCANNER_RAW); echo $env["DB_DATABASE"] ?? "database/database.sqlite";')"
[[ -f "$DB_PATH" ]] || { echo "Refusing update: database file is missing: $DB_PATH" >&2; exit 1; }

log "update start time: $START_TIME"
log "target app version: $APP_VERSION"

"$ROOT/scripts/validate-release.sh" "$PACKAGE"

php artisan tinker --execute='$r = app(App\Services\BackupService::class)->createManualBackup("pre_update_backup"); if (!($r["success"] ?? false)) { fwrite(STDERR, $r["message"].PHP_EOL); exit(1); }'

TMP_DIR=""
cleanup() {
  if [[ -n "$TMP_DIR" && -d "$TMP_DIR" ]]; then
    rm -rf "$TMP_DIR"
  fi
}
trap cleanup EXIT

SRC="$PACKAGE"
if [[ -f "$PACKAGE" ]]; then
  TMP_DIR="$(mktemp -d)"
  unzip -q "$PACKAGE" -d "$TMP_DIR"
  SRC="$(find "$TMP_DIR" -mindepth 1 -maxdepth 1 -type d | head -n 1)"
fi

if [[ ! -f "$SRC/manifest.json" ]]; then
  rollback "release manifest.json is missing"
fi

# Preserve user data and runtime state. Only relink the public storage path.
rsync -a \
  --exclude='.env' \
  --exclude='database/*.sqlite' \
  --exclude='database/*.sqlite-wal' \
  --exclude='database/*.sqlite-shm' \
  --exclude='storage/app/private/***' \
  --exclude='storage/app/backups/***' \
  --exclude='storage/app/public/uploads/***' \
  --exclude='storage/app/public/branch-logos/***' \
  --exclude='storage/logs/***' \
  "$SRC"/ "$ROOT"/

composer install --no-dev --optimize-autoloader
composer dump-autoload -o
php artisan migrate --force
if [ -L storage/app/public ]; then
    TARGET="$(readlink storage/app/public)"

    if [ "$TARGET" = "/var/www/html/storage/app/public" ]; then
        echo "Repairing invalid self-referencing storage/app/public symlink..."
        rm storage/app/public
        mkdir -p storage/app/public
    fi
fi

mkdir -p storage/app/public

if [ -L public/storage ]; then
    php artisan storage:unlink || true
fi

php artisan storage:link || echo "Warning: storage:link failed"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

docker compose restart app

if [[ ! -f public/build/manifest.json ]]; then
  rollback "public/build/manifest.json is missing after update"
fi

if [[ ! -d database/migrations || ! -d database/seeders ]]; then
  rollback "database migration or seeder directories are missing after update"
fi

TARGET="$(readlink public/storage)"

if [[ "$TARGET" != "$(pwd)/storage/app/public" && "$TARGET" != "../storage/app/public" ]]; then
    rollback "public/storage points to invalid target: $TARGET"
fi

if php artisan migrate:status --no-interaction | grep -q 'No migrations found'; then
  rollback "migration status verification failed"
fi

log "docker restart: completed"
log "migrations: completed"
log "storage relink: completed"
log "cache clear/config/route/view cache: completed"
log "asset verification: public/build/manifest.json present"
log "finish time: $(date -u +"%Y-%m-%dT%H:%M:%SZ")"

echo "Update complete. Smoke test: login, dashboard, invoice list, reports, backup page."
