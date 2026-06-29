# Release Package

GarmentsOS PRO has two package types:

1. Docker-first client release under `docker-releases/`.
2. Runtime source package under `releases/` for developer/emergency use.

## Docker Client Release

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
```

Generated Docker releases are ignored by Git and must not be committed.

Validate:

```bash
bash scripts/validate-docker-release.sh docker-releases/garmentsos-pro-1.8.0
```

## Exclusions

Packages must not include `.git`, `.github`, `.env`, database files, WAL/SHM files, logs, backups, dumps, private storage data, private keys, APP_KEY values, tokens, credentials, license private keys, or update signing private keys.

## Docker Reality

Docker is safer than giving clients a raw Laravel repo/folder, but it is not perfect source secrecy against a highly technical attacker. Real secrets must stay outside the image.
