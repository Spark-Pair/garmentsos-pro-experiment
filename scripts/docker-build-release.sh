#!/usr/bin/env bash
set -euo pipefail

VERSION="${1:-}"
ALLOW_DIRTY="${2:-}"

if [[ -z "$VERSION" ]]; then
  echo "Usage: $0 <version> [--allow-dirty]" >&2
  exit 2
fi

if ! command -v docker >/dev/null 2>&1; then
  echo "Docker is required to build a Docker client release." >&2
  exit 127
fi

if [[ "$ALLOW_DIRTY" != "--allow-dirty" && -n "$(git status --porcelain)" ]]; then
  echo "Working tree is dirty. Commit/revert changes or pass --allow-dirty for local test builds." >&2
  exit 1
fi

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RELEASE_ROOT="$ROOT/docker-releases"
PACKAGE_NAME="garmentsos-pro-$VERSION"
DEST="$RELEASE_ROOT/$PACKAGE_NAME"
IMAGE="sparkpair/garmentsos-pro:$VERSION"
IMAGE_TAR="$DEST/images/$PACKAGE_NAME.tar"
ZIP_PATH="$RELEASE_ROOT/$PACKAGE_NAME.zip"
SHA_PATH="$RELEASE_ROOT/$PACKAGE_NAME.sha256"

rm -rf "$DEST" "$ZIP_PATH" "$SHA_PATH"
mkdir -p "$DEST/images" "$DEST/scripts" "$DEST/docs" "$DEST/checksums"

docker build -t "$IMAGE" "$ROOT"
docker save "$IMAGE" -o "$IMAGE_TAR"

cp "$ROOT/docker-compose.yml" "$DEST/docker-compose.yml"
cp "$ROOT/.env.example" "$DEST/.env.example"

for script in \
  windows-docker-install.ps1 \
  windows-docker-update.ps1 \
  windows-docker-backup.ps1 \
  windows-docker-restore.ps1 \
  install.bat \
  update.bat \
  run-lan.bat \
  stop.bat; do
  cp "$ROOT/scripts/$script" "$DEST/scripts/$script"
done

for doc in \
  WINDOWS_DOCKER_INSTALL.md \
  WINDOWS_DOCKER_UPDATE.md \
  WINDOWS_CLIENT_HANDOFF.md \
  DOCKER_DEPLOYMENT.md; do
  cp "$ROOT/docs/$doc" "$DEST/docs/$doc"
done

tar_checksum="$(sha256sum "$IMAGE_TAR" | awk '{print $1}')"
printf '%s  %s\n' "$tar_checksum" "images/$PACKAGE_NAME.tar" > "$DEST/checksums/$PACKAGE_NAME.sha256"

built_at="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
commit="$(git rev-parse HEAD)"
branch="$(git branch --show-current)"

cat > "$DEST/manifest.json" <<JSON
{
  "app": "garmentsos-pro",
  "version": "$VERSION",
  "image": "$IMAGE",
  "image_tar": "images/$PACKAGE_NAME.tar",
  "image_sha256": "$tar_checksum",
  "git_commit": "$commit",
  "git_branch": "$branch",
  "built_at": "$built_at",
  "deployment": "docker-first-client"
}
JSON

if command -v zip >/dev/null 2>&1; then
  (
    cd "$RELEASE_ROOT"
    zip -qr "$ZIP_PATH" "$PACKAGE_NAME"
  )
elif command -v powershell.exe >/dev/null 2>&1; then
  RELEASE_ROOT_WIN="$(cygpath -w "$RELEASE_ROOT" 2>/dev/null || printf '%s' "$RELEASE_ROOT")"
  ZIP_PATH_WIN="$(cygpath -w "$ZIP_PATH" 2>/dev/null || printf '%s' "$ZIP_PATH")"
  powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "Compress-Archive -Path ""${RELEASE_ROOT_WIN}\${PACKAGE_NAME}\*"" -DestinationPath ""${ZIP_PATH_WIN}"" -Force"
else
  echo "zip command not found and PowerShell fallback is unavailable." >&2
  exit 1
fi

zip_checksum="$(sha256sum "$ZIP_PATH" | awk '{print $1}')"
printf '%s  %s\n' "$zip_checksum" "$(basename "$ZIP_PATH")" > "$SHA_PATH"

"$ROOT/scripts/validate-docker-release.sh" "$DEST"

echo "Built Docker client release:"
echo "  $DEST"
echo "  $ZIP_PATH"
echo "  $SHA_PATH"
