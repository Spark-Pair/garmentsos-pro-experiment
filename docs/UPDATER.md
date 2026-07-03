# Updater Foundation

## Purpose

Phase 4A adds update check, signed manifest validation, package download to private storage, and package verification. It does not apply or install updates.

## Default State

The updater is disabled by default:

```text
UPDATER_ENABLED=false
UPDATE_FEED_URL=https://sparkpair.dev/api/updates/garmentsos-pro/stable/latest.json
UPDATE_FALLBACK_FEED_URL=https://github.com/Spark-Pair/garmentsos-pro-experiment/releases/download/latest-stable/latest.json
UPDATE_CHANNEL=stable
UPDATE_LAUNCHER_PROTOCOL=garmentsos
```

No automatic update checks or automatic update apply actions are enabled for normal users.

`UPDATE_FEED_URL` is the release metadata endpoint for `latest.json`. Client installs should use the public SparkPair feed. The GitHub `latest-stable` release feed remains available as an experiment/developer fallback.

`UPDATE_FALLBACK_FEED_URL` is tried when the primary feed cannot be reached. Existing installed `.env` values are preserved unless a developer/admin explicitly uses the updater page repair action.

Optional timeout:

```text
UPDATE_FEED_TIMEOUT=8
```

## Windows PHP cURL CA Certificate Setup

The Developer Updater page fetches `latest.json` with PHP HTTP/cURL. On Windows, PHP may fail HTTPS requests if `curl.cainfo` or `openssl.cafile` points to a missing certificate bundle. A common error is:

```text
cURL error 77: error setting certificate file: C:\certs\cacert.pem
```

When this happens, the updater page shows the friendly message:

```text
HTTPS certificate bundle is missing. Please configure PHP curl.cainfo.
```

Recommended recovery:

1. Download a current CA bundle from the official curl CA extract page: `https://curl.se/docs/caextract.html`
2. Save it on the Windows host, for example:

```text
C:\certs\cacert.pem
```

3. Edit the PHP `php.ini` used by the app/PHP runtime:

```ini
curl.cainfo="C:\certs\cacert.pem"
openssl.cafile="C:\certs\cacert.pem"
```

4. Restart the PHP process/container/app service so PHP reloads `php.ini`.
5. Reopen Developer -> Updater and check the PHP HTTPS diagnostics:

- PHP cURL available
- `curl.cainfo`
- `openssl.cafile`
- certificate file exists

If a future GarmentsOS PRO package ships its own PHP runtime or `php.ini`, the bundled `php.ini` should point `curl.cainfo` and `openssl.cafile` to the packaged CA bundle path inside that runtime. Do not place secrets in the CA bundle path or updater feed settings.

## No GitHub Credentials On Client PCs

Client PCs must not contain:

- GitHub tokens
- deploy keys
- private repository URLs
- signing private keys
- update server secrets
- build machine credentials

Clients should receive signed release manifests and signed/checksummed packages through a trusted release server or controlled manual import workflow.

## Signed Manifest

The update manifest must be verified before it is trusted. Manifest data includes app id, latest version, minimum required version, update channel, release notes, mandatory flag, package URL, package checksum, package signature, migration flag, backup requirement, supported installation modes, and expiry.

The private signing key belongs only on the release/build side. The client app may contain a public verification key only.

## Package Verification

Packages are downloaded only to private updater temp storage outside `public/`. Phase 4A verifies packages but does not extract them into the app root.

Packages are rejected if they contain:

- `.env` or `.env.*`
- database files
- SQLite WAL/SHM files
- backups
- logs
- private storage
- credentials/secrets/private keys
- `.git/`
- `.github/`
- absolute paths
- path traversal entries

## Future Apply Requirements

Future update apply work must:

- require developer/admin action
- create and verify a backup first
- abort if backup fails
- validate package structure
- never overwrite client DB, `.env`, backups, logs, private storage, or uploads
- snapshot current app files for rollback
- test migrations on staging/copy first
- keep restore separate and never trigger restore automatically

## Phase 4A Boundary

Apply/install is intentionally not implemented. No route should replace app files, run migrations, run Composer update commands, or alter client data.

## Release Feed Contract

Docker release builds generate `docker-releases/latest.json` for GitHub/SparkPair release publishing. It is metadata only and does not contain secrets.

Expected GitHub release assets:

- `GarmentsOS-PRO.exe`
- `garmentsos-pro-VERSION.tar.gz` or `garmentsos-pro-VERSION.zip`
- `garmentsos-pro-VERSION.sha256`
- `latest.json`

`latest.json` contains:

- `app`
- `version`
- `channel`
- `mandatory`
- `released_at`
- `package_file`
- `package_sha256_file`
- `package_sha256`
- `package_url`
- `setup_url`
- `min_launcher_version`
- `notes`

`package_url` and `setup_url` are placeholders during local builds. Replace them with final GitHub/SparkPair release asset URLs when publishing.

## Publishing From GitHub UI

Normal publishing should happen through GitHub Actions, not local CMD/PowerShell commands.

Use:

```text
GitHub repository -> Actions -> Publish GarmentsOS PRO Release -> Run workflow
```

Workflow inputs:

- `version`: release version such as `1.8.14`; the workflow creates tag `v1.8.14`.
- `channel`: update channel, usually `stable`.
- `mandatory`: whether the metadata marks the update as mandatory.
- `min_launcher_version`: minimum supported Windows launcher/package script version.
- `release_notes`: notes written to the GitHub Release and `latest.json`.
- `prerelease`: whether the GitHub Release is marked as prerelease.

