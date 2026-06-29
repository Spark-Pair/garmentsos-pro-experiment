# Windows Docker Install

Primary client delivery is Docker-first. Clients receive a Docker release zip, not a Git repo or raw Laravel source folder.

## Prerequisites

1. Install Docker Desktop for Windows.
2. Start Docker Desktop.
3. Confirm Windows firewall allows Docker/LAN traffic when prompted.
4. Use Windows PowerShell 5.1 or newer. The provided BAT wrapper runs PowerShell with `-ExecutionPolicy Bypass -NoProfile`.

## Install

1. Extract the Docker release zip to:

```text
C:\SparkPair\GarmentsOS
```

2. For easiest install, double-click:

```text
scripts\install.bat
```

3. Or run PowerShell manually:

```powershell
cd C:\SparkPair\GarmentsOS
.\scripts\windows-docker-install.ps1
```

The installer loads the Docker image tar, creates `.env` from `.env.example` only when missing, creates named Docker volumes, runs first migrations intentionally, and starts the app.

The old `1.8.0` Docker release built before the Windows PowerShell compatibility fix should be replaced. Use a package built from `1.8.1` or later for Windows testing.

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
