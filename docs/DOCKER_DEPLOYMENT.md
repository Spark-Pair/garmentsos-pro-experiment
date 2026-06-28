# Docker Deployment

## Fresh Install

1. Create a client `.env` from `.env.example`.
2. Set `DB_DATABASE=/var/www/html/database/database.sqlite` or another mounted SQLite path.
3. Start the container:

```bash
docker compose up -d --build
```

4. If this is a fresh database and migrations are approved:

```bash
RUN_MIGRATIONS_ON_START=true docker compose up -d
```

## Existing Client Update

1. Stop the app or put it in maintenance mode.
2. Back up `database/` and `storage/`.
3. Rebuild the image or replace the release files.
4. Start without migrations first.
5. Run migrations only after backup approval.

## LAN Access

Map the host port with `APP_PORT`, for example:

```bash
APP_PORT=8080 docker compose up -d
```

LAN users browse to `http://server-ip:8080`.

## Operations

```bash
docker compose logs --tail=100
docker compose restart
docker compose down
```

Do not store backups, `.env`, or database files inside the image.
