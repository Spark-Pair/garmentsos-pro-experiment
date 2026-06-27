# Environment Configuration

## Purpose
This guide explains important `.env` keys for GarmentsOS PRO. The `.env` file is client-specific and must never be committed or shipped with a generic update package.

Recommended local/manual production-mode testing values:

```env
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=warning
LICENSE_ENFORCEMENT_ENABLED=false
BACKUP_RESTORE_ENABLED=false
UPDATER_ENABLED=false
```

## Core App
- `APP_NAME`: display/runtime app name. Use a safe client/app name.
- `APP_ENV`: use `production` for client/manual production-like testing.
- `APP_KEY`: Laravel encryption key. Generate once for fresh install. Never rotate on an existing client casually.
- `APP_DEBUG`: use `false` for client/manual production-like testing.
- `APP_URL`: server URL, usually LAN IP such as `http://192.168.1.10:8000`.

## Logging
- `LOG_LEVEL`: use `warning` for production-like installs. Use lower levels only for temporary local debugging.

## Database
- `DB_CONNECTION`: usually `sqlite`.
- `DB_DATABASE`: absolute path to `database/database.sqlite` or approved client DB path.
- `DB_FOREIGN_KEYS`: use `true` for SQLite foreign key support.

## Session, Cache, Queue
- `SESSION_DRIVER`: usually `file`.
- `SESSION_LIFETIME`: session timeout minutes.
- `SESSION_SECURE_COOKIE`: use `true` only when serving over HTTPS.
- `SESSION_HTTP_ONLY`: should stay `true`.
- `SESSION_SAME_SITE`: usually `lax`.
- `CACHE_DRIVER`: usually `file`.
- `QUEUE_CONNECTION`: usually `sync` for local/LAN installs unless queue workers are configured.

## Pusher
- `PUSHER_ENABLED`: enable only when Pusher is configured.
- `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET`: client/project Pusher credentials.
- `VITE_PUSHER_*`: frontend Pusher settings.

Never commit or publish `PUSHER_APP_SECRET` or other credentials.

## Subscription
- `SUBSCRIPTION_EXPIRE_DATE`: current subscription expiry date.
- `SUBSCRIPTION_MODE`: `demo` or `paid`. Put comments on separate lines, not after the value.

## Company Branding Fallback
These config values are fallbacks when DB branding settings are missing:
- `COMPANY_NAME`
- `COMPANY_OWNER`
- `COMPANY_LOGO`
- `COMPANY_LOGO_TEXT`
- `COMPANY_PHONE`
- `COMPANY_CITY`
- `COMPANY_ADDRESS`
- `COMPANY_LOGO_SVG_PATH`

Do not put HTML, scripts, secrets, private paths, or tokens in branding values.

## Licensing
- `LICENSE_ENFORCEMENT_ENABLED`: default `false`. Do not enable broadly without staging/client-copy approval.
- `LICENSE_SERVER_URL`: optional activation server URL.
- `LICENSE_PUBLIC_KEY`: public verification key only. Never ship a private signing key.
- `LICENSE_OFFLINE_GRACE_DAYS`: offline grace default.
- `INSTALLATION_MODE`: usually `local_lan`.

## Backup And Restore
- `BACKUP_RESTORE_ENABLED`: default `false`. Restore is dangerous and must stay disabled unless explicitly approved.

## Updater
- `UPDATER_ENABLED`: default `false`.
- `UPDATER_MANIFEST_URL`: optional manifest URL for future verification/checking.
- `UPDATER_PUBLIC_KEY`: public update verification key only.
- `UPDATER_CHANNEL`: update channel such as `stable`.
- `UPDATER_REQUIRE_SIGNATURE`: should stay `true` when updater checks are enabled.

Phase 4A does not implement apply/install. Do not add private signing keys to client PCs.

## After Environment Changes
Run:

```bash
php artisan config:clear
php artisan cache:clear
php artisan config:cache
```

For local troubleshooting, `config:cache` can be skipped until values are stable.

## Secret Safety
- Never commit `.env`.
- Never commit `APP_KEY`, passwords, tokens, Pusher secrets, private signing keys, DB files, dumps, or backups.
- If secrets were exposed outside the trusted machine/team, rotate them using the appropriate provider/process.
