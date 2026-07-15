# SparkPair Integration Contract

GarmentsOS PRO is the local client app. SparkPair Website/Admin Panel is the source of truth for customers, licenses, release metadata, and the public update feed.

## Product

- Product name: `GarmentsOS PRO`
- Product key: `garmentsos-pro`

## Update Feed

Installed clients should use:

```env
UPDATE_FEED_URL=https://www.sparkpair.dev/api/updates/garmentsos-pro/stable/latest.json
UPDATE_CHANNEL=stable
UPDATE_LAUNCHER_PROTOCOL=garmentsos
UPDATE_REQUEST_TTL_MINUTES=10
UPDATE_LOCK_TTL_MINUTES=30
```

The GitHub stable channel feed remains a fallback for developer/testing recovery:

```env
UPDATE_FALLBACK_FEED_URL=https://github.com/Spark-Pair/garmentsos-pro/releases/download/latest-stable/latest.json
```

Feed priority in the local app:

1. Installed `.env` `UPDATE_FEED_URL`
2. `config/updater.php` feed URL default
3. Stable GitHub fallback feed

The SparkPair `latest.json` endpoint must be public and must not require login. SparkPair should serve small metadata only. Large ZIP/EXE downloads should point directly to public GitHub Release assets and must not be streamed through SparkPair/Vercel.

## Feed JSON

Minimum required fields:

```json
{
  "app": "garmentsos-pro",
  "version": "1.8.59",
  "package_url": "https://github.com/Spark-Pair/garmentsos-pro/releases/download/v1.8.59/garmentsos-pro-1.8.59.zip",
  "package_sha256": "..."
}
```

Optional fields displayed by the updater UI:

```json
{
  "channel": "stable",
  "mandatory": false,
  "released_at": "2026-07-03T00:00:00Z",
  "package_file": "garmentsos-pro-1.8.59.zip",
  "setup_url": "https://github.com/Spark-Pair/garmentsos-pro/releases/download/v1.8.59/GarmentsOS-PRO.exe",
  "notes": "..."
}
```

## Release Sync

The GitHub release workflow can optionally notify SparkPair after assets are published:

```http
POST https://www.sparkpair.dev/api/admin/releases/sync-github
Authorization: Bearer <RELEASE_WEBHOOK_SECRET>
Content-Type: application/json
```

Configure these GitHub Actions secrets:

```text
SPARKPAIR_RELEASE_SYNC_URL=https://www.sparkpair.dev/api/admin/releases/sync-github
SPARKPAIR_RELEASE_WEBHOOK_SECRET=<secret>
```

If the sync secret is missing for stable releases, the workflow fails because SparkPair metadata would not be updated. Beta/dev sync can remain warning-only.

## License Verify

Installed clients should use:

```env
LICENSE_ENABLED=false
LICENSE_ENFORCEMENT_ENABLED=false
LICENSE_AUTO_REGISTER=true
LICENSE_CHECK_URL=https://www.sparkpair.dev/api/licenses/verify
LICENSE_REGISTER_URL=https://www.sparkpair.dev/api/licenses/register-install
LICENSE_GRACE_DAYS=7
```

`LICENSE_ENABLED=false` keeps local/dev builds allowed. Production/client installs can enable licensing after the SparkPair admin panel approval flow is ready.

Register request:

```json
{
  "product": "garmentsos-pro",
  "install_id": "local-random-install-id",
  "machine_hash": "hashed-safe-local-signals",
  "machine_name": "CLIENT-PC",
  "app_version": "1.8.59"
}
```

Verify request:

```json
{
  "product": "garmentsos-pro",
  "install_id": "local-random-install-id",
  "machine_hash": "hashed-safe-local-signals",
  "app_version": "1.8.59"
}
```

The local app persists `install_id` at:

```text
storage/app/install-id.txt
```

Successful active verify responses are cached locally. If SparkPair is unreachable later, the app uses the cached status and grace window instead of immediately blocking. Expired or invalid licenses enter read-only mode. Updater routes remain available so support can recover/update the installation.

Client users do not enter license keys inside GarmentsOS PRO. SparkPair admins approve devices/licenses from the SparkPair site.
