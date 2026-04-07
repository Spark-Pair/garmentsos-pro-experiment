# Access Map (Baseline)

This file captures the current access rules so refactors keep behavior identical.

## Global Middleware
- `auth`, `activeSession`, `subscriptionExpiry`, `readonly` (web group)

## Role Checks (Controllers)
This project uses `checkRole([...])` in controllers. The authoritative list is in:
- `REPORT_RESTRICTIONS.md`
- `docs/ROLE_MATRIX.md` (generated snapshot)

If you need to change permissions later:
1. Update `checkRole([...])` in the relevant controller method(s).
2. Update UI visibility in `resources/views/components/sidebar.blade.php` (role-based menu items).
3. Keep the two in sync.

## Notes
- Policies/Gates are not used yet (`app/Providers/AuthServiceProvider.php` is empty).
- When we modularize roles, this file will be replaced by a single config-based map.
- Read-only behavior is documented in `docs/READONLY_MODE.md`.
