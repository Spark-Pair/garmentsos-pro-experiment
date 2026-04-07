# Project Structure

This guide explains where code lives and how to keep it modular and reusable.

## 1) Routes
- `routes/web.php` contains all web routes.
- Apply middleware here to ensure **permission, session, and readonly** checks.

## 2) Controllers
- Keep controllers thin: validation, authorization, and orchestration.
- Put heavy logic into **services** or **model methods**.
- Reuse common patterns by adding helper methods in `app/Http/Controllers/Controller.php`.

## 3) Views (Blade)
- Layout: `resources/views/app.blade.php`
- Shared components: `resources/views/components/*`
- Pages: `resources/views/<module>/*.blade.php`
- All page scripts belong in `@push('page-scripts')`.

## 4) JS Organization
- Global init: `public/js/app-init.js`
- Reusable utilities: `public/js/utils/*`
- UI components: `public/js/components/*`
- Page-specific logic: `public/js/pages/*`

## 5) Data Flow
- Every page reads from a `window.__pageConfig` object set in Blade.
- Page scripts read that config and run **only what they need**.

## 6) Modularity Rules
- Avoid duplicate logic in multiple pages.
- When the same behavior is needed on 2+ pages, move it into `public/js/utils/`.
- For shared UI, create a component and keep its JS in `public/js/components/`.

## 7) Permissions
- Permissions are enforced server-side via middleware and controller checks.
- UI elements can be hidden with Blade `@can`/`@if` to improve UX.

## 8) Read‑Only Mode
- Read-only mode is enforced by middleware.
- JS `initReadOnlyLock()` disables non‑GET forms at the UI level.

## 9) QA
- Use `docs/QA_CHECKLIST.md` after refactors or permission changes.
