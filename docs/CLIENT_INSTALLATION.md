# Client Installation

Primary client delivery is Docker-first.

Clients receive a Docker release zip, not a Git repository or raw Laravel source folder. The image contains runtime code, while `.env`, database, storage, license identity/cache, and backups persist outside the image in Docker volumes or the client install folder.

## Windows Docker Install

1. Install Docker Desktop.
2. Extract the release zip to `C:\SparkPair\GarmentsOS`.
3. Run:

```powershell
cd C:\SparkPair\GarmentsOS
.\scripts\windows-docker-install.ps1
```

4. Open:

```text
http://localhost:8000
http://<server-lan-ip>:8000
```

LAN browser PCs do not need the app package and are not separately licensed.

## Developer/Emergency Non-Docker Install

Non-Docker install scripts remain for developer/emergency use only:

```bash
./scripts/client-install.sh
./scripts/client-update.sh /path/to/release.zip
```

Do not use raw folder handoff as the normal client delivery model.

## Data Safety

- Never overwrite an existing client database.
- Never overwrite client `.env`, backups, logs, uploads, private storage, or license identity/cache.
- Never commit or ship `.env`, `APP_KEY`, credentials, DB files, backups, dumps, logs, private keys, or tokens.
- Run migrations only after backup and approval.
