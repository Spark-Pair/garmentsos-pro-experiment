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

Uploaded SQLite restores are started as a private restore job. The browser upload request stores the file under private storage, queues an Artisan restore command, and returns quickly with a status panel. The background job then validates the SQLite file, creates an emergency backup, replaces only the business SQLite database, runs migrations, repairs branch metadata/access, backfills missing business `branch_id` values to Main Branch, clears cache, and records a success/failure status. This avoids Nginx/PHP gateway timeouts during large database restores.

Restore preserves the current installation identity:

- `.env`
- install ID
- license cache
- activation request cache
- device approval state
- update markers and locks

Docker runtime limits for restore uploads are set to support local SQLite files up to 1024 MB:

- Nginx `client_max_body_size=1024m`
- PHP `upload_max_filesize=1024M`
- PHP `post_max_size=1024M`
- PHP/FastCGI restore request timeouts are extended, but the restore work still runs outside the HTTP request.

On every Docker container startup the entrypoint repairs Laravel writable paths before Artisan/PHP-FPM starts. It creates `storage/app/license`, `storage/app/private/backups`, framework cache/session/view paths, logs, and `bootstrap/cache`, then applies `www-data:www-data` ownership and `ug+rwX` permissions without deleting backups, license cache, storage, or the database.

## Updater Relationship

Updater apply creates a verified pre-update backup before copying files or running migrations. Restore does not run automatically as part of updater apply.

## Release Safety

Release/update packages must not contain DB files, WAL/SHM files, dumps, backup files, logs, `.env`, private keys, credentials, or private storage data.
