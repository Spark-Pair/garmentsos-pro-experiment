# Docker Option

Docker is optional. Non-Docker installs remain supported.

The Docker stack runs Laravel with PHP 8.2 FPM, Nginx, Composer dependencies, SQLite support, and persistent host mounts for `.env`, `database/`, and `storage/`.

Default port:

```bash
APP_PORT=8000 docker compose up -d --build
```

The image does not bake a client `.env` or database. Mount them from the host.

Migrations do not run automatically unless explicitly enabled:

```bash
RUN_MIGRATIONS_ON_START=true docker compose up -d
```

When startup migrations are enabled, the entrypoint creates a verified backup first.
