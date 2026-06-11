# GarmentsOS PRO Release Safety Checklist

This checklist is the packaging and preservation baseline for all future
GarmentsOS PRO client releases. It is documentation only; no release automation
or updater is implemented in Phase 1.

## Non-Negotiable Release Rules

- [ ] Build releases on the developer machine or controlled CI environment.
- [ ] Do not build application assets on a client production PC.
- [ ] Do not run Git operations on a client production PC.
- [ ] Do not provide GitHub access, GitHub tokens, `.git`, or developer
      credentials to a client.
- [ ] Do not package the project directory by blindly zipping it.
- [ ] Do not overwrite authoritative client data during installation.
- [ ] Do not store the authoritative database inside a replaceable release
      directory in the final architecture.
- [ ] Do not run migrations without a verified pre-update database backup.
- [ ] Do not install an update whose checksum/signature or target client does
      not match.
- [ ] Do not delete the previous release until the new release is proven
      stable and the retention policy permits deletion.

## A. Must Preserve During Update

- [ ] Root `.env`
- [ ] Existing `APP_KEY`
- [ ] Configured live SQLite database
- [ ] SQLite WAL and SHM state when relevant to the selected backup method
- [ ] `storage\app` client data
- [ ] `storage\app\public\uploads\images`
- [ ] Legacy `public\uploads`, including `public\uploads\suppliers`
- [ ] Manual backups
- [ ] Automatic backups
- [ ] Logs required for support or auditing
- [ ] License file when introduced
- [ ] Client identity file when introduced
- [ ] Machine-binding file when introduced
- [ ] Update configuration and update history when introduced
- [ ] Any other writable client-owned files identified before packaging

Current special case:

- [ ] Classify `storage\app\database.sqlite` before deployment work. It is not
      the configured live database and must not be treated as authoritative
      without explicit verification.

## B. Must Exclude From A Release Package

- [ ] `.git`
- [ ] `.github` content not required at runtime
- [ ] `.env`
- [ ] `.env.backup`
- [ ] `.env.production`
- [ ] Any other real environment files
- [ ] `auth.json`
- [ ] GitHub, Composer, package registry, deployment, or update-server
      credentials
- [ ] Private keys, tokens, passwords, and developer secrets
- [ ] `database\*.sqlite`
- [ ] `database\*.sqlite-wal`
- [ ] `database\*.sqlite-shm`
- [ ] `storage\app\database.sqlite`
- [ ] Other database copies, corrupt backups, dumps, or exports
- [ ] Existing client backups
- [ ] Existing client logs
- [ ] Existing client uploads
- [ ] Existing client sessions
- [ ] Existing client file cache
- [ ] Existing compiled Blade views
- [ ] `node_modules`
- [ ] `tests`
- [ ] `.phpunit.result.cache`
- [ ] `.phpunit.cache`
- [ ] `public\hot`
- [ ] Local IDE settings such as `.idea`, `.vscode`, and `.fleet`
- [ ] Local OS/editor temporary files
- [ ] npm/yarn debug logs
- [ ] Developer notes and reports not explicitly approved for delivery
- [ ] Scratch files such as temporary JavaScript or SQL experiments
- [ ] Client-specific branding, overrides, identity, or license material
      belonging to a different client
- [ ] Stale runtime links/files such as the current invalid `public\storage`
      file

`vendor` and `public\build` may be ignored by Git, but they must be generated
and included in the final production release artifact.

## C. Must Include In A Release Package

- [ ] `app`
- [ ] Required `bootstrap` files
- [ ] `config`
- [ ] `routes`
- [ ] `resources\views`
- [ ] Other runtime resources required by Laravel
- [ ] `database\migrations`
- [ ] `public\index.php`
- [ ] `public\build`
- [ ] `public\build\manifest.json`
- [ ] Required directly served `public\js`
- [ ] Required public images, fonts, manifest, service worker, and offline page
- [ ] `vendor` installed with production dependencies
- [ ] `artisan`
- [ ] `composer.json`
- [ ] `composer.lock`
- [ ] Required package discovery/cache bootstrap structure
- [ ] Release metadata containing the version and supported client target
- [ ] A package file inventory or manifest
- [ ] SHA256 checksum
- [ ] Cryptographic signature when the signing system is introduced
- [ ] Approved client-specific overrides for the target client only

Do not include `node_modules`; Vite assets must already be built.

## Build Preparation Checklist

- [ ] Start from the intended reviewed commit/tag.
- [ ] Confirm the Git working tree does not contain accidental client data.
- [ ] Install Composer production dependencies with optimized autoloading.
- [ ] Run the frontend build in the developer/CI environment.
- [ ] Confirm `public\build\manifest.json` references files present in the
      artifact.
- [ ] Confirm directly served `public\js` files are included.
- [ ] Confirm `public\hot` is absent.
- [ ] Scan the staged artifact for `.env`, SQLite files, credentials, tokens,
      logs, backups, uploads, and other prohibited files.
- [ ] Confirm only the target client's approved overrides and branding are
      present.
- [ ] Record the release version, source commit, build date, and target client.
- [ ] Generate and independently verify the artifact SHA256.

## Pre-Update Backup Checklist

