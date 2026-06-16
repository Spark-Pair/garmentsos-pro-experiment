# Route Permission Feature Matrix

This is a living matrix. It starts with high-risk and obvious routes. Fill it as modules are hardened.

Status values:

- ready
- incomplete
- risky
- needs-review

| Module | Route/path | Controller action | Roles/permissions | Feature flag | Label keys | Client-specific notes | Status |
| --- | --- | --- | --- | --- | --- | --- | --- |
| auth | `GET login` | `AuthController@login` | guest | core | none | global login | ready |
| auth | `POST login` | `AuthController@loginPost` | guest | core | none | subscription expiry middleware applies | ready |
| home | `GET home` | `Controller@home` | authenticated | dashboard_cards later | dashboard | utility reminder visible | needs-review |
| backups | `GET /backup-db` | `BackupController@downloadDatabase` | developer, admin | backups | none | WAL-safe snapshot route | ready |
| users | `users.index/create/store` | `UserController` | developer, owner, manager, admin for create/store | users | none | reset/status endpoints separate | needs-review |
| permission report | `permissions-report` | `PermissionReportController@index` | developer | users | none | developer-only audit | ready |
| suppliers | `suppliers.*` | `SupplierController` | varies by action | suppliers | supplier | possible Supplier -> Vendor label | needs-review |
| customers | `customers.*` | `CustomerController` | varies by action | customers | customer | possible Customer -> Party label | needs-review |
| articles | `articles.*` | `ArticleController` | varies by action | articles | article | possible Article -> Design label | needs-review |
| orders | `orders.*` | `OrderController` | staff plus customer portal for create/store/index | orders | order | depends on customers/articles | needs-review |
| shipments | `shipments.*` | `ShipmentController` | staff roles | shipments | shipment | can be disabled for some clients; invoice dependency must be reviewed | risky |
| invoices | `invoices.*`, `print-invoices` | `InvoiceController` | staff roles | invoices | invoice | depends on orders/shipments/customers/articles | needs-review |
| customer payments | `customer-payments.*` | `CustomerPaymentController` | staff roles | payments | payment | clear/split endpoints are write operations | needs-review |
| supplier payments | `supplier-payments.index` | `SupplierPaymentController@index` | staff roles | payments | payment | create/store/show/edit/update/destroy removed because methods are blank | ready |
| payment programs | `payment-programs.*` | `PaymentProgramController` | staff roles | payment_programs | payment_program | customer/supplier summaries | needs-review |
| bank accounts | `bank-accounts.*` | `BankAccountController` | staff roles | bank_accounts | bank_account | serial/status endpoints | needs-review |
| vouchers | `vouchers.*` | `VoucherController` | staff roles | vouchers | voucher | depends on payments/bank accounts/suppliers | needs-review |
| CR/DR | `cr.*`, `dr.*` | `CRController`, `DRController` | staff roles | cr_dr | cr, dr | payment integrations | needs-review |
| daily ledger | `daily-ledger.index/create/store` | `DailyLedgerController` | create/store: developer, owner, admin, accountant | daily_ledger | daily_ledger | show/edit/update/destroy removed because methods are blank; writes are readonly-blocked | ready |
| physical quantities | `physical-quantities.*` | `PhysicalQuantityController` | staff/store roles | stock | stock | report type preference | needs-review |
| sales returns | `sales-returns.*` | `SalesReturnController` | staff roles | sales_returns | sales_return | stock impact | needs-review |
| fabrics | `fabrics.*`, issue/return | `FabricController` | staff/store roles | fabrics | fabric | production dependency | needs-review |
| production | `productions.*` | `ProductionController` | staff/store/supplier index access | production | production | supplier portal visibility | needs-review |
| employees | `employees.*` | `EmployeeController` | staff roles | employees | employee | upload profile images | needs-review |
| attendance | `attendances/*` | `AttendanceController` | broad staff roles | attendance | attendance | salary slip flow | needs-review |
| expenses | `expenses.*` | `ExpenseController` | staff plus supplier index access | expenses | expense | page titles need cleanup | needs-review |
| utilities | `utility-bills.*`, `utility-accounts.*` | utility controllers | staff/store roles | utilities | utility | bills marked paid via PUT | needs-review |
| logistics | `cargos.*`, `bilties.*` | cargo/bilty controllers | staff roles | logistics | cargo, bilty | resource blank actions should be reviewed | needs-review |
| reports | `reports/*` | `ReportController` | broad roles, record-level checks in places | reports | report | reads many modules; high dependency risk | risky |
| AJAX helpers | `get-*`, `set-*` | base `Controller` | authenticated route group; mixed validation | varies | varies | active helpers remain grouped under auth/readonly/dbTransaction; dead helpers removed | needs-review |

## Helper/AJAX Endpoint Permissions

These routes are authenticated and remain inside the shared `readonly` and `dbTransaction` middleware group unless noted otherwise.

Readonly policy:

- Core business writes remain blocked in readonly mode.
- Data-fetching POST helpers are allowed only when explicitly listed in `ReadOnlyMode`.
- Safe UI/report preference writes are allowed only when explicitly listed in `ReadOnlyMode`.
- Dead or missing routes must not remain in the readonly allowlist.

