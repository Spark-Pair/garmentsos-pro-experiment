#!/usr/bin/env bash
set -euo pipefail

VERSION="${1:-}"
ALLOW_DIRTY="${2:-}"

if [[ -z "$VERSION" ]]; then
  echo "Usage: $0 <version> [--allow-dirty]" >&2
  exit 2
fi

if [[ "$ALLOW_DIRTY" != "--allow-dirty" && -n "$(git status --porcelain)" ]]; then
  echo "Working tree is dirty. Commit/revert changes or pass --allow-dirty for local test builds." >&2
  exit 1
fi

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RELEASE_ROOT="$ROOT/releases"
PACKAGE_NAME="garmentsos-pro-$VERSION"
DEST="$RELEASE_ROOT/$PACKAGE_NAME"
ZIP_PATH="$RELEASE_ROOT/$PACKAGE_NAME.zip"
SHA_PATH="$RELEASE_ROOT/$PACKAGE_NAME.sha256"
MANIFEST_PATH="$RELEASE_ROOT/$PACKAGE_NAME-manifest.json"
CHANNEL="${UPDATER_CHANNEL:-stable}"

rm -rf "$DEST" "$ZIP_PATH" "$SHA_PATH" "$MANIFEST_PATH"
mkdir -p "$DEST" "$RELEASE_ROOT"

include_paths=(
  "app"
  "bootstrap"
  "config"
  "database/migrations"
  "public"
  "resources"
  "routes"
  "docs"
  "scripts"
  "artisan"
  "composer.json"
  "composer.lock"
  "package.json"
  "package-lock.json"
  ".env.example"
  "README.md"
  "Dockerfile"
  "docker-compose.yml"
  ".dockerignore"
  "docker"
)

for path in "${include_paths[@]}"; do
  if [[ -e "$ROOT/$path" ]]; then
    if [[ -d "$ROOT/$path" ]]; then
      mkdir -p "$DEST/$path"
      rsync -a "$ROOT/$path"/ "$DEST/$path"/
    else
      mkdir -p "$DEST/$(dirname "$path")"
      rsync -a "$ROOT/$path" "$DEST/$path"
    fi
  fi
done

rm -rf \
  "$DEST/.git" \
  "$DEST/.github" \
  "$DEST/node_modules" \
  "$DEST/tests" \
  "$DEST/storage" \
  "$DEST/vendor"

find "$DEST" -type f \( \
  -name '.env' -o \( -name '.env.*' ! -name '.env.example' \) -o -name '*.sqlite' -o -name '*.sqlite-wal' -o -name '*.sqlite-shm' \
  -o -name '*.dump' -o -name '*.sql' -o -name '*.bak' \
  -o -name '*.pem' -o -name '*.key' -o -name '*.pfx' -o -name '*.crt' \
  -o -name '.phpunit.result.cache' -o -name 'auth.json' -o -name 'public/hot' \
  \) -delete

for dir in \
  "storage/app" \
  "storage/app/private" \
  "storage/app/backups" \
  "storage/framework/cache" \
  "storage/framework/sessions" \
  "storage/framework/views" \
  "storage/logs" \
  "bootstrap/cache"; do
  mkdir -p "$DEST/$dir"
  touch "$DEST/$dir/.gitkeep"
done

built_at="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
commit="$(git rev-parse HEAD)"
branch="$(git branch --show-current)"
file_count="$(find "$DEST" -type f | wc -l | tr -d ' ')"

(
  cd "$RELEASE_ROOT"
  zip -qr "$ZIP_PATH" "$PACKAGE_NAME"
)

checksum="$(sha256sum "$ZIP_PATH" | awk '{print $1}')"
printf '%s  %s\n' "$checksum" "$(basename "$ZIP_PATH")" > "$SHA_PATH"

cat > "$MANIFEST_PATH" <<JSON
{
  "app": "garmentsos-pro",
  "version": "$VERSION",
  "git_commit": "$commit",
  "git_branch": "$branch",
  "built_at": "$built_at",
  "file_count": $file_count,
  "checksum": "$checksum",
  "package_name": "$(basename "$ZIP_PATH")",
  "update_channel": "$CHANNEL"
}
JSON

"$ROOT/scripts/validate-release.sh" "$DEST"
"$ROOT/scripts/validate-release.sh" "$ZIP_PATH"

echo "Built release:"
echo "  $DEST"
echo "  $ZIP_PATH"
echo "  $SHA_PATH"
echo "  $MANIFEST_PATH"
