# Environment

Production/client `.env` files are never committed and never shipped inside release packages or Docker images.

Important safe defaults:

```env
LICENSE_ENFORCEMENT_ENABLED=false
BACKUP_RESTORE_ENABLED=false
UPDATER_ENABLED=false
UPDATER_REQUIRE_SIGNATURE=true
UPDATER_RUN_MIGRATIONS=true
UPDATER_MAINTENANCE_MODE=true
```

Enable dangerous capabilities only for controlled admin operations after a verified backup.

## Secrets

Never store private signing keys, GitHub tokens, APP_KEY values from another install, DB passwords, license private keys, update signing private keys, or client credentials in docs, packages, settings, source control, or release artifacts.

## Docker

Docker mounts `.env`, `database/`, and `storage/` from the host. The image must not bake client `.env` or database files.
