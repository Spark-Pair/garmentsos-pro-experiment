# Architecture

GarmentsOS PRO is a Laravel local Windows business application for garment trading, stock, production, payments, and reporting. It is intended to run on a client PC or LAN server without GitHub access, Composer update, npm build, developer secrets, or source-control tooling on the client machine.

## Application Shape

- Laravel web app with Blade views and page-specific JavaScript under `public/js/pages`.
- SQLite is the current production database target.
- The app uses authentication, active-session checks, subscription/read-only mode, and write-request transactions.
- Many modules follow resource-controller patterns, but some routes expose scaffold methods that are not implemented yet.
- Client identity, labels, and feature flags now exist as foundations but are not fully applied across the app.

## Core Runtime Paths

Current app runtime paths are still mostly Laravel defaults:

- database: configured through `config/database.php`
- uploads: mostly `storage/app/public/uploads/images`, with a known split at `public/uploads/suppliers`
- logs: `storage/logs`
- sessions/cache/views: `storage/framework`
- backups: configurable in `config/backup.php`

Future target layout keeps runtime data outside replaceable release folders:

```text
C:\SparkPair\GarmentsOSPro\
  app\
    current\
    releases\
  data\
    database.sqlite
    uploads\
    runtime\
  backups\
    tmp\
    manual\
    auto\
  logs\
  updater\
  .env
```

Do not move to this layout without backup, validation, and rollback steps.

## Modules Overview

Current modules include:

- Auth, users, sessions, roles, permission report
- Setups, rates, defaults
- Suppliers, customers, articles
- Orders, shipments, invoices, invoice print
- Customer payments, supplier payments, payment programs
- Bank accounts, vouchers, CR, DR, daily ledger
- Physical quantities, stock, sales returns
- Fabrics, issued fabric, returned fabric, production
- Employees, attendance, salaries, employee payments
- Cargo, bilties, expenses, utilities
- Reports: statement, article, physical quantity, pending payments
- Notifications
- Backup, runtime readiness, release preparation scripts

## Client-Specific Foundation

Client identity lives in `config/client.php` and `App\Support\ClientContext`.

Labels live in `config/labels.php` and `App\Support\LabelManager`.

Feature flags live in `config/features.php` and `App\Support\FeatureManager`.

The `feature` middleware exists, but production routes and sidebar entries are not yet guarded module-by-module.

## Release And Update Concept

Client PCs should receive prepared release folders or ZIP artifacts, not Git operations.

Already prepared foundations:

- release rules
- inventory validation
- staging
- prerequisite checks
- dry-run workspace preparation
- source copy helper
- workspace preparation orchestrator

Not yet complete:

- ZIP artifact creator
- detached manifest and sha256 finalization
- installer/updater
- rollback switcher
- module pruning

## Safety Principles

- Runtime data must be preserved across releases.
- Release folders should be replaceable.
- Backups must be WAL-safe for SQLite.
- Disabled modules must not delete existing data.
- Old data should reappear if a feature/module is re-enabled.
