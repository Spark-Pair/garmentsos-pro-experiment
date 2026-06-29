# QA Checklist

## Automated

```bash
git diff --check
php artisan test
php artisan migrate --pretend --force
bash scripts/build-release.sh 0.0.0-test --allow-dirty
bash scripts/validate-release.sh releases/garmentsos-pro-0.0.0-test
bash scripts/docker-build-release.sh 0.0.0-docker-test --allow-dirty
bash scripts/validate-docker-release.sh docker-releases/garmentsos-pro-0.0.0-docker-test
docker compose config
docker build -t garmentsos-pro:test .
```

Docker commands require Docker to be installed.

Windows Docker scripts:

- Run on Windows PowerShell 5.1 or newer.
- `install.bat` and `update.bat` use `-ExecutionPolicy Bypass -NoProfile`.
- Confirm no script uses `RandomNumberGenerator::Fill`, PowerShell 7 ternary/null-coalescing operators, or `ForEach-Object -Parallel`.
- Treat Docker release `1.8.0` from before the PowerShell compatibility fix as invalid/replaced.

## Release Package

- Package excludes `.git`, `.github`, real `.env` variants, DB files, WAL/SHM, backups, dumps, logs, private storage data, private keys, tokens, credentials, tests, and developer artifacts.
- Package includes runtime files, migrations, safe public assets, docs, scripts, `.env.example`, and runtime directory skeletons.
- Generated release artifacts are ignored and not committed.

## Licensing

- Disabled enforcement preserves current behavior.
- Enabled no-license blocks on staging.
- Expired subscription maps to read-only.
- Tampered/fingerprint mismatch maps to blocked.
- LAN browser PCs are not separate license devices.
- Raw license keys and raw machine identifiers are not stored or logged.

## Backup/Restore

- Backup create/verify/download works for developer/admin only.
- Restore disabled refuses safely.
- Enabled restore requires confirmation and staging checkbox.
- Emergency backup is created before DB replacement.
- Arbitrary paths and traversal are rejected.

## Updater

- Disabled updater does not fetch/download/apply.
- Manifest signature, expiry, app, channel, and installation mode are validated.
- Package checksum/signature and deny-list are validated.
- Apply creates backup before copying files.
- Apply preserves `.env`, DB, backups, logs, private storage, and license identity/cache.
- Unauthorized users are blocked.

## Business Smoke

- Login, dashboard, articles, customers, suppliers, rates, reports.
- Orders, invoices, payments, vouchers, stock-related pages.
- Print preview still works.
- No print templates or JS print builders changed unless explicitly approved.
