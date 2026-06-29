# Docker Deployment

Docker is the primary client deployment path for GarmentsOS PRO.

Clients should receive a Docker release zip containing a saved image tar, compose file, `.env.example`, scripts, docs, checksums, and manifest. They should not receive the Git repo or raw Laravel source folder.

## Data Persistence

`docker-compose.yml` uses named volumes:

```yaml
volumes:
  garmentsos_database:
  garmentsos_storage:
```

These preserve:

- SQLite database
- storage
- license/cache/identity files under storage
- managed backups

Updating the image/container does not remove volumes.

## Runtime Environment

`.env` is mounted from the client install folder and is not baked into the image. The image does not include client database files, logs, backups, dumps, or secrets.

Migrations run only when:

```env
RUN_MIGRATIONS_ON_START=true
```

The entrypoint creates a backup before migrations.

## Commands

```bash
docker compose up -d
docker compose logs --tail=100
docker compose down
```

On Windows clients, prefer the provided PowerShell scripts.
