# Windows Docker Update

Use this for existing Docker client installs.

Scripts support Windows PowerShell 5.1 or newer. For normal users, prefer the GUI bootstrapper at `C:\SparkPair\GarmentsOS\GarmentsOS-PRO.exe`. The BAT/PowerShell scripts remain fallback and automation entrypoints.

The old `1.8.0` Docker release built before the Windows PowerShell compatibility fix should be treated as invalid/replaced.

## Update

GUI flow:

1. Open `C:\SparkPair\GarmentsOS\GarmentsOS-PRO.exe`.
2. Click `Check Update`.
3. Click `Update Now`.

In-app handoff flow:

1. In GarmentsOS PRO, open Developer Updater.
2. Click `Update Now`.
3. Allow Windows to open GarmentsOS PRO Updater if prompted.
4. The updater splash downloads, verifies, backs up, applies the update, and opens the app again.

Fallback script flow:

1. Extract the new Docker release zip.
2. Run:

```powershell
cd C:\SparkPair\GarmentsOS
.\scripts\windows-docker-update.ps1 -ReleaseDir C:\Path\To\NewRelease
```

The updater:

- checks Docker is running
- creates a backup first
- loads the new image tar
- updates compose/scripts/docs/images/checksums and friendly root launchers
- stages the GUI launcher EXE if it is locked by the running updater
- updates `GARMENTSOS_IMAGE` in `.env`
- preserves Docker volumes
- starts the new container
- runs migrations through the entrypoint only after backup
- hides technical files with the Windows hidden attribute by default

The GUI updater performs the download and SHA256 verification before delegating to this same PowerShell update script. Laravel never runs Docker update commands directly.

If the updater cannot overwrite the running GUI launcher, it writes:

```text
C:\SparkPair\GarmentsOS\updates\GarmentsOS-PRO.exe.pending
C:\SparkPair\GarmentsOS\.pending-launcher-update.json
```

The GUI updater starts a detached helper that waits for the updater process to exit, replaces the launcher EXE, and re-registers the HKCU `garmentsos://` protocol.

For developer testing, keep technical files visible:

```powershell
.\scripts\windows-docker-update.ps1 -ReleaseDir C:\Path\To\NewRelease -HideTechnicalFiles:$false
```

## Rollback

Load the previous image tar, set `GARMENTSOS_IMAGE` in `.env` to the previous tag, then run:

```powershell
docker compose up -d
```

Restore the database only when required and only from a verified backup.
