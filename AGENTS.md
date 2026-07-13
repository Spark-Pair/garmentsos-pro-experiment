# GarmentsOS PRO Agent Rules

This is the GarmentsOS PRO experiment repo. AI agents and developers must read these docs before changing code:

- [Project Overview](docs/PROJECT_OVERVIEW.md)
- [Current Status](docs/CURRENT_STATUS.md)
- [Branch System](docs/BRANCH_SYSTEM.md)
- [Branch Module Registry](docs/BRANCH_MODULE_REGISTRY.md)
- [Branch Filtering Rules](docs/BRANCH_FILTERING_RULES.md)
- [Reports Branch Rules](docs/REPORTS_BRANCH_RULES.md)
- [Serial Numbering Rules](docs/SERIAL_NUMBERING_RULES.md)
- [Auto Created Records Rules](docs/AUTO_CREATED_RECORDS_RULES.md)
- [UI/UX Rules](docs/UI_UX_RULES.md)
- [Migration Safety Rules](docs/MIGRATION_SAFETY_RULES.md)
- [Release Safety Checklist](docs/RELEASE_SAFETY_CHECKLIST.md)
- [Manual Browser Tests](docs/MANUAL_BROWSER_TESTS.md)
- [Known Risks and Pending](docs/KNOWN_RISKS_AND_PENDING.md)

## Hard Rules

- Do not commit, push, tag, or release unless explicitly asked.
- Preserve the existing app UI and interaction style.
- Do not change `main-child` classes.
- Do not make `main-child` scrollable.
- Header/search-header should not scroll; inner content areas scroll.
- Use existing app components, classes, tables, modals, and context menus.
- Do not use browser `alert()` for new work.
- Normal CRUD feedback uses toast only.
- Top-right notifications are only for real persistent/system notifications.
- Update available uses the app modal, not a top warning box.
- Never rewrite already-run migrations.
- Use forward-only migrations for new schema/data changes.
- Preserve old data.
- Never rename old document numbers automatically.
- Main Branch defaults all modules enabled, but Developer can disable anything.
- Repair/backfill must not overwrite Developer settings.

## Branch System Memory

- Dynamic Branch Module Registry currently has 50 modules.
- Global route-aware branch switcher renders in `resources/views/app.blade.php`.
- Hidden branch preference form is outside page forms.
- Individual pages should not manually include branch selectors.
- Record Filtering checkbox is saved in branch module setting metadata.
- `shouldFilterRecords()` requires a usable selected branch, `record_filtering_enabled`, branch ID support, module enabled, and readiness.
- `applyScope()` returns unchanged when filtering is off.
- `applyRelatedScope()` filters only when current and related modules are both record-filtered.
- Voucher global + `customer_payments` branch-wise must show all eligible payments.

## Release Gate

Do not release until the checklist in [Release Safety Checklist](docs/RELEASE_SAFETY_CHECKLIST.md) and the smoke tests in [Manual Browser Tests](docs/MANUAL_BROWSER_TESTS.md) pass.
