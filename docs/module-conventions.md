# Module Conventions

Every GarmentsOS PRO module should be understandable from its routes, controller, model, views, JavaScript, permissions, feature flag, and release ownership.

## Standard Module Files

Use this shape when practical:

- routes in `routes/web.php`
- controller in `app/Http/Controllers`
- model in `app/Models`
- views in `resources/views/{module}`
- page JavaScript in `public/js/pages/{module}-*.js`
- migrations in `database/migrations`
- tests in `tests/Feature` or `tests/Unit`

## Routes

- Prefer explicit routes or `Route::resource(...)->only([...])`.
- Do not expose blank `show`, `edit`, `update`, or `destroy` methods.
- Name routes consistently with Laravel resource names.
- Add AJAX/helper endpoints near the module route group.
- Future module route groups should use feature middleware, for example `feature:shipments`.

## Controllers

Each action should:

- enforce role/permission rules server-side
- validate input before writes
- keep business rules local or call a service when logic is shared
- return consistent JSON for AJAX requests
- redirect with clear flash messages for form requests
- avoid silent blank responses

## Models

Models should define:

- `$fillable`
- `$casts` for dates, booleans, decimals, arrays
- relationships used by controllers/views
- computed formatting only when it is presentation-safe and already follows local patterns

## Validation

Current app style uses `$request->validate()` and `Validator::make()`. For new complex modules, prefer FormRequest later, but do not mix patterns inside a small change unless approved.

Validate:

- required IDs with `exists`
- dates
- numeric amounts and quantities
- file type and size
- enum-like fields such as invoice type, voucher type, production type

## Permissions

Do not rely on sidebar visibility. Routes and controller actions must enforce access.

Use the current role names carefully:

- developer
- owner
- manager
- admin
- accountant
- guest
- store_keeper
- customer
- supplier

Document any new role decision in `docs/route-permission-feature-matrix.md`.

## Feature Flags

Use feature flags for access control, not code removal.

Examples:

- `shipments`
- `invoices`
- `payment_programs`
- `reports`
- `backups`

Feature middleware should be applied only after dependency checks.

## Labels

Use labels for client wording differences. Example: Article can become Design without renaming database tables or models.

Do not rename domain classes just for wording. Use label configuration for UI text.

## Sidebar And Menu

Sidebar entries should eventually be generated from a central module/menu definition. Until then:

- keep labels consistent with route names
- hide menus only after route protection exists
- do not hide a feature while leaving critical linked AJAX routes unguarded

## Reports, Print, And Export

Reports should document:

- source tables
- filters
- roles
- feature flag
- print/export output
- client label impact

Print views should avoid hardcoded client assets beyond `client_company` configuration.

## Uploads

New code must not write directly to `public/uploads`.

Preferred path is the Laravel public disk, currently `storage/app/public/uploads/images`, until external runtime storage is implemented.

Existing split upload roots are a production risk and must be handled in a migration phase.

## Release Packaging

For future module pruning, each module should declare:

- routes
- controllers
- models
- views
- JS files
- migrations
- public assets
- dependencies

Feature flags hide access; module manifests control package inclusion.
