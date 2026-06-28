# Release Package

GarmentsOS PRO release packages are runtime-only bundles for client PCs or LAN servers. They must never include developer state, client data, secrets, backups, logs, or database files.

## Build

```bash
./scripts/build-release.sh 1.8.0
```

For local test builds with uncommitted work:

```bash
./scripts/build-release.sh 0.0.0-test --allow-dirty
```

Output:

```text
releases/garmentsos-pro-<version>/
releases/garmentsos-pro-<version>.zip
releases/garmentsos-pro-<version>.sha256
releases/garmentsos-pro-<version>-manifest.json
```

Generated release files are ignored by Git and must not be committed.

## Validation

```bash
./scripts/validate-release.sh releases/garmentsos-pro-1.8.0
./scripts/validate-release.sh releases/garmentsos-pro-1.8.0.zip
```

The validator fails if the package contains `.env`, `.git`, SQLite DB/WAL/SHM files, backups, dumps, logs, private keys, secret-looking values, `node_modules`, tests, or private storage data.

## Runtime Contents

Safe package contents include Laravel runtime files, migrations, public assets, resources, routes, safe docs, install/update scripts, `.env.example`, and empty runtime folder skeletons under `storage/` and `bootstrap/cache/`.

Never overwrite an existing client `.env` or database during install or update.
