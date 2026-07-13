# Copilot Instructions

This is the GarmentsOS PRO repo. Read [AGENTS.md](../AGENTS.md) and the docs under `docs/` before suggesting code.

Important project rules:

- Do not suggest rewriting already-run migrations.
- Use forward-only migrations.
- Preserve existing data and old document numbers.
- Preserve existing UI style and layout behavior.
- Do not make `main-child` scrollable.
- Do not use browser `alert()` in new code.
- Normal CRUD feedback should use toast only.
- Branch filtering must follow `ModuleBranchService`.
- Related dropdowns filter only when current and related modules are both record-filtered.
- Global branch selector belongs in `app.blade.php`, not individual pages.

Do not recommend release/tag actions until manual smoke tests pass.
