# GarmentsOS PRO Agent Instructions

GarmentsOS PRO is a Laravel local Windows business app. Treat it as a production client-data system, not a sandbox.

## Default Rules

- Inspect before editing. Read routes, controllers, models, views, migrations, config, and tests related to the task.
- Work in small approved phases. Do not turn a narrow request into a broad refactor.
- Do not change business behavior unless the phase explicitly asks for it.
- Do not edit `.env`.
- Do not touch production database, uploads, logs, backups, storage runtime data, or generated client data.
- Do not run migrations unless explicitly approved.
- Do not run Composer, npm, release scripts, ZIP generation, updater, or installer work unless explicitly approved.
- Never delete disposable build folders unless the user explicitly approves the exact paths.
- Preserve existing user changes. If the git tree is dirty, identify changes before editing.

## Before Editing

Report the exact files to create or change and why when a phase asks for approval first. For implementation phases, keep edits limited to the approved files.

Use this quick inspection pattern:

- `git status --short`
- `rg --files`
- `rg` for routes, labels, roles, paths, and module names
- Read the relevant controller/model/view/test files
- Identify whether the change affects data, permissions, routes, or release packaging

## Testing And Reporting

Run focused tests for changed behavior. For docs-only changes, `git diff --check` is usually enough.

Final reports should include:

- files changed
- behavior summary
- tests/checks run
- git status
- risks or follow-up phase

## Runtime And Data Safety

Current production-style risks include runtime data inside the app tree, SQLite WAL mode, uploads split between `storage/app/public/uploads/images` and `public/uploads/suppliers`, and logs/cache/session/view files under `storage`.

Do not move runtime data casually. Use the runtime readiness docs and backup services before any migration.

Release packages must never include:

- `.env`
- `.git`
- SQLite databases, WAL, SHM
- uploads
- logs
- backups
- storage runtime data
- node_modules
- tests/docs/scripts unless a release rule explicitly allows a file

## Module Safety

New module work must follow `docs/module-conventions.md`.

Before adding or changing a module, check:

- route names and URL paths
- controller authorization
- validation
- feature flag key
- label keys
- sidebar/menu entries
- public JS files
- print/export/report behavior
- upload paths
- release package ownership

Do not expose blank resource methods. Use explicit routes or `only([...])`.

## Client-Specific Work

Prefer, in this order:

1. global code with configuration
2. labels for wording changes, such as Article -> Design
3. feature flags for access control, such as disabling Shipments
4. workflow config for process differences
5. module manifest/package pruning for code ownership
6. overlays only as a last resort

Client-specific work must not break global fixes. Example: an Invoice Quick Print global fix should reach all clients, while Client A can still keep Shipments disabled and Article labeled as Design.

## Release Pipeline Pause Rule

Release pipeline scripts can create disposable workspaces and take time. Do not run them unless the current user request explicitly asks for that phase. Interrupted runs leave partial folders and must not be retried on the same path.
