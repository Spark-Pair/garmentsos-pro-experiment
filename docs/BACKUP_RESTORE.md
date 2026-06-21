# Backup And Restore

## Purpose

Phase 3A added safe backup creation, verification, listing, and permission-protected download for GarmentsOS PRO. Phase 3B adds a guarded restore workflow foundation.

## Production Safety

- Do not delete, overwrite, truncate, or recreate a client database during backup.
- Do not copy or ship `database/database.sqlite`, `*.sqlite-wal`, `*.sqlite-shm`, DB dumps, or backup files in release packages.
- Do not store backups under `public/`.
- Do not expose direct public backup URLs.
- Do not test restore on production first.
- Restore is disabled by default and must be explicitly enabled only after staging/copy testing.

## Private Backup Storage

Managed database backups are stored under:

```text
storage/app/private/backups/database
```

This directory is outside the public web root. Downloads must go through authenticated developer/admin routes.

Each backup has:

- SQLite backup file
- metadata JSON file
- SHA-256 checksum
- `backup_logs` row
- sanitized `audit_logs` row

## SQLite Backup Strategy

SQLite backups use `VACUUM INTO` to ask SQLite to create a consistent standalone database file. This avoids unsafe manual copying of the live database and avoids relying on WAL/SHM sidecar files.

The current implementation:

- creates the backup in a private temp path first
- generates a SHA-256 checksum
- writes metadata
- verifies the backup before marking success
- moves only managed backup files
- never overwrites the current database
- never deletes the current database

## Verification

Verification checks:

- file is inside the controlled private backup directory
- file exists and is a normal file
- SQLite header is valid
- checksum matches the recorded value
- metadata JSON exists and matches the file checksum

Invalid backups are blocked from download.

## Downloads

Downloads are permission-protected and available only to `developer` and `admin` users. The app does not expose a public URL for backup files.

The legacy `/backup-db` endpoint is preserved for existing UI compatibility, but it now creates a managed private backup and downloads that verified file instead of streaming the live database file directly.

## Restore

Restore is guarded and disabled by default:

```text
BACKUP_RESTORE_ENABLED=false
```

When explicitly enabled, restore requires:

- explicit confirmation
- exact typed phrase: `RESTORE BACKUP {backupLogId}`
- staging/copy tested checkbox
- managed `BackupLog` ID only
- no arbitrary filesystem path input
- emergency backup before restore
- emergency backup verification before DB replacement
- selected backup verification before restore
- restore/backup lock to prevent overlap
- SQLite sidecar handling after DB connection is closed
- post-restore validation
- rollback plan
- staging/copy test before production use
- audit logging

Restore is not part of updater flow and must not run automatically.

## Update And Migration Rule

Before update, migration, restore, or cloud migration work, create and verify a backup first. Update packages must never overwrite client databases, uploads, backups, or environment files. Restore must be tested on a copy/staging database before any client production use.
