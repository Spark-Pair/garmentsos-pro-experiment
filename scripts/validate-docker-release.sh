#!/usr/bin/env bash
set -euo pipefail

TARGET="${1:-}"

if [[ -z "$TARGET" || ! -d "$TARGET" ]]; then
  echo "Usage: $0 <docker-release-directory>" >&2
  exit 2
fi

required=(
  "docker-compose.yml"
  ".env.example"
  "manifest.json"
  "Open GarmentsOS.bat"
  "Install GarmentsOS.bat"
  "Update GarmentsOS.bat"
  "Stop GarmentsOS.bat"
  "Backup GarmentsOS.bat"
  "images"
  "checksums"
  "scripts/windows-docker-install.ps1"
  "scripts/windows-docker-update.ps1"
  "scripts/windows-docker-backup.ps1"
  "scripts/windows-docker-restore.ps1"
  "scripts/install.bat"
  "scripts/update.bat"
  "scripts/run-lan.bat"
  "scripts/stop.bat"
  "docs/WINDOWS_DOCKER_INSTALL.md"
  "docs/WINDOWS_DOCKER_UPDATE.md"
  "docs/WINDOWS_GUI_UPDATER.md"
  "docs/WINDOWS_CLIENT_HANDOFF.md"
)

for path in "${required[@]}"; do
  if [[ ! -e "$TARGET/$path" ]]; then
    echo "Missing Docker release path: $path" >&2
    exit 1
  fi
done

bad_paths="$(
  cd "$TARGET"
  find . \
    \( -path './.git' -o -path './.git/*' \
    -o -path './.github' -o -path './.github/*' \
    -o -name '.env' -o \( -name '.env.*' ! -name '.env.example' \) \
    -o -name '*.sqlite' -o -name '*.sqlite-wal' -o -name '*.sqlite-shm' \
    -o -iname '*.dump' -o -iname '*.sql' -o -iname '*.bak' \
    -o -iname '*.pem' -o -iname '*.key' -o -iname '*.pfx' -o -iname '*.crt' \
    -o -path './storage' -o -path './storage/*' \
    -o -path './database' -o -path './database/*' \
    -o -path './backups' -o -path './backups/*' \
    -o -name 'auth.json' \) \
    -print
)"

if [[ -n "$bad_paths" ]]; then
  echo "Forbidden files found in Docker release:" >&2
  echo "$bad_paths" >&2
  exit 1
fi

image_tar="$(find "$TARGET/images" -maxdepth 1 -type f -name '*.tar' | head -n 1)"
if [[ -z "$image_tar" ]]; then
  echo "Missing Docker image tar under images/." >&2
  exit 1
fi

checksum_file="$(find "$TARGET/checksums" -maxdepth 1 -type f -name '*.sha256' | head -n 1)"
if [[ -z "$checksum_file" ]]; then
  echo "Missing image checksum file under checksums/." >&2
  exit 1
fi

(
  cd "$TARGET"
  sha256sum -c "checksums/$(basename "$checksum_file")"
)

if grep -R -I -n --exclude='validate-docker-release.sh' -E 'APP_KEY=base64:[A-Za-z0-9+/=]{20,}|PUSHER_APP_SECRET=[^[:space:]]{8,}|LICENSE_PRIVATE_KEY=.+|-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----|github_pat_[A-Za-z0-9_]{20,}|ghp_[A-Za-z0-9]{20,}' "$TARGET" >/tmp/garmentsos-docker-release-secret-scan.txt 2>/dev/null; then
  echo "Potential secret values found in Docker release:" >&2
  cat /tmp/garmentsos-docker-release-secret-scan.txt >&2
  rm -f /tmp/garmentsos-docker-release-secret-scan.txt
  exit 1
fi
rm -f /tmp/garmentsos-docker-release-secret-scan.txt

echo "Docker release validation passed: $TARGET"
