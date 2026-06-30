# Updater Foundation

## Purpose

Phase 4A adds update check, signed manifest validation, package download to private storage, and package verification. It does not apply or install updates.

## Default State

The updater is disabled by default:

```text
UPDATER_ENABLED=false
UPDATE_FEED_URL=
UPDATE_CHANNEL=stable
```

No automatic update checks or automatic update apply actions are enabled for normal users.

`UPDATE_FEED_URL` is the future SparkPair/GitHub release metadata endpoint for `latest.json`. It is displayed on the Developer Updater page so installed package state can be checked, but it does not enable automatic update apply.

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

The workflow builds the Docker package with `scripts/docker-build-release.sh`, creates the GitHub Release, rewrites `latest.json` with final GitHub release asset URLs, and uploads release assets.

Uploaded assets:

- `garmentsos-pro-VERSION.tar.gz`, `garmentsos-pro-VERSION.zip`, or another archive produced by the build script.
- `garmentsos-pro-VERSION.sha256`
- `latest.json`
- `GarmentsOS-PRO-Setup.exe` only if that file exists in the workflow workspace.

If the Windows setup EXE is not available on the GitHub-hosted runner, the workflow prints a warning and publishes the Docker release assets without failing.

The installed app can point at the published feed with:

```env
UPDATE_FEED_URL=https://github.com/OWNER/REPO/releases/download/vVERSION/latest.json
UPDATE_CHANNEL=stable
```

Local release commands remain available for developer testing only.
