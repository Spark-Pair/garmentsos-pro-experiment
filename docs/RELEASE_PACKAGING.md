# Release Packaging Guide

## Purpose
This guide defines how to prepare a safe GarmentsOS PRO release package for a client PC or LAN server PC without exposing development secrets, GitHub details, private files, or client data.

Phase 1 is documentation only. It does not implement licensing, updater, feature flags, multi-brand data separation, or cloud migration tooling.

## Production Safety Warning
- This app may already be running with real client data.
- Never delete, reset, truncate, recreate, or overwrite production/client data during packaging.
- Never overwrite an existing client database during an update.
- Always create and verify a backup before replacing application files.
- Treat every client machine as production unless you have written confirmation that it is a fresh test install.

## Never Ship To Client PCs
Do not include these files or folders in any client release package:

- `.git/`
- `.github/` if private/internal workflows exist
- `.env`
- real environment variants such as `.env.backup`, `.env.production`, `.env.local`
- `auth.json`
- GitHub tokens, credentials, personal access tokens, deploy keys, or repository URLs that expose private repo details
- private keys, PEM files, SSH keys, signing private keys, update signing private keys
- `database/database.sqlite`
- `*.sqlite-shm`
- `*.sqlite-wal`
- DB dumps, exported databases, backup archives, or backup folders
- `storage/logs/*`
- `storage/app/private/*`
- any generated backup folder under `storage/`, project root, desktop, or support paths
- `node_modules/`
- `tests/`
- `.phpunit.result.cache`
- `phpunit.xml` if it is not needed in the client package
- internal/private docs, notes, credentials, support logs, screenshots, or implementation plans
- `scripts/` unless a specific script is intended for client support and has been reviewed
- `public/hot`
- Vite dev artifacts
- source maps if they expose source
- developer machine paths/details, local usernames, IDE files, or personal machine configuration

## Safe Or Required In Client Package
A client package may include:

- Laravel runtime files required to run the app
- `vendor/` for offline installs
- built frontend assets under `public/build`
- safe public assets under `public/images`, fonts, icons, `manifest.json`, `service-worker.js`, and similar public runtime assets
- storage skeleton directories only, not logs, private files, backups, or uploaded private data unless explicitly migrating a client
- a generated client `.env` with no developer secrets
- an empty or installer-created SQLite database only for a fresh installation
- future license/update public verification key only, never the private signing key

## Recommended Client Release Structure
Use a clean staging folder outside the Git working tree:

```text
GarmentsOS-PRO/
  app/
  bootstrap/
  config/
  database/
    migrations/
    seeders/              # only if needed for fresh install/support
  public/
    build/
    images/
    js/
    index.php
  resources/
  routes/
  storage/
    app/
    framework/
      cache/
      sessions/
      views/
    logs/                 # empty directory only
  vendor/
  artisan
  composer.json
  composer.lock
  package-manifest.txt    # optional generated file list/checksums
  .env                    # generated per client, not copied from developer machine
```

Do not copy the entire repository as-is. Build a release from an allowlist or from a clean export process.

## Environment Handling
- Generate a new `.env` for each client install.
- Never copy the developer `.env`.
- Use `APP_ENV=production` and `APP_DEBUG=false`.
- Generate a client-specific `APP_KEY` for a fresh install.
- For an update, preserve the existing client `.env`; do not replace it.
- Do not store GitHub URLs, GitHub tokens, private repo credentials, signing private keys, or developer credentials in `.env`.
- Public verification keys may be shipped in a future licensing/updater phase, but private signing keys must remain only on the developer/release server.

## Database Handling
- Fresh install: create an empty SQLite database through the installer or documented setup command.
- Existing install/update: keep the existing client database exactly where it is.
- Never include the developer `database/database.sqlite` in a release package.
- Never include SQLite WAL/SHM files from a developer or client machine.
- Never overwrite client `database/database.sqlite` during an update.
- For client data migration, use a separate migration/backup procedure with written steps, backups, and verification.

## Backup Before Update Rule
Before any update:

1. Confirm the app path and database path.
2. Put the app in a safe maintenance state if needed.
3. Create a database backup.
4. Verify the backup file exists and has a realistic size.
5. Record the current app version/package name.
6. Apply application file changes.
7. Run required non-destructive Laravel commands.
8. Verify login, core pages, reports, and current client data.

For SQLite, prefer a safe backup strategy such as SQLite backup APIs or `VACUUM INTO` where appropriate. Do not copy a database file while writes may be active unless the chosen backup method is safe for that environment.

## Update Package Rules
- Update packages must contain application files only.
- Update packages must never contain or overwrite client DB files.
- Update packages must never contain or overwrite client `.env`.
- Update packages must never contain private signing keys or GitHub credentials.
- Update packages must preserve `storage/app`, client uploads, backups, and logs unless a documented support task says otherwise.
- Update packages should be signed or checksummed in a future updater phase.

## Git And GitHub Secrecy Rules
- Client PCs must not contain `.git/`.
- Client PCs must not contain Git remotes.
- Client PCs must not contain private GitHub organization/user IDs, tokens, deploy keys, or workflow secrets.
- Do not run `git pull` on client PCs from the private repository.
- Prefer a private repository plus a release package produced on the developer/release machine.
- Future update delivery should use a release server/proxy or signed public manifest that does not reveal private repository access.

## Secrets Handling Rules
- No production secrets in source code.
- No secrets in Blade, JavaScript, public assets, docs, screenshots, or logs.
- No private signing keys in client packages.
- Rotate any secret suspected to have been copied to a client PC.
- Keep only minimum client-local credentials needed for that installation.

## Build And Package Checklist
- Confirm working tree is clean or intentionally tagged for release.
- Build assets with `npm run build`.
- Install production dependencies with Composer using optimized autoload.
- Remove or exclude dev-only files listed in "Never Ship To Client PCs".
- Ensure `public/hot` is absent.
- Ensure no source maps are included if they expose source.
- Ensure `.env` is generated for the target client or omitted for installer generation.
- Ensure no database, WAL, SHM, dump, or backup file is included.
- Ensure storage contains only required empty skeleton directories.
- Create package manifest or checksum list.
- Scan package for secrets, tokens, private keys, and developer paths.
- Test the package in a clean staging folder before delivery.

## Client Install Checklist
- Confirm whether this is a fresh install or update.
- For fresh install, generate client `.env` and app key.
- For fresh install, create an empty database or run documented setup.
- For update, back up database first and preserve existing `.env`, database, uploads, and storage data.
- Confirm file permissions for `storage/` and `bootstrap/cache/`.
- Run Laravel cache commands only after `.env` is correct.
- Verify login, dashboard, sidebar, key CRUD pages, reports, backup button, and read-only behavior.
- Record installed package version/date and backup location.

## Rollback And Safety Notes
- Keep the previous application package until the update is verified.
- Keep the pre-update DB backup in a restricted backup location.
- If an update fails before migrations or data changes, restore previous app files and keep the database untouched.
- If a future phase introduces database migrations, rollback must be documented per release and tested before client rollout.
- Do not improvise destructive repair commands on client data.

## Future Phase Notes
- Licensing will add machine-bound signed license files, offline grace, and server-side license enforcement.
- Updater will add signed manifest checks, checksum/signature verification, update logs, and safer apply/rollback flow.
- Multi-brand will add business groups, brands, brand access control, data backfill, and report scoping.
- Those future phases must keep this packaging rule: no update package overwrites client database or secrets.
