# Windows Docker Update

Use this for existing Docker client installs.

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
- updates compose/scripts/docs
- updates `GARMENTSOS_IMAGE` in `.env`
- preserves Docker volumes
- starts the new container
- runs migrations through the entrypoint only after backup

## Rollback

Load the previous image tar, set `GARMENTSOS_IMAGE` in `.env` to the previous tag, then run:

```powershell
docker compose up -d
```

Restore the database only when required and only from a verified backup.
