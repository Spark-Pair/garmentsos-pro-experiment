# Backup And Restore

## Backup

Backups are developer/admin only, stored in private storage, checksum verified, and download protected.

Managed SQLite backups are stored under:

```text
storage/app/private/backups/database
```

Backups use `VACUUM INTO` to create a consistent standalone SQLite file instead of copying live DB/WAL/SHM files.

Each successful backup has:

- SQLite backup file
- metadata JSON file
- SHA-256 checksum
- `backup_logs` row
- sanitized `audit_logs` row

## Restore

Restore is guarded and disabled by default:

```env
BACKUP_RESTORE_ENABLED=false
```

When enabled, restore requires:

- exact typed confirmation
- staging/copy-tested confirmation
- managed `BackupLog` ID only
- selected backup verification
- emergency backup before DB replacement
- shared backup/restore lock
- post-restore validation
- rollback attempt if validation fails

No route accepts arbitrary filesystem paths. Restore must be tested on a staging/client-copy database before production use.

## Updater Relationship

Updater apply creates a verified pre-update backup before copying files or running migrations. Restore does not run automatically as part of updater apply.

## Release Safety

Release/update packages must not contain DB files, WAL/SHM files, dumps, backup files, logs, `.env`, private keys, credentials, or private storage data.