- [ ] Notify users and prevent new write operations.
- [ ] Enter maintenance mode or otherwise quiesce the application.
- [ ] Stop the application service before a filesystem-level SQLite copy.
- [ ] Determine whether WAL is active.
- [ ] Use SQLite's online backup facility, `VACUUM INTO`, or a controlled
      checkpoint-and-copy procedure.
- [ ] Do not copy only `database.sqlite` while writes may still exist in WAL.
- [ ] Store the backup outside `app\current` and all versioned release folders.
- [ ] Use a timestamped, client-specific backup name.
- [ ] Back up authoritative uploads and other client-owned writable data.
- [ ] Record current application version and migration state.
- [ ] Run `PRAGMA integrity_check` against the database backup.
- [ ] Confirm the backup can be opened read-only.
- [ ] Confirm the backup is non-empty and its file size is plausible.
- [ ] Confirm sufficient disk space exists for the new release, backup, and
      rollback copy.
- [ ] Abort the update if any backup or verification step fails.

## Installation Safety Checklist

- [ ] Verify the update targets this client ID and update channel.
- [ ] Verify minimum/current version compatibility.
- [ ] Verify SHA256 and signature before extraction.
- [ ] Extract into a new staging or versioned release directory.
- [ ] Reject archive entries containing absolute paths, `..`, unsafe links, or
      files outside the release directory.
- [ ] Confirm `.env`, database, uploads, backups, and logs are not supplied by
      the archive.
- [ ] Connect the candidate release to preserved external configuration and
      data only through the approved mechanism.
- [ ] Clear stale application caches.
- [ ] Run migrations with `--force` only after backup verification.
- [ ] Build production caches only after the candidate configuration is valid.
- [ ] Start the candidate and run application/database health checks.
- [ ] Verify required public assets load.
- [ ] Verify uploads remain accessible.
- [ ] Verify login and one read-only core page before enabling normal use.
- [ ] Switch the active release only after all required checks pass.

## Rollback Checklist

- [ ] Keep the previous release untouched during installation.
- [ ] Keep the verified pre-update database backup untouched.
- [ ] Know whether migrations require database restoration before switching
      back.
- [ ] Stop the failed candidate.
- [ ] Restore the previous database when schema/data compatibility requires it.
- [ ] Validate the restored database with `PRAGMA integrity_check`.
- [ ] Reactivate the previous release.
- [ ] Clear caches that may reference the failed release.
- [ ] Restart the application service.
- [ ] Confirm health, login, data visibility, and upload availability.
- [ ] Record the failure reason, failed version, rollback time, and backup used.
- [ ] Do not retry automatically until the failure has been reviewed.

## Current Installation Hazards To Resolve Later

- [ ] Move the authoritative database outside the replaceable application
      directory.
- [ ] Move or map writable runtime storage outside release directories.
- [ ] Consolidate or explicitly preserve both upload locations.
- [ ] Replace the stale `public\storage` file with the approved Windows-safe
      linking/mapping approach.
- [ ] Replace obsolete `E:\al-jobat-spark-pair` script paths.
- [ ] Remove `npm run build` from client production startup.
- [ ] Replace or service-manage `php artisan serve` for final production.
- [ ] Replace the current main-file-only database download with a
      WAL-consistent backup service.
- [ ] Identify and safely archive/remove the stale
      `storage\app\database.sqlite` only after explicit approval.

## LAN Production Checklist

- [ ] Assign the server PC a static IP or DHCP reservation.
- [ ] Bind the application service to `0.0.0.0` on the selected port.
- [ ] Allow the application port only on the Windows Private firewall profile.
- [ ] Do not expose the application directly to the public internet.
- [ ] Test access from another office PC using the server IP.
- [ ] Provide browser shortcuts/open scripts that point to the server IP.
- [ ] Configure automatic startup after Windows and networking are ready.
- [ ] Configure service restart-on-failure behavior.
- [ ] Use a dedicated service account with limited filesystem permissions.
- [ ] Confirm the service account can write only to approved data, runtime,
      logs, backup, and updater locations.
- [ ] Retain and rotate application and updater logs.
- [ ] Add and monitor a future health endpoint.
- [ ] Test operation after a Windows restart.

## Release Acceptance Checklist

- [ ] Existing automated tests pass.
- [ ] Default-client behavior is unchanged unless the release explicitly says
      otherwise.
- [ ] Database backup and restore have been tested on a non-production copy.
- [ ] Clean-machine installation has been tested.
- [ ] LAN access has been tested from at least one other PC.
- [ ] Application restart and Windows restart have been tested.
- [ ] Release notes identify migrations, backup requirements, and rollback
      limitations.
- [ ] The previous stable artifact and compatible database backup remain
      available.
- [ ] A responsible person has approved rollout to production.

## Recommended Next Phase

Phase 2 should create the client identity/configuration foundation while
preserving all current behavior:

- Add a stable client ID and installed application version source.
- Add typed configuration contracts for supported labels, features, and
  workflows, with current behavior as the defaults.
- Add focused tests for default resolution and invalid configuration.
- Do not move production data.
- Do not implement the updater.
- Do not introduce client-specific business logic.

