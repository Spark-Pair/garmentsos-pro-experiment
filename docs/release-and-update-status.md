# Release And Update Status

This document tracks the safe release/update pipeline status. Do not confuse prepared foundations with a finished updater.

## Done

- Production audit documentation
- Release safety checklist
- Client identity foundation
- Label manager foundation
- Feature flag foundation
- Inert feature middleware
- WAL-safe SQLite backup service
- `/backup-db` migration to safe backup service
- Temporary backup cleanup service
- Manual temp backup cleanup command
- Runtime path readiness service
- Runtime path checker command
- Release builder design
- Machine-readable release rules
- Release inventory validator
- Staging directory builder
- Release prerequisite checker
- Prepare workspace dry-run
- Prepare source copy helper
- Prepare workspace orchestrator

## Not Done

- ZIP artifact creator
- deterministic archive verification
- detached release manifest
- `.sha256` generation
- client installer
- updater
- rollback switcher
- license/subscription enforcement beyond current app config
- migration-safe update flow
- permanent backup retention
- restore workflow
- external runtime migration
- module manifest
- module pruning integration

## Known Trial Note

Prepare workspace trials can take time during Composer install. Interrupted runs leave partial disposable workspaces. Do not rerun on the same path. Use a new disposable path or explicitly approve deletion of the old disposable path.

## Current Packaging Rules

Release packages must include prepared app code, vendor, public build assets, migrations, config, routes, views, public assets, `artisan`, and lock files.

Release packages must exclude `.env`, databases, WAL/SHM, uploads, logs, backups, storage runtime data, `node_modules`, tests, docs, scripts, and developer secrets.

## Next Release Pipeline Phase

When release testing resumes, use fresh disposable paths and allow Composer/npm to finish. If the controlled workspace trial passes, the next phase should be ZIP artifact creator planning only.

Do not implement updater/installer until backup, staging, ZIP verification, and rollback plans are complete.
