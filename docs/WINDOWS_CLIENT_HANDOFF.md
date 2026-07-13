# Windows Client Handoff

Give the client a Docker release zip with this shape:

```text
GarmentsOS-Docker-Release/
  docker-compose.yml
  .env.example
  Open GarmentsOS.bat
  Install GarmentsOS.bat
  Update GarmentsOS.bat
  Stop GarmentsOS.bat
  Backup GarmentsOS.bat
  Repair GarmentsOS Network.bat
  GarmentsOS-PRO.exe
  launcher/
  scripts/
    windows-docker-install.ps1
    windows-docker-update.ps1
    windows-docker-backup.ps1
    windows-docker-restore.ps1
    run-lan.bat
    stop.bat
  docs/
    WINDOWS_DOCKER_INSTALL.md
    WINDOWS_DOCKER_UPDATE.md
  images/
    garmentsos-pro-<version>.tar
  checksums/
    garmentsos-pro-<version>.sha256
  manifest.json
```

For Windows clients, use Windows PowerShell 5.1 or newer. The easiest entrypoints are the root launchers:

```text
GarmentsOS-PRO.exe
Install GarmentsOS.bat
Update GarmentsOS.bat
Open GarmentsOS.bat
Stop GarmentsOS.bat
Backup GarmentsOS.bat
```

Client flow:

1. First install: double-click `Install GarmentsOS.bat`.
2. Daily use: use the Desktop shortcut named `GarmentsOS PRO`, the Start Menu shortcut under `SparkPair`, or double-click `Open GarmentsOS.bat`.
3. GUI update: open `GarmentsOS-PRO.exe`; it can open the app, install on a clean PC, or apply an update handoff.
4. Handoff update: in the app Developer Updater page, click `Prepare Update`, then open the downloaded JSON in the launcher.
5. Fallback update: extract the new package and double-click `Update GarmentsOS.bat`.
6. Stop the app: double-click `Stop GarmentsOS.bat`.
7. LAN/firewall repair: right-click `Repair GarmentsOS Network.bat` and run as administrator if another PC can ping the server but cannot open `http://SERVER_IP:8000`.

The technical scripts remain under `scripts/` for support and automation. Installed client folders hide technical files by default using the Windows hidden attribute. Support/developer machines can show hidden files in File Explorer or run install/update with `-HideTechnicalFiles:$false`.

If a package was built as `1.8.0` before the Windows PowerShell compatibility fix, replace it with `1.8.1` or later before client testing.

Do not give clients:

- Git repo
- raw development folder
- `.git` or `.github`
- `.env`
- database files
- backups/logs/dumps
- private keys
- GitHub tokens
- license/update signing private keys

Docker reduces source exposure compared with raw folder delivery, but it is not absolute protection against a highly technical attacker with full machine access. Real secrets stay outside the image in `.env` or on the license/update server.
