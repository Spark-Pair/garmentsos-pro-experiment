#!/usr/bin/env bash
set -euo pipefail

PACKAGE="${1:-}"

if [[ -z "$PACKAGE" ]]; then
  echo "Usage: $0 <release-directory-or-zip>" >&2
  exit 2
fi

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

[[ -f .env ]] || { echo "Refusing update: .env is missing." >&2; exit 1; }

DB_PATH="$(php -r '$env=parse_ini_file(".env", false, INI_SCANNER_RAW); echo $env["DB_DATABASE"] ?? "database/database.sqlite";')"
[[ -f "$DB_PATH" ]] || { echo "Refusing update: database file is missing: $DB_PATH" >&2; exit 1; }

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

rsync -a --delete \
  --exclude='.env' \
  --exclude='database/*.sqlite' \
  --exclude='database/*.sqlite-wal' \
  --exclude='database/*.sqlite-shm' \
  --exclude='storage/app/private/***' \
  --exclude='storage/app/backups/***' \
  --exclude='storage/logs/***' \
  "$SRC"/ "$ROOT"/

composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link || true
php artisan optimize:clear
php artisan config:cache

echo "Update complete. Smoke test: login, dashboard, invoice list, reports, backup page."
