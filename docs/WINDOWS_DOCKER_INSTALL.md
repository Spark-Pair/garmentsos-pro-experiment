# Windows Docker Install

Primary client delivery is Docker-first. Clients receive a Docker release zip, not a Git repo or raw Laravel source folder.

## Prerequisites

1. Install Docker Desktop for Windows.
2. Start Docker Desktop.
3. Confirm Windows firewall allows Docker/LAN traffic when prompted.

## Install

1. Extract the Docker release zip to:

```text
C:\SparkPair\GarmentsOS
```

2. Run PowerShell as Administrator if required by local policy.
3. Run:

```powershell
cd C:\SparkPair\GarmentsOS
.\scripts\windows-docker-install.ps1
```

The installer loads the Docker image tar, creates `.env` from `.env.example` only when missing, creates named Docker volumes, runs first migrations intentionally, and starts the app.

Open:

```text
http://localhost:8000
http://<server-lan-ip>:8000
```

LAN browser PCs do not need the app files and do not consume separate licenses.

## Safety

- `.env` stays outside the image.
- Database/storage/backups stay in Docker volumes.
- Reinstall/update does not delete volumes.
- Do not delete Docker volumes unless intentionally wiping client data.