The workflow builds the Docker package with `scripts/docker-build-release.sh`, creates the versioned GitHub Release, rewrites `latest.json` with final GitHub release asset URLs, uploads release assets, then updates a moving channel feed release.

Uploaded assets:

- `garmentsos-pro-VERSION.tar.gz`, `garmentsos-pro-VERSION.zip`, or another archive produced by the build script.
- `garmentsos-pro-VERSION.sha256`
- `latest.json`
- `GarmentsOS-PRO.exe`, copied from the published `GarmentsOS-PRO.exe` when the launcher build succeeds.

If the Windows client EXE is not available on the GitHub-hosted runner, the workflow prints a warning and publishes the Docker release assets without failing.

Channel feed releases:

- `latest-stable`
- `latest-beta`
- `latest-dev`

Each channel release contains a moving `latest.json` asset uploaded with `--clobber`. The channel `latest.json` still points `package_url` at the real immutable versioned release asset, for example `https://github.com/Spark-Pair/garmentsos-pro-experiment/releases/download/v1.8.16/garmentsos-pro-1.8.16.zip`.

Private GitHub repositories return `404` for unauthenticated release asset requests. Browsers may appear to work when the developer is logged into GitHub, but installed apps and the Windows launcher are unauthenticated. For client installs, use the public SparkPair update server URL:

```env
UPDATE_FEED_URL=https://sparkpair.dev/api/updates/garmentsos-pro/stable/latest.json
```

The workflow still publishes `latest.json` to `latest-stable`, `latest-beta`, and `latest-dev` for audit and for public repos, but private repo asset URLs are not a reliable client feed.

The installed app can point at the public published feed with:

```env
UPDATE_FEED_URL=https://sparkpair.dev/api/updates/garmentsos-pro/stable/latest.json
UPDATE_CHANNEL=stable
UPDATE_LAUNCHER_PROTOCOL=garmentsos
```

The Developer Updater page fetches this feed read-only, validates the basic JSON contract, compares the installed/current version with `version`, and displays whether an update is available. If GitHub or the internet is unreachable, the page shows `feed_unreachable` instead of crashing. If the HTTP status is `404`, the page explains that private GitHub release assets need a public update feed URL or SparkPair update server.

This feed display does not directly apply updates from Laravel. Actual client update application is handled by the Windows GUI launcher/package flow.

The release feed is the primary updater UI. Advanced signed-manifest apply is a separate security/apply foundation and is shown as a secondary advanced section. It can remain unconfigured while release feed checks and Windows launcher updates continue to work.

## In-App Update Handoff

Primary user flow:

```text
App detects update -> Update Now -> garmentsos://update?request=...&autoStart=1 -> updater splash opens -> update applies -> app reopens
```

When the feed reports `update_available`, the Developer Updater page shows one main button: `Update Now`.

The app creates a temporary signed update request URL that expires after `UPDATE_REQUEST_TTL_MINUTES` minutes, then passes that URL to the Windows launcher protocol with `autoStart=1`. The web app click is the user confirmation. Laravel still does not apply the update; the Windows updater applies it outside the running app.

After clicking `Update Now`, the app shows an update-started overlay. If Windows asks to open GarmentsOS PRO Updater, choose Open.

Manual fallback is hidden under `Troubleshooting / Manual update`. Use it only if `Update Now` does not open the launcher. `Download Update Request` downloads a JSON handoff file from:

```text
/developer/updater/update-request
```

The response contains:

```json
{
  "app": "garmentsos-pro",
  "current_version": "1.8.18",
  "target_version": "1.8.14",
  "channel": "stable",
  "package_file": "garmentsos-pro-1.8.14.zip",
  "package_url": "https://github.com/OWNER/REPO/releases/download/v1.8.14/garmentsos-pro-1.8.14.zip",
  "package_sha256": "...",
  "setup_url": "https://github.com/OWNER/REPO/releases/download/v1.8.14/GarmentsOS-PRO.exe",
  "mandatory": false,
  "notes": "Release notes",
  "requested_at": "2026-06-30T00:00:00Z",
  "apply_method": "windows-launcher-required",
  "launcher_protocol_url": "garmentsos://update"
}
```

Laravel still does not load Docker images, restart containers, or replace the running app. The JSON file is a safe handoff for the Windows GUI launcher/updater.

The current launcher supports opening this JSON manually. A later Windows installer/launcher phase should register the custom protocol:

```text
garmentsos://update
```

Once the protocol is registered, the app hands off to the launcher with a URL such as:

```text
garmentsos://update?request=<encoded update request URL>&autoStart=1
```

The signed request URL is generated from:

```text
/developer/updater/update-request/signed
```

It uses Laravel's signed URL validation and expires automatically. Expired or tampered URLs fail before returning JSON.

Developer users also see a non-invasive in-app update banner when the feed status is `update_available`. The banner shows one primary `Update Now` action and a `Details` link. Feed failures are shown only on the Updater page.

Troubleshooting fallback flow:

```text
Download Update Request -> Open GarmentsOS-PRO.exe -> Open Request JSON -> Update Now
```

Use the fallback only if the protocol link does not open the updater. If an update fails, the updater shows details and troubleshooting actions instead of hiding the error.

Local release commands remain available for developer testing only.
