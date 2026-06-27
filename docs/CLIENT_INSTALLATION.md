# Client Installation Guide

## Purpose
This guide explains how to install GarmentsOS PRO on a new client/server PC without exposing developer secrets or damaging client data.

Use this with `docs/RELEASE_PACKAGING.md`, `docs/ENVIRONMENT.md`, and `docs/BACKUP_RESTORE.md`.

## Supported Local Modes
- Single PC: app, PHP runtime, and SQLite database run on one machine.
- LAN server PC: one PC hosts the app/database and other office PCs use a browser.
- Future cloud/VPS: deploy with a managed database and stricter backup/deployment process.

## Prerequisites
- PHP 8.1+ with SQLite/PDO SQLite extensions enabled.
- Composer.
- Node.js/npm only if assets must be built on the client/server machine.
- SQLite file access permission for the app user.
- A local web server or Laravel dev server for LAN use.

## Fresh Install Steps
1. Copy the reviewed release package to the client/server PC.
2. Confirm the package does not contain `.git/`, `.env`, database files, backups, logs, private keys, tokens, or developer-only files.
3. Create `.env` from `.env.example`.
4. Set production-safe values:

```env
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=warning
```

5. Set `APP_URL` to the server PC URL, for example `http://192.168.1.10:8000`.
6. For a fresh install only, generate a new key:

```bash
php artisan key:generate
```

Never change `APP_KEY` on an existing client install unless you understand encrypted data/session impact and have a verified backup.

7. Create the SQLite database file for a fresh install:

```bash
touch database/database.sqlite
```

Windows PowerShell equivalent:

```powershell
New-Item -ItemType File database\database.sqlite
```

8. Point `.env` at the DB file:

```env
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/database.sqlite
DB_FOREIGN_KEYS=true
```

9. Install PHP dependencies:

```bash
composer install --no-dev --optimize-autoloader
```

10. If assets are not already built in the package:

```bash
npm install
npm run build
```

11. Run migrations only on a new empty DB:

```bash
php artisan migrate
```

12. Prepare Laravel caches/storage:

```bash
php artisan storage:link
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
```

13. Start on LAN if using Artisan:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

14. If the project has a Windows launcher/service/batch file in the approved package, configure it to start the same command/path. Do not ship developer machine paths.

## First Login
Use the existing seeded/user setup process for this project. If no admin user exists, create it through the approved seed/install process only.

## After Install Checklist
- Login works.
- Dashboard/home is centered and not clipped.
- Orders, invoices, payments, stock-related pages, and reports open.
- Print preview works on key documents.
- A managed backup can be created and verified.
- LAN browser access works from another PC.
- `.env`, DB, backups, and uploads are not in Git/package output.

## Update Checklist
- Confirm the current install path and database path.
- Create and verify a backup before replacing app files.
- Preserve `.env`, database files, uploads, private storage, and existing backups.
- Replace only application code/build assets from the update package.
- Run `composer install --no-dev --optimize-autoloader`.
- Build assets only if the package does not include `public/build`.
- Run `php artisan migrate --pretend` before any real migration.
- Run real migrations only after backup and explicit approval.
- Clear/cache config after `.env` or config changes.
- Browser smoke test login, dashboard, orders/invoices/payments, reports, backups, and print preview.

## LAN Notes
- Host the app on one server PC. LAN PCs should not each run separate copied app folders if they share data.
- Browser PCs are not separately licensed; the server/app installation is licensed.
- Restrict firewall/network access to trusted LAN users.
- For heavier concurrent office use, plan PostgreSQL/cloud migration separately.

## Data Safety Rules
- Never overwrite an existing client database during update.
- Never delete client backups/logs/uploads during cleanup.
- Never run destructive database commands on a client database without a verified backup and explicit approval.
- Never commit or ship `.env`, `APP_KEY`, credentials, DB files, backups, dumps, logs, private keys, or tokens.
