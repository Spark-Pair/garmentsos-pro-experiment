# Update Process

GarmentsOS PRO supports two controlled update paths:

1. Scripted update using a validated release package.
2. Developer/admin updater UI using signed manifest and package verification.

Both paths must preserve client `.env`, database files, backups, logs, private storage, and license identity/cache.

## Branch Workflow

1. Work in a feature branch.
2. Push experiment work only to the `experiment` remote when approved.
3. Do not push experiment branches to stable `origin`.
4. Test on staging or a client-copy database.
5. Merge to `main` only after explicit approval.
6. Build a release package from approved code.

## Scripted Update

```bash
./scripts/client-update.sh releases/garmentsos-pro-1.8.0.zip
```

The script requires an existing `.env` and DB file, validates the package, creates a pre-update backup, copies safe code paths, runs Composer, runs migrations after backup, clears caches, and prints a smoke checklist.

## Updater UI

The updater is developer/admin only. When `UPDATER_ENABLED=false`, it does not fetch, download, verify, or apply anything.

When enabled and configured, apply flow:

1. Verify signed manifest.
2. Download package to private updater storage.
3. Verify checksum and package signature.
4. Reject forbidden files and unsafe paths.
5. Create verified DB backup.
6. Extract package into private staging.
7. Snapshot overwritten code files.
8. Copy only allowed runtime paths.
9. Run migrations only if manifest requires it and `UPDATER_RUN_MIGRATIONS=true`.
10. Log result and keep backup/snapshot for manual rollback.

## Never Overwrite

- `.env`
- `database/database.sqlite`
- `*.sqlite-wal`
- `*.sqlite-shm`
- DB dumps
- backups
- private storage/client uploads
- logs
- private keys, tokens, credentials
- license identity/cache

## Rollback

- Restore previous code from the snapshot or previous release package.
- Restore DB only when required and only from a verified backup.
- Do not automatically restore DB after migration failure.
- Never test rollback for the first time on a production/client DB.
