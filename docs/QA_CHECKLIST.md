# QA Checklist (Quick)

Use this checklist after changes to verify the app remains stable.

## 1) Auth & Session
- Login works for active users.
- Inactive users are blocked as expected.
- Logout works normally and in read‑only mode.

## 2) Read‑Only Mode
- Expired subscription shows warning.
- All write actions are blocked (create/update/delete/mark paid).
- Read‑only POST actions still work:
  - layout toggle, report type toggles, get details, etc.

## 3) Global UI
- Sidebar renders and active nav highlights correctly.
- Menu modal opens with `Ctrl + Space` and keyboard navigation works.
- Home shortcut `Shift + Space` routes to home.

## 4) Core CRUD Flows
- Customers, Suppliers, Orders, Payments, Vouchers can be created and edited.
- Select inputs show selected text in edit modals.
- Form validation and alerts show correctly.

## 5) Reports
- Statement, Pending Payments, and Article reports load.
- Filters apply without breaking layout.

## 6) Performance
- Only the current page’s JS is loaded.
- No console errors on initial load.

## 7) Security
- CSRF tokens present in forms.
- Read‑only middleware is active for web routes.

## 8) Release Package Safety
- Package was prepared using `docs/RELEASE_PACKAGING.md`.
- Package does not contain `.git/`, real `.env`, GitHub credentials, private keys, developer DB files, SQLite WAL/SHM files, dumps, backups, logs, tests, or dev-only files.
- Existing client `.env`, database, uploads, and backups are preserved during update testing.
- A database backup is created and verified before any update test.
- Managed backups are stored outside `public/` and downloaded only through developer/admin protected routes.
- Backup metadata and SHA-256 checksum are created and verification rejects invalid or tampered files.
- Restore is disabled by default unless `BACKUP_RESTORE_ENABLED=true`.
- Restore requires exact typed confirmation and staging/copy tested checkbox.
- Restore creates and verifies an emergency backup before replacing SQLite DB files.
- Restore rejects arbitrary paths and uses managed backup log IDs only.
- Restore is not wired into updater flow.

## 9) Licensing Foundation
- `LICENSE_ENFORCEMENT_ENABLED=false` keeps existing app behavior unchanged.
- Developer license status page loads for developer/admin users.
- Signed license cache tampering is rejected in tests.
- Installation fingerprint output is a hash/preview only, not raw machine details.
- LAN/browser PCs are not treated as separate licensed devices.
- Online activation stores only a license key hash, never the raw license key.
- Offline signed license import rejects tampered, UUID-mismatched, or fingerprint-mismatched payloads.
- Subscription refresh updates the signed cache only after signature validation.

## 10) Updater Foundation
- `UPDATER_ENABLED=false` keeps updater network checks disabled by default.
- Client package contains no GitHub tokens, deploy keys, private repo URLs, update secrets, or signing private keys.
- Update manifests require signature verification before trust.
- Update packages are checksum/signature verified before future apply.
- Update package verification rejects `.env`, database files, WAL/SHM files, backups, logs, private storage, Git metadata, secrets, absolute paths, and path traversal.
- Phase 4A has no apply/install route and no automatic update behavior.

## 11) Developer Settings Foundation
- Missing settings keep existing labels, branding, modules, and feature behavior unchanged.
- Developer/admin users can view the settings screen; other roles cannot change settings.
- Label overrides accept plain text only, reject HTML, can be reset, and invalidate cache.
- Sidebar Article labels use safe fallback labels when no override exists.
- Route blocking is currently enforced only for `articles`, `customers`, and `suppliers`.
- Disabled Articles, Customers, and Suppliers are hidden in both mobile and desktop sidebar menus.
- Shared routes and finance/order/stock/report modules are not route-blocked until their route maps are reviewed in later phases.
- Feature flag middleware exists as foundation; do not wire it to business workflows without a separate review.
- License-disallowed modules/features remain blocked even if local developer settings are enabled.
- Missing local settings or missing license restriction lists preserve current staged behavior.
