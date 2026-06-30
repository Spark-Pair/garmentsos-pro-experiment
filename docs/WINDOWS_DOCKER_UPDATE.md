# Windows Docker Update

Use this for existing Docker client installs.

Scripts support Windows PowerShell 5.1 or newer. For the simplest run, extract the new release package and double-click the root `Update GarmentsOS.bat` launcher.

The old `1.8.0` Docker release built before the Windows PowerShell compatibility fix should be treated as invalid/replaced.

## Update

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
- updates `GARMENTSOS_IMAGE` in `.env`
- preserves Docker volumes
- starts the new container
- runs migrations through the entrypoint only after backup
- hides technical files with the Windows hidden attribute by default

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
