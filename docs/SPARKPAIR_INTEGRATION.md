# SparkPair Integration Contract

GarmentsOS PRO is the local client app. SparkPair Website/Admin Panel is the source of truth for customers, licenses, release metadata, and the public update feed.

## Product

- Product name: `GarmentsOS PRO`
- Product key: `garmentsos-pro`

## Update Feed

Installed clients should use:

```env
UPDATE_FEED_URL=https://sparkpair.dev/api/updates/garmentsos-pro/stable/latest.json
UPDATE_CHANNEL=stable
UPDATE_LAUNCHER_PROTOCOL=garmentsos
UPDATE_REQUEST_TTL_MINUTES=10
UPDATE_LOCK_TTL_MINUTES=30
```

The GitHub experiment channel feed remains a fallback for developer/testing recovery:

```env
UPDATE_FALLBACK_FEED_URL=https://github.com/Spark-Pair/garmentsos-pro-experiment/releases/download/latest-stable/latest.json
```

Feed priority in the local app:

1. Installed `.env` `UPDATE_FEED_URL`
2. `config/updater.php` feed URL default
3. Experiment GitHub fallback feed

The SparkPair `latest.json` endpoint must be public and must not require login. Private GitHub release assets can return `404` to unauthenticated clients, so installed clients should use the SparkPair feed URL.

## Feed JSON

Minimum required fields:

```json
{
  "app": "garmentsos-pro",
  "version": "1.8.40",
  "package_url": "https://github.com/Spark-Pair/garmentsos-pro-experiment/releases/download/v1.8.40/garmentsos-pro-1.8.40.zip",
  "package_sha256": "..."
}
```

Optional fields displayed by the updater UI:

```json
{
  "channel": "stable",
  "mandatory": false,
  "released_at": "2026-07-03T00:00:00Z",
  "package_file": "garmentsos-pro-1.8.40.zip",
  "setup_url": "https://github.com/Spark-Pair/garmentsos-pro-experiment/releases/download/v1.8.40/GarmentsOS-PRO.exe",
  "notes": "..."
}
```

## Release Sync

The GitHub release workflow can optionally notify SparkPair after assets are published:

```http
POST https://sparkpair.dev/api/admin/releases/sync-github
Authorization: Bearer <RELEASE_WEBHOOK_SECRET>
Content-Type: application/json
```

Configure these GitHub Actions secrets:

```text
SPARKPAIR_RELEASE_SYNC_URL=https://sparkpair.dev/api/admin/releases/sync-github
SPARKPAIR_RELEASE_WEBHOOK_SECRET=<secret>
```

If either secret is missing, release sync is skipped with a warning. If sync fails, the workflow warns but does not fail the GitHub release while the SparkPair site is stabilizing.

## License Verify

Installed clients should use:

```env
LICENSE_ENABLED=false
LICENSE_CLIENT_ID=
LICENSE_CLIENT_NAME=
LICENSE_KEY=
LICENSE_CHECK_URL=https://sparkpair.dev/api/licenses/verify
LICENSE_GRACE_DAYS=7
```

`LICENSE_ENABLED=false` keeps local/dev builds allowed. Production/client installs can enable licensing after a customer and license are created in the SparkPair admin panel.

Verify request:

```json
{
  "product": "garmentsos-pro",
  "client_id": "abc-garments",
  "license_key": "GOS-XXXX-XXXX-XXXX-XXXX",
  "install_id": "local-random-install-id",
  "app_version": "1.8.40"
}
```

The local app persists `install_id` at:

```text
storage/app/install-id.txt
```

Successful active verify responses are cached locally. If SparkPair is unreachable later, the app uses the cached status and grace window instead of immediately blocking. Expired or invalid licenses enter read-only mode. Updater routes remain available so support can recover/update the installation.

The local app must never log or display the full `LICENSE_KEY`; developer pages show a masked key only.
