# Permissions & Role Updates

This app uses role checks in controllers (server‑side) and hides UI links in the sidebar (client‑side). To change permissions, update both.

## How Permissions Work
- Server‑side access is enforced via `checkRole([...])` usage in controllers.
- UI visibility is enforced in `resources/views/components/sidebar.blade.php`.

## Change Access for a Role (Step‑by‑Step)
1. Update controller role checks:
   - Use `docs/ROLE_MATRIX.md` to locate the controller and method.
   - Edit the role list in the controller for that method.
2. Update sidebar visibility:
   - Edit `resources/views/components/sidebar.blade.php` to show/hide menu items for the same roles.
3. Test the role in the UI.

## Notes
- Role checks are now standardized through `denyIfNoRole([...])` in `app/Http/Controllers/Controller.php`.
- Keep controller and sidebar changes in sync to avoid confusing UI states.
