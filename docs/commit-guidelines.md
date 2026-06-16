# Commit Guidelines

Use commits as phase boundaries.

## Commit Style

Use concise imperative messages:

- `Add architecture standards docs`
- `Restrict incomplete supplier payment routes`
- `Add module manifest foundation`
- `Add client profile config foundation`
- `Harden invoice print validation`

## One Phase Per Commit

Good commit scope:

- docs-only standards phase
- one route cleanup phase
- one module validation hardening phase
- one feature flag application phase

Avoid commits that mix docs, business logic, release scripts, and unrelated UI changes.

## Commit Body

Include:

- why the change was made
- what files/areas changed
- behavior impact
- tests/checks run
- any follow-up risk

Example:

```text
Restrict incomplete supplier payment routes

SupplierPaymentController only implements index behavior. Limit the
resource route to index so direct create/store/edit/update/destroy URLs
do not return blank responses.

Tests: php artisan test --filter SupplierPaymentRouteTest
```

## Never Commit

- `.env`
- database files
- `*.sqlite`, `*.sqlite-wal`, `*.sqlite-shm`
- uploads
- backups
- logs
- storage runtime files
- temporary release workspaces
- `node_modules`
- private keys, tokens, or credential JSON files

## Before Committing

Run:

```text
git status --short
git diff --check
```

Run focused tests for code changes. Run the full suite when shared middleware, auth, database behavior, reports, backup, runtime, or release scripts change.
