# Client Installation

## Purpose

Install GarmentsOS PRO on a new client/server PC without exposing developer secrets or damaging client data.

## Supported Modes

- Single PC: app, PHP runtime, and SQLite database run on one machine.
- LAN server PC: one PC hosts the app/database and office PCs use browsers.
- Docker LAN server: optional containerized PHP/Nginx app with host-mounted `.env`, database, and storage.

LAN browser PCs are not separate licensed devices. The server/app installation is licensed.

## Fresh Install With Script

Use a reviewed release package. Never copy a developer `.env`, database, logs, backups, private keys, or tokens to the client PC.

```bash
./scripts/client-install.sh
```

The script:

- copies `.env.example` to `.env` only when `.env` is missing
- creates the SQLite DB only when missing
- installs Composer dependencies without dev packages
- generates `APP_KEY` only for a fresh install with an empty key
- runs migrations
- clears/caches config
- creates a first backup when possible

LAN test command:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

## Manual Fresh Install

1. Create `.env` from `.env.example`.
2. Set `APP_ENV=production`, `APP_DEBUG=false`, and a client-specific `APP_URL`.
3. Set SQLite:

```env
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/database.sqlite
DB_FOREIGN_KEYS=true
```

4. Create the DB file only if this is a fresh install.
5. Run:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
```

Never change `APP_KEY` on an existing client install unless the impact is reviewed and a verified backup exists.

## Existing Client Update

```bash
./scripts/client-update.sh /path/to/garmentsos-pro-1.8.0.zip
```

The update script refuses to run without an existing `.env` and DB file, validates the release, creates a backup before copying files, preserves client data, runs Composer, then runs migrations after backup.

## Data Safety

- Never overwrite an existing client database during update.
- Never overwrite client `.env`, backups, logs, uploads, private storage, or license identity/cache.
- Never commit or ship `.env`, `APP_KEY`, credentials, DB files, backups, dumps, logs, private keys, or tokens.
- Run migrations only after backup and approval.

## Smoke Checklist

- Login works.
- Dashboard opens.
- Orders, invoices, payments, stock-related pages, and reports open.
- Backup create/verify works.
- License page shows server-installation wording.
- LAN browser access works from another PC.
