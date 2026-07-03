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
LATEST_PATH="$RELEASE_ROOT/latest.json"
CHANNEL="${UPDATE_CHANNEL:-${UPDATER_CHANNEL:-stable}}"
MANDATORY="${RELEASE_MANDATORY:-false}"
MIN_LAUNCHER_VERSION="${RELEASE_MIN_LAUNCHER_VERSION:-1.8.11}"
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
  WINDOWS_GUI_UPDATER.md \
  WINDOWS_CLIENT_HANDOFF.md \
  DOCKER_DEPLOYMENT.md; do
  cp "$ROOT/docs/$doc" "$DEST/docs/$doc"
done

launcher_source=""
launcher_publish_dir="$ROOT/launcher/GarmentsOS.Setup/bin/Release/net8.0-windows/win-x64/publish"
if [[ -d "$launcher_publish_dir" ]]; then
  launcher_source="$launcher_publish_dir"
fi

if [[ -n "$launcher_source" ]]; then
  mkdir -p "$DEST/launcher"
  cp -R "$launcher_source"/. "$DEST/launcher/"
else
  echo "Error: launcher publish directory was not found: $launcher_publish_dir" >&2
  echo "Run dotnet publish for the self-contained Windows launcher before building the release package." >&2
  exit 1
fi

app_asset=""
for candidate in \
  "$ROOT/GarmentsOS-PRO.exe" \
  "$launcher_source/GarmentsOS-PRO.exe"; do
  if [[ -n "$candidate" && -f "$candidate" ]]; then
    app_asset="$candidate"
    break
  fi
done

if [[ -n "$app_asset" ]]; then
  app_asset_size="$(wc -c < "$app_asset" | tr -d '[:space:]')"
  app_asset_sha256="$(sha256sum "$app_asset" | awk '{print $1}')"
  echo "Selected GarmentsOS-PRO.exe: $app_asset"
  echo "GarmentsOS-PRO.exe size: $app_asset_size bytes"
  echo "GarmentsOS-PRO.exe sha256: $app_asset_sha256"
  if (( app_asset_size < 20 * 1024 * 1024 )); then
    echo "Error: GarmentsOS-PRO.exe is too small for a self-contained launcher. Refusing to package likely stub EXE." >&2
    exit 1
  fi

  cp "$app_asset" "$DEST/GarmentsOS-PRO.exe"
  echo "Included GarmentsOS-PRO.exe at release package root from: $app_asset"
else
  echo "Error: GarmentsOS-PRO.exe was not found in root or exact publish folder." >&2
  exit 1
fi

echo "Release package root contents:"
find "$DEST" -maxdepth 1 -mindepth 1 -printf '  %f\n' | sort

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

if [[ -f "$DEST/GarmentsOS-PRO.exe" ]]; then
  package_exe_size="$(wc -c < "$DEST/GarmentsOS-PRO.exe" | tr -d '[:space:]')"
  if (( package_exe_size < 20 * 1024 * 1024 )); then
    echo "Error: release package root GarmentsOS-PRO.exe is too small: $package_exe_size bytes." >&2
    exit 1
  fi

  archive_has_app="false"
  if [[ "$ARCHIVE_PATH" == *.zip ]] && command -v unzip >/dev/null 2>&1; then
    if unzip -Z1 "$ARCHIVE_PATH" | grep -Fxq "$PACKAGE_NAME/GarmentsOS-PRO.exe"; then
      archive_has_app="true"
    fi
  elif [[ "$ARCHIVE_PATH" == *.zip ]] && command -v 7z >/dev/null 2>&1; then
    if 7z l -ba "$ARCHIVE_PATH" | awk '{print $NF}' | grep -Fxq "$PACKAGE_NAME/GarmentsOS-PRO.exe"; then
      archive_has_app="true"
    fi
  elif [[ "$ARCHIVE_PATH" == *.zip ]] && command -v 7zz >/dev/null 2>&1; then
    if 7zz l -ba "$ARCHIVE_PATH" | awk '{print $NF}' | grep -Fxq "$PACKAGE_NAME/GarmentsOS-PRO.exe"; then
      archive_has_app="true"
    fi
  elif [[ "$ARCHIVE_PATH" == *.tar.gz || "$ARCHIVE_PATH" == *.tgz ]] && command -v tar >/dev/null 2>&1; then
    if tar -tzf "$ARCHIVE_PATH" | grep -Fxq "$PACKAGE_NAME/GarmentsOS-PRO.exe"; then
      archive_has_app="true"
    fi
  elif [[ "$ARCHIVE_PATH" == *.tar.gz || "$ARCHIVE_PATH" == *.tgz ]] && command -v tar.exe >/dev/null 2>&1; then
    if tar.exe -tzf "$ARCHIVE_PATH" | grep -Fxq "$PACKAGE_NAME/GarmentsOS-PRO.exe"; then
      archive_has_app="true"
    fi
  else
    echo "Warning: could not inspect archive contents for GarmentsOS-PRO.exe." >&2
    archive_has_app="unknown"
  fi

  if [[ "$archive_has_app" == "false" ]]; then
    echo "Error: GarmentsOS-PRO.exe exists in release folder but was not found in archive." >&2
    exit 1
  elif [[ "$archive_has_app" == "true" ]]; then
    echo "Verified archive contains: $PACKAGE_NAME/GarmentsOS-PRO.exe"
  fi
else
  echo "Error: release package root is missing GarmentsOS-PRO.exe." >&2
  exit 1
fi

"$ROOT/scripts/validate-docker-release.sh" "$DEST"

cat > "$LATEST_PATH" <<JSON
{
  "app": "garmentsos-pro",
  "version": "$VERSION",
  "channel": "$CHANNEL",
  "mandatory": $MANDATORY,
  "released_at": "$built_at",
  "package_file": "$(basename "$ARCHIVE_PATH")",
  "package_sha256_file": "$(basename "$SHA_PATH")",
  "package_sha256": "$archive_checksum",
  "package_url": "PLACEHOLDER_GITHUB_RELEASE_ASSET_URL/$(basename "$ARCHIVE_PATH")",
  "setup_url": "PLACEHOLDER_GITHUB_RELEASE_ASSET_URL/GarmentsOS-PRO.exe",
  "min_launcher_version": "$MIN_LAUNCHER_VERSION",
  "notes": "GarmentsOS PRO $VERSION Docker client release. Manual install/update uses the Windows launchers. Auto-update is not implemented in this build."
}
JSON

echo "Built Docker client release:"
echo "  $DEST"
echo "  $ARCHIVE_PATH ($ARCHIVE_KIND)"
echo "  $SHA_PATH"
echo "  $LATEST_PATH"
