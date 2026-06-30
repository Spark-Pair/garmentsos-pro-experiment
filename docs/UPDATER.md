# Updater Foundation

## Purpose

Phase 4A adds update check, signed manifest validation, package download to private storage, and package verification. It does not apply or install updates.

## Default State

The updater is disabled by default:

```text
UPDATER_ENABLED=false
UPDATE_FEED_URL=https://github.com/Spark-Pair/garmentsos-pro-experiment/releases/download/latest-stable/latest.json
UPDATE_CHANNEL=stable
```

No automatic update checks or automatic update apply actions are enabled for normal users.

`UPDATE_FEED_URL` is the SparkPair public release metadata endpoint for `latest.json`. It is displayed on the Developer Updater page so installed package state can be checked, but it does not enable automatic update apply.

Optional timeout:

```text
UPDATE_FEED_TIMEOUT=8
```

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

- `GarmentsOS-PRO-Setup.exe`
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
- `GarmentsOS-PRO-Setup.exe`, copied from the published `GarmentsOS PRO Launcher.exe` when the launcher build succeeds.

If the Windows setup EXE is not available on the GitHub-hosted runner, the workflow prints a warning and publishes the Docker release assets without failing.

Channel feed releases:

- `latest-stable`
- `latest-beta`
- `latest-dev`

Each channel release contains a moving `latest.json` asset uploaded with `--clobber`. The channel `latest.json` still points `package_url` at the real immutable versioned release asset, for example `https://github.com/Spark-Pair/garmentsos-pro-experiment/releases/download/v1.8.16/garmentsos-pro-1.8.16.zip`.

Private GitHub repositories return `404` for unauthenticated release asset requests. Browsers may appear to work when the developer is logged into GitHub, but installed apps and the Windows launcher are unauthenticated. For client installs, use the public SparkPair update server URL:

```env
UPDATE_FEED_URL=https://updates.sparkpair.dev/garmentsos-pro/stable/latest.json
```

The workflow still publishes `latest.json` to `latest-stable`, `latest-beta`, and `latest-dev` for audit and for public repos, but private repo asset URLs are not a reliable client feed.

The installed app can point at the published feed with:

```env
UPDATE_FEED_URL=https://github.com/Spark-Pair/garmentsos-pro-experiment/releases/download/latest-stable/latest.json
UPDATE_CHANNEL=stable
```

The Developer Updater page fetches this feed read-only, validates the basic JSON contract, compares the installed/current version with `version`, and displays whether an update is available. If GitHub or the internet is unreachable, the page shows `feed_unreachable` instead of crashing. If the HTTP status is `404`, the page explains that private GitHub release assets need a public update feed URL or SparkPair update server.

This feed display does not directly apply updates from Laravel. Actual client update application is handled by the Windows GUI launcher/package flow.

## In-App Update Handoff

When the feed reports `update_available`, the Developer Updater page shows `Prepare Update`.

`Prepare Update` downloads a JSON handoff file from:

```text
/developer/updater/update-request
```

The response contains:

```json
{
  "app": "garmentsos-pro",
  "target_version": "1.8.14",
  "package_url": "https://github.com/OWNER/REPO/releases/download/v1.8.14/garmentsos-pro-1.8.14.zip",
  "package_sha256": "...",
  "mandatory": false,
  "requested_at": "2026-06-30T00:00:00Z",
  "apply_method": "windows-launcher-required",
  "notes": "Release notes"
}
```

Laravel still does not load Docker images, restart containers, or replace the running app. The JSON file is a safe handoff for the Windows GUI launcher/updater.

The current launcher supports opening this JSON manually. A later launcher protocol/agent can remove that file-selection step.

Developer users also see a non-invasive in-app update banner when the feed status is `update_available`. The banner links to the Developer Updater page and to the safe `Prepare Update` handoff JSON. Feed failures are shown only on the Updater page.

Local release commands remain available for developer testing only.
