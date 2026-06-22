# Developer Settings

Phase 5A adds a developer/admin settings foundation for safe local customization.

## Scope
- Database overrides are additive and stored in new settings tables only.
- Missing settings preserve existing behavior through config defaults and fallback helpers.
- Label overrides are plain text and rendered with escaped Blade output.
- Phase 5A introduced sidebar Article label overrides as the first runtime proof integration.
- Phase 5B enforces only the `articles` module as the first module visibility and route-blocking proof.
- Other modules may be listed in settings but are not route-blocked yet.
- Sidebar hiding is not security by itself; direct URL access is blocked by `moduleEnabled:articles` middleware for the Article proof routes.

## Not Implemented In Phase 5A
- Full module route blocking.
- Feature flag enforcement across business workflows.
- Logo upload or file-based branding assets.
- Multi-brand or branch scoping.
- Updater apply/install integration.

## Safety Rules
- Do not store secrets, tokens, passwords, private keys, `.env` values, or database credentials in settings.
- Settings writes reject common secret-looking values before persistence.
- Do not use label overrides for HTML or JavaScript.
- Settings writes are limited to developer/admin users and protected by normal web auth and CSRF middleware.
- Setting changes are audited through sanitized audit logs.
- Module enforcement must expand module-by-module only after each route map is reviewed.

## Future Work
- Add explicit module/feature enforcement middleware only after a separate review.
- Add logo upload only with strict file validation, private staging, and rollback.
- Add brand/branch scoped settings only in the later multi-brand phase.
