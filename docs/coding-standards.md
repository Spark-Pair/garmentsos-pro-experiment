# Coding Standards

These standards are specific to GarmentsOS PRO. Follow existing app patterns unless a phase explicitly approves a refactor.

## Controllers

Controller actions should follow this order:

1. authorize role/permission
2. load required setup data
3. validate request
4. perform business operation
5. return view, JSON, or redirect

Example role guard:

```php
if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin'])) {
    return $resp;
}
```

Do not expose empty resource methods. If an action is not supported, remove the route with `only([...])` or return a deliberate 404/redirect in an approved phase.

## Validation

- Use server-side validation for every write.
- Keep client-side validation as convenience only.
- Validate IDs with `exists`.
- Validate file uploads with extension, MIME, and size rules.
- Validate requests before storing or deleting uploaded files.
- Use decimal-compatible validation for money/quantity fields where the business allows decimals.
- Finance writes must validate related IDs before writing and reject zero/negative amounts unless the workflow explicitly documents otherwise.

## Authorization

- Sidebar/menu checks are not authorization.
- AJAX endpoints need the same authorization care as page routes.
- Customer and supplier portal routes must restrict data to the logged-in customer/supplier.
- Reports must protect record-detail endpoints, not only the report landing page.

## Feature Flags

Use `FeatureManager` or `feature:{key}` middleware after a module dependency review.

Missing feature keys should remain disabled by default.

Do not apply feature guards only to sidebar links. Guard routes too.

## Labels

Use label configuration for client-specific wording:

- Supplier -> Vendor
- Customer -> Party
- Article -> Design
- Invoice -> Bill
- Shipment -> Delivery

Do not rename database columns, routes, or models for wording-only changes.

## Views

- Keep page views under `resources/views/{module}`.
- Use existing Blade components for inputs, cards, modals, search headers, and navigation.
- Keep titles accurate. Do not copy titles such as "Add Article" into unrelated modules.
- Avoid embedding large new business logic in Blade.

## JavaScript

- Put page-specific behavior under `public/js/pages`.
- Shared helpers belong under `public/js/utils` or `public/js/components`.
- Do not hardcode client names, secret values, or production paths.
- Keep print/export code explicit about columns and filters.

## Upload And Storage Rules

- New uploads should use Laravel storage APIs.
- Do not write new files to `public/uploads`.
- Treat `public/uploads` as a legacy runtime location only; preserve it until an approved migration, but do not add new writes there.
- Image update forms must preserve the existing filename when no replacement file is uploaded.
- Create-form upload field names and controller validation keys must match; compatibility aliases should be explicit when needed.
- Do not assume `public/storage` is valid without a readiness check.
- Do not move existing uploads without a backup and migration plan.

## Error Handling

- User-facing errors should be clear and safe.
- Do not expose filesystem paths, SQL details, or secrets.
- Server-side logs may contain operational details when useful.

## Production Safety

Never include these in release packages:

- `.env`
- database files
- WAL/SHM files
- uploads
- backups
- logs
- storage runtime data
- node_modules
- tests/docs/scripts unless explicitly intended for a developer artifact

## Common Current Risks To Avoid Repeating

- blank resource methods exposed by `Route::resource`
- role logic duplicated in Blade and controllers
- direct public upload writes
- hardcoded client wording
- broad AJAX endpoints without module feature mapping
- runtime files inside replaceable app folders
