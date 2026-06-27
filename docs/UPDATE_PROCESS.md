# Update Process

## Purpose
This guide explains how code updates are prepared, reviewed, packaged, and applied safely for GarmentsOS PRO.

## Branch Workflow
1. Work in a feature branch.
2. Review and test the feature branch.
3. Push the feature branch only after review.
4. Combine related reviewed branches through an integration branch when needed.
5. Test on staging or a client-copy database.
6. Merge to `main` only after explicit approval.
7. Build a release/update package from approved code.

Do not merge directly into `main`, push to `main`, or deploy unreviewed local changes.

## Update Types

### Global Update
Applies to all clients and belongs in common code.

Examples:
- bug fix
- safe UI/layout improvement
- security hardening
- licensing/backup/updater foundation

### Client-Specific Update
Prefer DB settings, modules, labels, branding, and license/module restrictions when possible.

Use a separate branch/channel if code truly differs. Avoid hardcoding one client's behavior into global code.

### Emergency Hotfix
1. Branch from current production `main`.
2. Make the smallest possible fix.
3. Back up before deploy.
4. Test on staging/client-copy.
5. Apply only after approval.

## Update Checklist
1. Confirm Git branch/status is clean.
2. Create and verify a database backup.
3. Confirm update package excludes `.env`, DB files, backups, logs, Git metadata, private keys, and secrets.
4. Preserve client `.env`, DB, uploads, private storage, and backups.
5. Replace application files/build assets only.
6. Run:

```bash
composer install --no-dev --optimize-autoloader
```

7. Build assets only if needed:

```bash
npm install
npm run build
```

8. Review migrations:

```bash
php artisan migrate --pretend
```

9. Run real migrations only after backup and approval:

```bash
php artisan migrate
```

10. Clear/cache config:

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
```

11. Run tests if environment allows.
12. Browser smoke test login, dashboard, orders, invoices, payments, reports, backups, updater disabled state, and print preview.

## Never Overwrite During Update
- `.env`
- `database/database.sqlite`
- `*.sqlite-wal`
- `*.sqlite-shm`
- DB dumps
- backups
- private storage/client uploads
- logs when needed for troubleshooting
- private keys, tokens, credentials

## Release Package Rules
Include:
- application code
- `vendor/` for offline installs when needed
- built assets under `public/build`
- safe public assets
- storage skeleton directories

Exclude:
- `.git/`, `.github/` internal workflows
- `.env` and real env variants
- database files, WAL/SHM, dumps, backups
- logs
- `storage/app/private/*`
- private keys/signing keys/tokens/credentials
- `node_modules/`
- tests and internal docs unless approved
- source maps if they expose source

## Rollback
- Restore previous code version first.
- Restore DB only if migration/data changes require it and only after careful confirmation.
- Keep the pre-update backup and release package record.
- Never test rollback for the first time on a production/client DB.
