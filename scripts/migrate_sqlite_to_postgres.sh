#!/usr/bin/env bash
set -euo pipefail

# SQLite -> Postgres data-only migration using pgloader.
#
# Usage:
#   export SQLITE_PATH="/absolute/path/to/database.sqlite"
#   export PG_DSN="postgresql://USER:PASSWORD@HOST:PORT/DB?sslmode=require"
#   ./scripts/migrate_sqlite_to_postgres.sh
#
# Requirements:
#   - pgloader installed on this machine
#   - network access to Postgres

if ! command -v pgloader >/dev/null 2>&1; then
  echo "pgloader not found. Install it first (e.g., apt-get install pgloader)." >&2
  exit 1
fi

SQLITE_PATH="${SQLITE_PATH:-}"
PG_DSN="${PG_DSN:-}"

if [[ -z "$SQLITE_PATH" || -z "$PG_DSN" ]]; then
  echo "Missing env vars. Set SQLITE_PATH and PG_DSN." >&2
  exit 1
fi

if [[ ! -f "$SQLITE_PATH" ]]; then
  echo "SQLite file not found at: $SQLITE_PATH" >&2
  exit 1
fi

tmp_load="$(mktemp -t pgloader-load-XXXX.load)"
trap 'rm -f "$tmp_load"' EXIT

cat >"$tmp_load" <<LOAD
LOAD DATABASE
     FROM sqlite:///${SQLITE_PATH}
     INTO ${PG_DSN}
WITH data only, reset sequences, prefetch rows = 10000, batch rows = 5000, workers = 4;
LOAD

echo "Running pgloader (data-only) ..."
pgloader "$tmp_load"
echo "Done."

