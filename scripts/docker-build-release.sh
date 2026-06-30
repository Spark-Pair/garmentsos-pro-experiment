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
TAR_GZ_PATH="$RELEASE_ROOT/$PACKAGE_NAME.tar.gz"
TAR_PATH="$RELEASE_ROOT/$PACKAGE_NAME.tar"
SHA_PATH="$RELEASE_ROOT/$PACKAGE_NAME.sha256"
CHANNEL="${UPDATER_CHANNEL:-stable}"
INSTALLED_MANIFEST="$ROOT/bootstrap/cache/installed-release.json"

rm -rf "$DEST" "$ZIP_PATH" "$TAR_GZ_PATH" "$TAR_PATH" "$SHA_PATH"
mkdir -p "$DEST/images" "$DEST/scripts" "$DEST/docs" "$DEST/checksums"

built_at="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
commit="$(git rev-parse HEAD)"
branch="$(git branch --show-current)"

cleanup_installed_manifest() {
  rm -f "$INSTALLED_MANIFEST"
}
trap cleanup_installed_manifest EXIT

mkdir -p "$(dirname "$INSTALLED_MANIFEST")"
cat > "$INSTALLED_MANIFEST" <<JSON
{
  "app": "garmentsos-pro",
  "version": "$VERSION",
  "channel": "$CHANNEL",
  "image": "$IMAGE",
  "image_tag": "$VERSION",
  "git_commit": "$commit",
  "git_branch": "$branch",
  "built_at": "$built_at",
  "deployment": "docker-first-client",
  "source": "docker-build-release"
}
JSON

docker build -t "$IMAGE" "$ROOT"
cleanup_installed_manifest
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

for launcher in \
  "Open GarmentsOS.bat" \
  "Install GarmentsOS.bat" \
  "Update GarmentsOS.bat" \
  "Stop GarmentsOS.bat" \
  "Backup GarmentsOS.bat"; do
  cp "$ROOT/scripts/package-launchers/$launcher.stub" "$DEST/$launcher"
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

cat > "$DEST/manifest.json" <<JSON
{
  "app": "garmentsos-pro",
  "version": "$VERSION",
  "channel": "$CHANNEL",
  "image": "$IMAGE",
  "image_tar": "images/$PACKAGE_NAME.tar",
  "image_sha256": "$tar_checksum",
  "git_commit": "$commit",
  "git_branch": "$branch",
  "built_at": "$built_at",
  "deployment": "docker-first-client"
}
JSON

ARCHIVE_PATH=""
ARCHIVE_KIND=""

if command -v zip >/dev/null 2>&1; then
  (
    cd "$RELEASE_ROOT"
    zip -qr "$ZIP_PATH" "$PACKAGE_NAME"
  )
  ARCHIVE_PATH="$ZIP_PATH"
  ARCHIVE_KIND="zip"
elif command -v 7z >/dev/null 2>&1; then
  (
    cd "$RELEASE_ROOT"
    7z a -tzip "$ZIP_PATH" "$PACKAGE_NAME" >/dev/null
  )
  ARCHIVE_PATH="$ZIP_PATH"
  ARCHIVE_KIND="7z zip"
elif command -v 7zz >/dev/null 2>&1; then
  (
    cd "$RELEASE_ROOT"
    7zz a -tzip "$ZIP_PATH" "$PACKAGE_NAME" >/dev/null
  )
  ARCHIVE_PATH="$ZIP_PATH"
  ARCHIVE_KIND="7zz zip"
elif command -v tar.exe >/dev/null 2>&1; then
  (
    cd "$RELEASE_ROOT"
    tar.exe -czf "${PACKAGE_NAME}.tar.gz" "$PACKAGE_NAME"
  )
  ARCHIVE_PATH="$TAR_GZ_PATH"
  ARCHIVE_KIND="tar.gz"
elif command -v tar >/dev/null 2>&1; then
  (
    cd "$RELEASE_ROOT"
    tar -czf "${PACKAGE_NAME}.tar.gz" "$PACKAGE_NAME"
  )
  ARCHIVE_PATH="$TAR_GZ_PATH"
  ARCHIVE_KIND="tar.gz"
elif command -v powershell.exe >/dev/null 2>&1; then
  RELEASE_ROOT_WIN="$(cygpath -w "$RELEASE_ROOT" 2>/dev/null || printf '%s' "$RELEASE_ROOT")"
  ZIP_PATH_WIN="$(cygpath -w "$ZIP_PATH" 2>/dev/null || printf '%s' "$ZIP_PATH")"
  image_size_bytes="$(wc -c < "$IMAGE_TAR" | tr -d '[:space:]')"
  max_compress_archive_bytes=$((1800 * 1024 * 1024))
  if (( image_size_bytes > max_compress_archive_bytes )); then
    echo "PowerShell Compress-Archive fallback is not safe for this large Docker image and no zip/7z/tar fallback was found." >&2
    exit 1
  fi

  powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "Compress-Archive -Path ""${RELEASE_ROOT_WIN}\${PACKAGE_NAME}\*"" -DestinationPath ""${ZIP_PATH_WIN}"" -Force"
  ARCHIVE_PATH="$ZIP_PATH"
  ARCHIVE_KIND="PowerShell zip"
else
  echo "No supported archive tool found. Install zip, 7z, or use Windows tar.exe." >&2
  exit 1
fi

archive_checksum="$(sha256sum "$ARCHIVE_PATH" | awk '{print $1}')"
printf '%s  %s\n' "$archive_checksum" "$(basename "$ARCHIVE_PATH")" > "$SHA_PATH"

"$ROOT/scripts/validate-docker-release.sh" "$DEST"

echo "Built Docker client release:"
echo "  $DEST"
echo "  $ARCHIVE_PATH ($ARCHIVE_KIND)"
echo "  $SHA_PATH"
