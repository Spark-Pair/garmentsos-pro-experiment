# GarmentsOS PRO Production Audit

Audit date: 2026-06-11

This document records the current production/runtime layout and the safety
constraints that must be respected before client-specific releases, deployment
automation, backup improvements, or updater work begin.

No application behavior is changed by this document.

## Current Runtime

| Item | Current value |
| --- | --- |
| Laravel | 10.49.0 |
| PHP CLI | 8.2.28 |
| Composer | 2.9.8 |
| Environment | `production` |
| Debug mode | `false` |
| Application URL | `http://192.168.100.11:8000` |
| Database driver | SQLite |
| Live database | `C:\Software\garmentsos-pro\database\database.sqlite` |
| SQLite journal mode | WAL |
| SQLite integrity check | `ok` |
| SQLite foreign keys | Enabled on the Laravel connection |
| SQLite busy timeout | 5000 ms |
| Storage root | `C:\Software\garmentsos-pro\storage` |
| Logs | `storage\logs\laravel.log` |
| Sessions | `storage\framework\sessions` |
| File cache | `storage\framework\cache\data` |
| Compiled views | `storage\framework\views` |
| Queue | Synchronous |

The application currently keeps code, the live database, uploads, logs,
sessions, cache, and compiled views under the same project directory. This
layout is usable for the current installation but is not safe for replacing the
application directory during an update.

## Database Findings

- The configured live database is
  `C:\Software\garmentsos-pro\database\database.sqlite`.
- SQLite WAL mode is active. The associated `database.sqlite-wal` and
  `database.sqlite-shm` files may exist while the application is running.
- The live database passed `PRAGMA integrity_check`.
- Laravel enables foreign keys, WAL, `synchronous=NORMAL`, and a 5000 ms busy
  timeout when booting the SQLite connection.
- An older database file also exists at `storage\app\database.sqlite`. It is not
  the configured live database. It must be treated as stale/unclassified data
  until manually identified, and must not be shipped in a release or mistaken
  for the authoritative database.
- The current `/backup-db` route streams only the configured
  `database.sqlite` file. With WAL active and writes in progress, copying only
  the main file is not a guaranteed consistent backup.

## Storage And Upload Findings

- The default local disk uses `storage\app`.
- The public disk uses `storage\app\public`.
- Most uploaded images are written to
  `storage\app\public\uploads\images`.
- A legacy customer upload path writes directly to
  `public\uploads\suppliers`.
- Uploads are therefore split between Laravel storage and the public
  application tree.
- `public\storage` is not a valid Windows junction or symbolic link. It is a
  regular 53-byte file containing the obsolete Linux path:

  ```text
  /home/raza/Software/garmentsos-pro/storage/app/public
  ```

This stale file can prevent a correct `storage:link` operation and can make
stored public uploads inaccessible through `/storage`.

## Frontend Build Findings

- Vite inputs are `resources/css/app.css` and `resources/js/app.js`.
- `public\build\manifest.json` exists.
- Hashed CSS, JavaScript, Font Awesome fonts, and related build artifacts exist
  under `public\build\assets`.
- Much of the application JavaScript is served directly from `public\js` and is
  not part of the main Vite bundle.
- A production release must therefore include both `public\build` and required
  directly served public assets.
- `public\hot` must never be included in a production release.

## Start Script Findings

Current root-level scripts:

- `run.bat`
- `open.bat`
- `Al-Jobat.vbs`

Observed issues:

- `run.bat` changes directory to the obsolete path
  `E:\al-jobat-spark-pair`.
- `Al-Jobat.vbs` launches `E:\al-jobat-spark-pair\run.bat`.
- `open.bat` runs `npm run build` on the production PC before opening the
  browser.
- `run.bat` starts `php artisan serve --host=0.0.0.0 --port=8000`.
- Binding to `0.0.0.0` permits LAN access when Windows Firewall allows the
  port.
- `php artisan serve` is acceptable for the current temporary arrangement, but
  a managed Windows service with a packaged web/PHP runtime is the preferred
  final production setup.

The scripts must not be edited during this audit phase.

## Production Risks

