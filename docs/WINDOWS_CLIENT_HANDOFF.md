# Windows Client Handoff

Give the client a Docker release zip with this shape:

```text
GarmentsOS-Docker-Release/
  docker-compose.yml
  .env.example
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
