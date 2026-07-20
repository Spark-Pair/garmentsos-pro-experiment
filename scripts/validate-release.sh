#!/usr/bin/env bash
set -euo pipefail

TARGET="${1:-}"

if [[ -z "$TARGET" ]]; then
  echo "Usage: $0 <release-directory-or-zip>" >&2
  exit 2
fi

if [[ ! -e "$TARGET" ]]; then
  echo "Release target not found: $TARGET" >&2
  exit 2
fi

TMP_DIR=""
SCAN_ROOT="$TARGET"

cleanup() {
  if [[ -n "$TMP_DIR" && -d "$TMP_DIR" ]]; then
    rm -rf "$TMP_DIR"
  fi
}
trap cleanup EXIT

if [[ -f "$TARGET" ]]; then
  case "$TARGET" in
    *.zip)
      TMP_DIR="$(mktemp -d)"
      unzip -q "$TARGET" -d "$TMP_DIR"
      SCAN_ROOT="$TMP_DIR"
      only_dir="$(find "$TMP_DIR" -mindepth 1 -maxdepth 1 -type d | head -n 1 || true)"
      top_count="$(find "$TMP_DIR" -mindepth 1 -maxdepth 1 | wc -l | tr -d ' ')"
      if [[ "$top_count" == "1" && -n "$only_dir" ]]; then
        SCAN_ROOT="$only_dir"
      fi
      ;;
    *)
      echo "Unsupported release file type: $TARGET" >&2
      exit 2
      ;;
  esac
fi

required=(
  "artisan"
  "composer.json"
  "composer.lock"
  ".env.example"
  "app"
  "bootstrap"
  "config"
  "database/migrations"
  "database/seeders"
  "public"
  "public/build/manifest.json"
  "resources"
  "routes"
  "vendor/autoload.php"
)

for path in "${required[@]}"; do
  if [[ ! -e "$SCAN_ROOT/$path" ]]; then
    echo "Missing required runtime path: $path" >&2
    exit 1
  fi
done

for dir in \
  "storage/app" \
  "storage/app/private" \
  "storage/app/backups" \
  "storage/framework/cache" \
  "storage/framework/sessions" \
  "storage/framework/views" \
  "storage/logs" \
  "bootstrap/cache" \
  "vendor/composer"; do
  if [[ ! -d "$SCAN_ROOT/$dir" ]]; then
    echo "Missing runtime directory: $dir" >&2
    exit 1
  fi
done

bad_paths="$(
  cd "$SCAN_ROOT"
  find . \
    \( -path './.git' -o -path './.git/*' \
    -o -path './.github' -o -path './.github/*' \
    -o -name '.env' -o \( -name '.env.*' ! -name '.env.example' \) \
    -o -path './database/*.sqlite' \
    -o -name '*.sqlite-wal' -o -name '*.sqlite-shm' \
    -o -path './storage/logs/*' ! -name '.gitkeep' \
    -o -path './storage/app/backups/*' ! -name '.gitkeep' \
    -o -path './storage/app/private/*' ! -name '.gitkeep' \
    -o -iname '*.dump' -o -iname '*.sql' -o -iname '*.bak' \
    -o -iname '*.pem' -o -iname '*.key' -o -iname '*.pfx' -o -iname '*.crt' \
    -o -path './node_modules' -o -path './node_modules/*' \
    -o -path './tests' -o -path './tests/*' \
    -o -name '.phpunit.result.cache' -o -name 'auth.json' \) \
    -print
)"

if [[ -n "$bad_paths" ]]; then
  echo "Forbidden files found in release package:" >&2
  echo "$bad_paths" >&2
  exit 1
fi

if grep -R -I -n --exclude='validate-release.sh' --exclude='validate-docker-release.sh' -E 'APP_KEY=base64:[A-Za-z0-9+/=]{20,}|PUSHER_APP_SECRET=[^[:space:]]{8,}|LICENSE_PRIVATE_KEY=.+|-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----|github_pat_[A-Za-z0-9_]{20,}|ghp_[A-Za-z0-9]{20,}' "$SCAN_ROOT" >/tmp/garmentsos-release-secret-scan.txt 2>/dev/null; then
  echo "Potential secret values found in release package:" >&2
  cat /tmp/garmentsos-release-secret-scan.txt >&2
  rm -f /tmp/garmentsos-release-secret-scan.txt
  exit 1
fi
rm -f /tmp/garmentsos-release-secret-scan.txt

echo "Release validation passed: $TARGET"