1. The live database is inside the application directory.
2. Writable storage, uploads, logs, sessions, cache, and compiled views are
   inside the application directory.
3. Replacing or deleting the project directory could destroy production data.
4. The backup route may produce an incomplete SQLite backup while WAL contains
   committed pages not yet checkpointed into the main database file.
5. Uploads are split between two locations and can be missed by a backup or
   release switch.
6. `public\storage` is stale and invalid for the current Windows installation.
7. Start scripts refer to an obsolete drive and project directory.
8. `open.bat` incorrectly requires Node/npm and performs a frontend build on
   the client PC.
9. The stale `storage\app\database.sqlite` could be accidentally distributed or
   restored.
10. A direct ZIP of the working tree could expose `.env`, production data,
    logs, developer files, or client-specific assets.
11. Client PCs must not receive `.git`, GitHub credentials, Composer
    credentials, developer secrets, or repository access.
12. Configuration and routes are not currently cached. This is not a data-loss
    risk, but should be addressed by a future controlled release build.
13. Some browser functionality loads third-party XLSX and optional Pusher
    JavaScript from external CDNs, so those features may depend on internet
    availability.

## Future Client Server Folder Plan

The intended final production structure is:

```text
C:\SparkPair\GarmentsOSPro\
  app\
    current\
    releases\
      {version}\
  data\
    database.sqlite
    uploads\
    runtime\
  backups\
    auto\
    manual\
  logs\
  updater\
  .env
  run.bat
  open.bat
```

This is a future target only. Phase 1 does not move the database, storage,
uploads, logs, `.env`, or scripts.

The key rule is that authoritative client data must live outside replaceable
release directories. `app\current` should identify the active application
release, while `.env`, the database, uploads, backups, runtime storage, and
logs remain stable across release changes.

## Pre-Update Backup Rules

Future updates must not begin until all of these conditions are met:

1. Prevent new write requests and place the application into maintenance mode.
2. Stop or quiesce the application process before filesystem-level database
   copying.
3. Handle WAL correctly. Either use SQLite's online backup mechanism or
   `VACUUM INTO`, or checkpoint WAL after writes are stopped before copying.
4. Never assume that copying only `database.sqlite` is safe while WAL is
   active.
5. Include all authoritative uploads and other client data in the preservation
   plan.
6. Record the installed application version and database migration state.
7. Validate the database backup with `PRAGMA integrity_check`.
8. Confirm that the backup exists, is readable, and has a sensible non-zero
   size before changing application files or running migrations.
9. Store pre-update backups outside the release directory.
10. Retain the previous application release until the new release passes its
    health checks.

## Rollback Safety Notes

- A rollback must restore both the previous application release and a database
  version compatible with that release.
- If a migration modifies data or schema, switching code alone is not a
  complete rollback.
- Keep an untouched, verified pre-update database backup.
- Do not overwrite the previous release during installation.
- Extract a candidate release into a new versioned directory.
- Run migrations and health checks under controlled maintenance.
- Switch the active release only after package, database, and application
  checks pass.
- If validation fails, stop the candidate, restore the verified database when
  required, reactivate the previous release, restart the service, and log the
  failure.
- Never report an update as successful before a post-switch health check.

## LAN Production Notes

- Reserve a static server IP or configure a DHCP reservation.
- Continue to bind the server to `0.0.0.0` when LAN access is required.
- Permit the selected application port only on the Windows Private network
  firewall profile.
- Other office PCs should access the application through the server PC IP,
  such as `http://192.168.1.50:8000`.
- Do not expose the application port directly to the public internet.
- Use a browser shortcut or small open script on client PCs.
- The final setup should use a managed Windows service or scheduled startup
  mechanism with restart-on-failure behavior.
- The service account should have only the permissions needed for application
  code and the external data/runtime directories.
- Add a future health endpoint and retain application/updater logs for support.

## Recommended Next Phase

Phase 2 should introduce the client identity and configuration foundation only:

- Define a stable client ID and installed application version.
- Add configuration contracts for future labels, features, and workflows
  without enabling custom behavior yet.
- Keep defaults identical to current behavior.
- Add focused tests proving the default client remains unchanged.
- Do not move production data or implement the updater as part of Phase 2.

