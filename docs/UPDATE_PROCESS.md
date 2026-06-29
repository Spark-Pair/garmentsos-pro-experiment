# Update Process

Primary client updates are Docker image updates.

## Docker Update

1. Receive the new Docker release zip.
2. Run:

```powershell
cd C:\SparkPair\GarmentsOS
.\scripts\windows-docker-update.ps1 -ReleaseDir C:\Path\To\NewRelease
```

The script loads the new image, creates a backup first, preserves Docker volumes, updates `GARMENTSOS_IMAGE`, starts the new container, and runs migrations through the entrypoint only when configured.

## Preserved During Update

- `.env`
- Docker database volume
- Docker storage volume
- backups
- logs
- license identity/cache

## Rollback

Load the previous image tar, set `GARMENTSOS_IMAGE` in `.env` to the old tag, then run:

```powershell
docker compose up -d
```

Restore DB only from a verified backup and only when needed.

## Developer/Emergency Source Update

`scripts/client-update.sh` remains available for non-Docker emergency/developer scenarios. It is not the primary client update path.
