# Release Package

GarmentsOS PRO has two package types:

1. Docker-first client release under `docker-releases/`.
2. Runtime source package under `releases/` for developer/emergency use.

## Docker Client Release

Normal publishing is done from the GitHub Actions UI:

```text
Actions -> Publish GarmentsOS PRO Release -> Run workflow
```

The workflow builds the Docker release package, creates tag `vVERSION`, uploads release assets, writes the final GitHub asset URLs into versioned `latest.json`, and updates the moving channel feed release such as `latest-stable`.

Local command-line builds are for developer testing:

```bash
bash scripts/docker-build-release.sh 1.8.0
```

Output:

```text
docker-releases/garmentsos-pro-1.8.0/
docker-releases/garmentsos-pro-1.8.0/images/garmentsos-pro-1.8.0.tar
docker-releases/garmentsos-pro-1.8.0/docker-compose.yml
docker-releases/garmentsos-pro-1.8.0/.env.example
docker-releases/garmentsos-pro-1.8.0/scripts/
docker-releases/garmentsos-pro-1.8.0/docs/
docker-releases/garmentsos-pro-1.8.0/manifest.json
docker-releases/garmentsos-pro-1.8.0.sha256
docker-releases/garmentsos-pro-1.8.0.zip
docker-releases/latest.json
```

Generated Docker releases are ignored by Git and must not be committed.

`latest.json` is generated beside the package archive for GitHub/SparkPair update metadata. Upload it with the release assets after replacing placeholder URLs when publishing.

The versioned release keeps its own `latest.json` for audit/history. Installed apps should use the stable channel URL instead:

```env
UPDATE_FEED_URL=https://github.com/Spark-Pair/garmentsos-pro-experiment/releases/download/latest-stable/latest.json
```

The moving channel file still points `package_url` at the immutable `vVERSION` package asset.

If the feed includes a real `setup_url`, the Developer Updater page shows a `Download Windows Updater` button. Placeholder setup URLs are hidden from the UI.

Expected release assets:

- `GarmentsOS-PRO-Setup.exe`
- `garmentsos-pro-VERSION.tar.gz` or `garmentsos-pro-VERSION.zip`
- `garmentsos-pro-VERSION.sha256`
- `latest.json`

The setup EXE is copied from the published WinForms launcher output as `GarmentsOS-PRO-Setup.exe`. If the launcher build fails, the GitHub workflow skips it with a warning and the package still has BAT/PowerShell fallback launchers.

For private repositories, GitHub release assets require authentication and return `404` to installed clients. Publish `docker-releases/latest.json` to the public SparkPair update feed, for example:

```env
UPDATE_FEED_URL=https://updates.sparkpair.dev/garmentsos-pro/stable/latest.json
```

Validate:

```bash
bash scripts/validate-docker-release.sh docker-releases/garmentsos-pro-1.8.0
```

## Exclusions

Packages must not include `.git`, `.github`, `.env`, database files, WAL/SHM files, logs, backups, dumps, private storage data, private keys, APP_KEY values, tokens, credentials, license private keys, or update signing private keys.

## Docker Reality

Docker is safer than giving clients a raw Laravel repo/folder, but it is not perfect source secrecy against a highly technical attacker. Real secrets must stay outside the image.