| Endpoint | Controller action | Type | Current protection | Notes | Status |
| --- | --- | --- | --- | --- | --- |
| `POST get-order-details` | `Controller@getOrderDetails` | read helper | auth + readonly allowlist | invoice flow dependency | needs-review |
| `POST get-category-data` | `Controller@getCategoryData` | read helper | auth + readonly allowlist | used by payment programs and bank accounts | needs-review |
| `POST get-program-details` | `Controller@getProgramDetails` | read helper | auth + readonly allowlist | payment program dependency | needs-review |
| `POST get-shipment-details` | `Controller@getShipmentDetails` | read helper | auth + readonly allowlist | invoice flow dependency | needs-review |
| `POST get-voucher-details` | `Controller@getVoucherDetails` | read helper | auth + readonly allowlist | CR flow dependency | needs-review |
| `POST get-employees-by-category` | `Controller@getEmployeesByCategory` | read helper | auth + readonly allowlist | employee payment dependency | needs-review |
| `POST get-utility-accounts` | `Controller@getUtilityAccounts` | read helper | auth + readonly allowlist | utility bill dependency | needs-review |
| `POST change-data-layout` | `Controller@changeDataLayout` | safe preference update | auth + readonly allowlist | updates `users.layout` only | ready |
| `POST update-theme` | `AuthController@updateTheme` | safe preference update | auth + readonly allowlist | updates `users.theme` only | ready |
| `POST update-menu-shortcuts` | `AuthController@updateMenuShortcuts` | safe preference update | auth + readonly allowlist | updates `users.menu_shortcuts` only | ready |
| `POST set-invoice-type` | `Controller@setInvoiceType` | safe preference update | auth + readonly allowlist | updates `users.invoice_type` only | ready |
| `POST set-voucher-type` | `Controller@setVoucherType` | safe preference update | auth + readonly allowlist | updates `users.voucher_type` only | ready |
| `POST set-production-type` | `Controller@setProductionType` | safe preference update | auth + readonly allowlist | updates `users.production_type` only | ready |
| `POST set-daily-ledger-type` | `Controller@setDailyLedgerType` | safe preference update | auth + readonly allowlist | updates `users.daily_ledger_type` only | ready |
| `POST set-statement-type` | `Controller@setStatementType` | safe preference update | auth + readonly allowlist | updates `users.statement_type` only | ready |
| `POST set-physical-quantity-report-type` | `Controller@setPhysicalQuantityReportType` | safe preference update | auth + readonly allowlist | updates `users.physical_quantity_report_type` only | ready |
| `POST get-payments-by-method` | missing `Controller@getPaymentsByMethod` | dead helper | removed | route pointed to a missing method and had no UI references | ready |
| `POST set-cr-type` | missing `Controller@setCRType` | dead helper | removed | route pointed to a missing method and had no UI references | ready |

## Sensitive Business Write Endpoints

Custom and high-sensitivity writes remain inside the authenticated `readonly` and `dbTransaction` middleware group. Core business writes must also have an explicit controller role gate.

| Endpoint | Controller action | Business change | Role protection | Readonly behavior | Status |
| --- | --- | --- | --- | --- | --- |
| `POST update-user-status` | `UserController@updateStatus` | activates/deactivates users | developer, owner, manager, admin | blocked | ready |
| `POST users.reset-password` | `UserController@resetPassword` | resets user passwords and sessions | developer, owner, admin | blocked | ready |
| `POST update-supplier-category` | `SupplierController@updateSupplierCategory` | supplier category changes | developer, owner, admin, accountant | blocked | ready |
| `POST update-image` | `ArticleController@updateImage` | article image change | developer, owner, admin, accountant, store_keeper | blocked | ready |
| `POST add-rate` | `ArticleController@addRate` | article rate creation | developer, owner, admin, accountant, store_keeper | blocked | ready |
| `POST customer-payments/{id}/clear` | `CustomerPaymentController@clear` | creates payment clear record | developer, owner, admin, accountant | blocked | ready |
| `POST customer-payments/{payment}/split` | `CustomerPaymentController@split` | splits payment records | developer, owner, admin, accountant | blocked | ready |
| `POST payment-programs.update-program` | `PaymentProgramController@updateProgram` | payment program update | developer, owner, admin, accountant | blocked | ready |
| `POST payment-programs/{id}/mark-paid` | `PaymentProgramController@markPaid` | marks program paid | developer, owner, admin, accountant | blocked | ready |
| `POST update-bank-account-status` | `BankAccountController@updateStatus` | bank account status toggle | developer, owner, manager, admin | blocked | ready |
| `PUT/POST bank-accounts/{account}/update-serial` | `BankAccountController@updateSerial` | bank account display order | developer, owner, manager, admin | blocked | ready |
| `POST fabrics/issuePost` | `FabricController@issuePost` | fabric stock issue | developer, owner, admin, accountant, store_keeper | blocked | ready |
| `POST fabrics/returnPost` | `FabricController@returnPost` | fabric stock return | developer, owner, admin, accountant, store_keeper | blocked | ready |
| `POST update-employee-status` | `EmployeeController@updateStatus` | employee status toggle | developer, owner, manager, admin | blocked | ready |
| `POST attendances/manage-salary` | `AttendanceController@manageSalaryPost` | salary adjustment | developer, owner, manager, admin, accountant, guest | blocked | needs-review |
| `POST attendances/generate-slip` | `AttendanceController@generateSlipPost` | salary slip generation | route group + controller flow | blocked | needs-review |
| `PUT utility-bills/{utilityBill}/mark-paid` | `UtilityBillController@markPaid` | marks utility bill paid | developer, owner, admin, accountant | blocked | ready |
| `POST daily-ledger` | `DailyLedgerController@store` | creates daily cash ledger record | developer, owner, admin, accountant | blocked | ready |

## Immediate Matrix Tasks

- Keep resource routes restricted to implemented actions with `only([...])`.
- Add feature keys for modules not yet represented if needed.
- Keep readonly preference exceptions explicit and covered by tests.
- Align sidebar entries with this matrix after route protection exists.
