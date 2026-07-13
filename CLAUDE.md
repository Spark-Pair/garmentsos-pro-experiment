# Claude Instructions

Follow [AGENTS.md](AGENTS.md) first. This repo is the GarmentsOS PRO repo.

Before editing, read:

- [Current Status](docs/CURRENT_STATUS.md)
- [Branch System](docs/BRANCH_SYSTEM.md)
- [Branch Filtering Rules](docs/BRANCH_FILTERING_RULES.md)
- [Migration Safety Rules](docs/MIGRATION_SAFETY_RULES.md)

Do not commit, push, tag, or release unless explicitly requested.

For this project:

- Prefer small, scoped changes.
- Do not rewrite migration history.
- Do not change application logic during documentation-only tasks.
- Preserve old document numbers and old data.
- Keep UI consistent with existing app components and layout.
- Use toast for normal CRUD feedback.
- Keep top-right notifications for real app/system notifications only.
- Use app modal style for update available prompts.
