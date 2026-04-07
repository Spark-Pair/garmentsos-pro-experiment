# Read‑Only Mode (Subscription Expiry)

When `session('readonly')` is true, all non‑GET requests are blocked except for explicitly allowed read‑only POST endpoints.

## Allowed Non‑GET Routes
These are the route names allowed during read‑only mode (data fetchers):
- `get-order-details`
- `get-category-data`
- `get-program-details`
- `get-shipment-details`
- `get-voucher-details`
- `get-payments-by-method`
- `get-employees-by-category`
- `get-utility-accounts`
- `sales-returns.get-details`
- `reports.statement.get-names`
- `payment-programs.get-summary`
- `change-data-layout`
- `set-invoice-type`
- `set-voucher-type`
- `set-production-type`
- `set-daily-ledger-type`
- `set-cr-type`
- `set-statement-type`
- `update-last-activity`
- `logout`

## Where It Is Enforced
- `app/Http/Middleware/ReadOnlyMode.php`
- Registered in `app/Http/Kernel.php` as `readonly`
- Applied to web routes in `routes/web.php`
- UI guard: `public/js/utils/readonly.js` disables non‑GET forms (logout is excluded).

## How To Change Allowed Actions
1. Add or remove route names from `$allowedRouteNames` in `ReadOnlyMode`.
2. Ensure UI buttons/forms for write actions are disabled in `resources/views/app.blade.php` (read‑only banner + JS form guard).
3. Keep behavior consistent across controllers; do not bypass middleware.
