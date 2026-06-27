# Release Checklist

Use this before publishing or applying a GarmentsOS PRO release/update.

## Git And Review
- Current branch is correct.
- Working tree is clean.
- Intended commits only.
- Code review complete.
- No merge to `main` without explicit approval.

## Tests
- `git diff --check` passes.
- Targeted tests pass.
- `php artisan test` passes where available.
- Browser smoke test passes.

## Backup
- Client database backup created.
- Backup verified by checksum/SQLite validation.
- Backup location recorded.
- Rollback plan ready.

## Migrations
- Migrations reviewed.
- `php artisan migrate --pretend` reviewed.
- Additive-only migrations preferred.
- Real migration runs only after backup and approval.

## Package Safety
- No `.env` in commit/package.
- No DB files, WAL/SHM, dumps, backups, logs, credentials, private keys, tokens, APP_KEY, or secrets.
- No private GitHub/repo/update/license secrets.
- Client `.env`, DB, uploads, private storage, and backups are preserved.

## Feature Flags And Dangerous Modes
- `LICENSE_ENFORCEMENT_ENABLED=false` unless explicitly approved.
- `BACKUP_RESTORE_ENABLED=false` unless explicitly approved for a staged restore.
- `UPDATER_ENABLED=false` unless explicitly approved.
- Updater apply/install is not included unless a separate approved phase implements it.

## Browser Smoke Test
- Login/logout.
- Home/dashboard centered and not clipped.
- Developer Settings page top/bottom not clipped.
- License page.
- Backup page.
- Updater disabled/check page.
- Restore disabled/details page if route exists.
- Orders, invoices, payments, vouchers, reports, stock-related pages.
- Print preview.
- LAN browser access when applicable.

## Client-Specific Notes
- Branding/settings/module differences documented.
- Client-specific changes use DB settings or a separate branch/channel where possible.
- Support notes include release package name, date, backup file, and rollback steps.
