# Windows GUI Updater

GarmentsOS PRO includes a lightweight Windows launcher:

```text
GarmentsOS-PRO.exe
```

It is a host-side updater. Laravel does not update its own running Docker container.

## What It Shows

- Installed version from `C:\SparkPair\GarmentsOS\manifest.json`
- App status for `http://localhost:8000`
- Docker status
- Update feed URL from installed `.env`
- Latest version and release notes when `latest.json` is reachable

For client installs, use a public feed URL:

```env
UPDATE_FEED_URL=https://sparkpair.dev/api/updates/garmentsos-pro/stable/latest.json
```

Private GitHub release assets return `404` to unauthenticated apps/launchers even if they open in a browser where the developer is logged in.

## Buttons

- `Open App`: opens `http://localhost:8000`
- `Check Update`: reads `UPDATE_FEED_URL` / `latest.json`
- `Update Now`: downloads, verifies, extracts, and applies the package through `windows-docker-update.ps1`
- `Open Request JSON`: loads `garmentsos-update-request.json` created by the in-app `Download Update Request` button
- `Backup`: runs the existing Windows Docker backup script
- `Repair`: runs `docker compose up -d` in the installed folder
- `Stop Services`: stops Docker services through the existing stop launcher when available
- `Open Install Folder`: opens `C:\SparkPair\GarmentsOS`

## Update Safety

`Update Now`:

1. Reads `latest.json` or an in-app update request JSON.
2. Downloads `package_url`.
3. Verifies the downloaded package SHA256 against `package_sha256`.
4. Extracts the package to a temporary folder.
5. Runs the package's `scripts\windows-docker-update.ps1`.

The PowerShell updater preserves Docker volumes, client data, backups, and `.env`. The GUI does not delete client data and does not implement uninstall.

## In-App Handoff

From the Developer Updater page, click `Update Now` when an update is available. The browser opens:

```text
garmentsos://update?request=<encoded signed request URL>&autoStart=1
```

The launcher opens in a dedicated update splash/progress mode, downloads the temporary signed request JSON, validates the required fields, downloads the package, verifies SHA256, creates a backup, runs the host-side Docker updater, and opens the app again when complete. The original web app `Update Now` click is the confirmation for this automatic handoff.

Manual fallback is under `Troubleshooting / Manual update`. Click `Download Update Request` there only if the main `Update Now` button does not open the launcher. This downloads:

```text
garmentsos-update-request.json
```

Open that file in the launcher with `Open Request JSON`, review the version/package details, then click `Update Now`.

Protocol handoff:

```text
garmentsos://update
garmentsos://update?request=<encoded update request URL>
garmentsos://update?request=<encoded update request URL>&autoStart=1
garmentsos://open
```

The Windows install/update scripts register this protocol under HKCU:

```text
HKCU\Software\Classes\garmentsos
  (Default) = URL:GarmentsOS PRO Launcher
  URL Protocol = ""

HKCU\Software\Classes\garmentsos\shell\open\command
  (Default) = "C:\SparkPair\GarmentsOS\GarmentsOS-PRO.exe" "%1"
```

Older installs may still have `GarmentsOS-PRO-Setup.exe` or `GarmentsOS PRO Launcher.exe`, but new installs register `GarmentsOS-PRO.exe`.

When opened with `garmentsos://update`, the launcher opens normally and focuses the update flow. When opened with `garmentsos://update?request=<encoded-url-or-path>`, it attempts to load that request JSON from a local path, `file://` URL, or `http/https` URL. If loading fails, the user can still choose `Open Request JSON`.

When opened with `autoStart=1`, the launcher hides the normal technical buttons and shows only update progress, optional Details, and failure troubleshooting actions. It starts the update automatically after the signed request is loaded and validated. On failure, it keeps the error visible and shows `Open Install Folder`, `Save Log`, and `Close`.

If the running launcher EXE is locked during update, the PowerShell updater stages the new launcher at:

```text
C:\SparkPair\GarmentsOS\updates\GarmentsOS-PRO.exe.pending
```

and writes:

```text
C:\SparkPair\GarmentsOS\.pending-launcher-update.json
```

After the updater exits, a detached helper replaces `C:\SparkPair\GarmentsOS\GarmentsOS-PRO.exe` and re-registers `garmentsos://` under HKCU.

## Developer Build

Build:

```powershell
dotnet build launcher\GarmentsOS.Setup\GarmentsOS.Setup.csproj -c Release
```

Publish a self-contained single-file EXE so client PCs do not need a separate .NET Desktop Runtime install:

```powershell
dotnet publish launcher\GarmentsOS.Setup\GarmentsOS.Setup.csproj -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true -p:EnableCompressionInSingleFile=true -p:IncludeNativeLibrariesForSelfExtract=true
```

The Docker release builder includes the launcher automatically when `GarmentsOS-PRO.exe` exists under the launcher publish/build output. If it is missing, the release still works with BAT/PowerShell fallback launchers.
