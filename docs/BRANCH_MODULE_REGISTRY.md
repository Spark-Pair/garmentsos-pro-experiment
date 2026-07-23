# Branch Module Registry

The Dynamic Branch Module Registry currently contains 51 modules.

## Current Module List

- articles
- fabrics
- rates
- setup
- setups
- attendances
- bilties
- cr
- cargos
- dr
- shipments
- dashboard
- home
- notifications
- developer_audit_logs
- developer_backups
- developer_branches
- developer_license
- developer_settings
- developer_updater
- first_run_setup
- auth_login
- subscription_expired
- updating
- bank_accounts
- daily_ledger
- employee_payments
- expenses
- utility_accounts
- utility_bills
- vouchers
- customers
- employees
- suppliers
- users
- inventory
- physical_quantities
- productions
- reports_article
- reports_pending_payments
- permissions_report
- reports_physical_quantity
- reports
- reports_statement
- customer_payments
- invoices
- orders
- payment_programs
- sales_returns
- statement_adjustments
- supplier_payments

## Verified Route Mappings

- `orders.index -> orders`
- `payment-programs.index -> payment_programs`
- `reports.article -> reports_article`
- `reports.statement -> reports_statement`
- `reports.pending-payments -> reports_pending_payments`
- `reports.physical-quantity -> reports_physical_quantity`
- `bank-accounts.index -> bank_accounts`
- `cargos.index -> cargos`
- `vouchers.index -> vouchers`
- `add-rate -> null`
- `module-branch-preferences.store -> null`

## Ignored Internal Routes

Internal/utility routes are not branch modules. Examples:

- `module-branch-preferences`
- `branch-logos`
- `change-data-layout`
- `update-theme`
- `update-user-status`
- `api/user`
- `_ignition/*`

## Future Modules

Unknown real routes should appear as Detected / Needs Configuration so Developer can review branch behavior.
