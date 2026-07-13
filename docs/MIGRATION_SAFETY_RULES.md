# Migration Safety Rules

## Hard Rules

- Never rewrite already-run migrations.
- Use forward-only migrations for new schema/data changes.
- Preserve old data.
- Do not destroy, rename, or silently rewrite production data.
- Repair/backfill must not overwrite Developer settings.
- If a migration is pending, do not run it unless explicitly asked.

## Current Branch Migration Notes

- `2026_07_13_000002_add_branch_id_to_remaining_branch_configurable_tables.php` adds missing `branch_id` columns only when absent.
- It backfills old rows safely to Main Branch.
- It keeps branch ownership data in `down()`.
- Current migration status says all migrations are `Ran`.

## Known Migration Risk

`2026_07_11_000006_complete_branch_module_registry_and_dispatch_scope.php` appears untracked while migration status says it ran. Before commit/release, ensure the exact migration file that ran is included.
