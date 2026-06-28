# Release Checklist

## Git

- Current branch is correct.
- Working tree is clean.
- No merge to `main` without explicit approval.
- No push to stable `origin` for experiment work.

## Tests

- `git diff --check`
- `php artisan test`
- `php artisan migrate --pretend --force`
- release build and validation
- Docker config/build where Docker is available

## Package Safety

- No `.env`.
- No DB files, WAL/SHM, dumps, backups, logs, credentials, private keys, tokens, APP_KEY, or secrets.
- No private GitHub/repo/update/license secrets.
- Client `.env`, DB, uploads, private storage, license identity/cache, and backups are preserved.
- Generated `releases/` files are not committed.

## Update Safety

- Verified backup exists before migrations/update apply.
- Manifest/package signatures and checksums are verified.
- Package deny-list passes.
- Migrations run only after backup and approval.
- Rollback plan is ready.

## Dangerous Modes

- `LICENSE_ENFORCEMENT_ENABLED=false` unless explicitly approved.
- `BACKUP_RESTORE_ENABLED=false` unless explicitly approved for staged restore.
- `UPDATER_ENABLED=false` unless explicitly approved for controlled updater use.

## Browser Smoke

- Login/logout.
- Home/dashboard.
- Developer settings.
- License page.
- Backup page.
- Updater page.
- Orders, invoices, payments, vouchers, reports, and stock-related pages.
- Print preview.
- LAN browser access when applicable.
