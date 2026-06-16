# GarmentsOS PRO AI Instructions

Read `AGENTS.md` first.

This is a Laravel local Windows business app with client data safety requirements. Do not touch `.env`, production databases, uploads, logs, backups, storage runtime data, or release workspaces unless explicitly asked.

Follow these docs:

- `docs/architecture.md`
- `docs/module-conventions.md`
- `docs/coding-standards.md`
- `docs/change-workflow.md`
- `docs/client-specific-customization.md`
- `docs/route-permission-feature-matrix.md`

Prefer small phases. Do not refactor the whole app. Do not expose blank resource methods. Do not add direct `public/uploads` writes. Use labels for client wording differences and feature flags for access control.
