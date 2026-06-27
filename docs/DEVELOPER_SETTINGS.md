# Developer Settings

Phase 5A adds a developer/admin settings foundation for safe local customization.

## Scope
- Database overrides are additive and stored in new settings tables only.
- Missing settings preserve existing behavior through config defaults and fallback helpers.
- Label overrides are plain text and rendered with escaped Blade output.
- Phase 5A introduced sidebar Article label overrides as the first runtime proof integration.
- Route blocking is currently enforced only for `articles`, `customers`, `suppliers`, reviewed pure `reports` routes, and direct `rates` management routes.
- Other modules may be listed in settings but are not route-blocked yet.
- Sidebar hiding is not security by itself; direct URL access is blocked by `moduleEnabled:*` middleware for reviewed module routes.
- License restrictions win over local settings when licensing is enabled and a license declares allowed modules/features.
- Local settings cannot enable a module or feature that is not included in the active license.
- Missing local settings or missing license restriction lists preserve current staged behavior.
- Branding overrides are applied only to safe text/color values in the app layout, sidebar, login, and home views.
- Branding fallback order is database override, `config/client_company.php`, `config/branding.php`, then a hardcoded safe fallback.

## Not Implemented In Phase 5A
- Full module route blocking.
- Feature flag enforcement across business workflows.
- Logo upload or file-based branding assets.
- Print template and JavaScript print-builder branding changes.
- Favicon, app icon, or manifest branding changes.
- Multi-brand or branch scoping.
- Updater apply/install integration.

## Safety Rules
- Do not store secrets, tokens, passwords, private keys, `.env` values, or database credentials in settings.
- Settings writes reject common secret-looking values before persistence.
- Branding settings never write to `.env`.
- Do not use label overrides for HTML or JavaScript.
- Branding text must be plain text, and branding colors must be strict `#RRGGBB` hex values.
- Logo upload and arbitrary logo path editing are not implemented; the app continues to use trusted configured logo assets.
- SparkPair developer branding has not been removed in this phase.
- Settings writes are limited to developer/admin users and protected by normal web auth and CSRF middleware.
- Setting changes are audited through sanitized audit logs.
- Module enforcement must expand module-by-module only after each route map is reviewed.
- Shared helper/session setter routes, `setups`, article `add-rate`, saved-rate usage, and finance/order/stock/ledger/report-adjacent workflows remain intentionally delayed until separate route-map reviews.
- No automatic global module lockdown has been added.

## Future Work
- Add explicit module/feature enforcement middleware only after a separate review.
- Add logo upload only with strict file validation, private staging, and rollback.
- Add brand/branch scoped settings only in the later multi-brand phase.
