# Client Installation Guide

## Purpose
This guide describes safe client-side installation and update handling for GarmentsOS PRO. It complements `docs/RELEASE_PACKAGING.md`.

## Supported Local Modes
- Single local PC: app, PHP runtime, and SQLite database run on one machine.
- LAN server PC: one PC hosts the app and database; other users access it through a browser on the local network.
- Future cloud hosted app: app and database run on a hosted server with managed backup and deployment procedures.

## Fresh Install Checklist
- Use a reviewed release package, not a Git clone.
- Confirm the package does not contain `.git/`, developer `.env`, developer database files, logs, backups, or private docs.
- Generate a client-specific `.env`.
- Generate a fresh `APP_KEY`.
- Create an empty SQLite database or use the approved installer-created database.
- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Confirm `storage/` and `bootstrap/cache/` are writable.
- Run required Laravel setup/cache commands after the environment is correct.
- Verify login, dashboard, core pages, reports, backup access, and read-only behavior.

## Update Checklist
- Confirm the current client installation path.
- Confirm the current database path.
- Back up the database before replacing app files.
- Verify the backup file exists and has a realistic size.
- Preserve the client `.env`.
- Preserve the client database and SQLite sidecar files.
- Preserve uploads and client storage data.
- Replace only application files from the update package.
- Run required non-destructive Laravel commands.
- Verify login, reports, invoices/payments/vouchers/orders pages, and backup access.
- Record update date, package name, and backup location.

## LAN Deployment Notes
- Keep the app on one server PC and let other PCs access it through the browser.
- Do not copy the app folder independently to each workstation if they should share the same data.
- Ensure only the server PC writes to the SQLite database.
- For heavier concurrent office use, plan a PostgreSQL migration using the documented migration process.
- Restrict network/firewall access to trusted LAN users only.

## Client Data Safety Rules
- Never overwrite an existing client database during an update.
- Never copy the developer database onto a client machine except as a deliberate fresh demo/test install.
- Never delete client backups, logs, or uploads during packaging cleanup.
- Never use destructive database commands on a production/client database without a verified backup and explicit approval.

## Troubleshooting Notes
- If the app fails after an update, first preserve the current folder and database.
- Check `.env`, PHP extensions, file permissions, and Laravel logs.
- If rollback is needed, restore previous app files and keep the database backup available.
- Do not run Git commands on client PCs as a deployment method.
