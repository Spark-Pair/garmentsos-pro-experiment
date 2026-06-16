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
| users | `users.index/create/store` | `UserController` | index: developer, owner, manager, admin, accountant; create/store: developer, owner, manager, admin | users | none | desktop/mobile Add User hidden from accountant | ready |
| permission report | `permissions-report` | `PermissionReportController@index` | developer | users | none | developer-only audit | ready |
| suppliers | `suppliers.*` | `SupplierController` | varies by action | suppliers | supplier | possible Supplier -> Vendor label | needs-review |
| customers | `customers.*` | `CustomerController` | varies by action | customers | customer | possible Customer -> Party label | needs-review |
| articles | `articles.*` | `ArticleController` | varies by action | articles | article | possible Article -> Design label | needs-review |
| orders | `orders.*` | `OrderController` | staff plus customer portal for create/store/index | orders | order | depends on customers/articles | needs-review |
| shipments | `shipments.*` | `ShipmentController` | staff roles | shipments | shipment | can be disabled for some clients; invoice dependency must be reviewed | risky |
| invoices | `invoices.*`, `print-invoices` | `InvoiceController` | staff roles | invoices | invoice | depends on orders/shipments/customers/articles | needs-review |
| customer payments | `customer-payments.*` | `CustomerPaymentController` | developer, owner, admin, accountant | payments | payment | clear/split endpoints are write operations | ready |
| supplier payments | `supplier-payments.index` | `SupplierPaymentController@index` | developer, owner, admin, accountant | payments | payment | create/store/show/edit/update/destroy removed because methods are blank | ready |
| payment programs | `payment-programs.*` | `PaymentProgramController` | developer, owner, admin, accountant | payment_programs | payment_program | customer/supplier summaries | ready |
| bank accounts | `bank-accounts.*` | `BankAccountController` | index/create/store: developer, owner, admin, accountant; status/serial: developer, owner, admin | bank_accounts | bank_account | manager removed from hidden finance controls | ready |
| vouchers | `vouchers.*` | `VoucherController` | staff roles | vouchers | voucher | depends on payments/bank accounts/suppliers | needs-review |
| CR/DR | `cr.*`, `dr.*` | `CRController`, `DRController` | developer, owner, admin, accountant | cr_dr | cr, dr | payment integrations | ready |
| daily ledger | `daily-ledger.index/create/store` | `DailyLedgerController` | create/store: developer, owner, admin, accountant | daily_ledger | daily_ledger | show/edit/update/destroy removed because methods are blank; writes are readonly-blocked | ready |
| physical quantities | `physical-quantities.*` | `PhysicalQuantityController` | staff/store roles | stock | stock | report type preference | needs-review |
| sales returns | `sales-returns.*` | `SalesReturnController` | staff roles | sales_returns | sales_return | stock impact | needs-review |
| fabrics | `fabrics.*`, issue/return | `FabricController` | staff/store roles | fabrics | fabric | production dependency | needs-review |
| production | `productions.*` | `ProductionController` | staff/store/supplier index access | production | production | supplier portal visibility | needs-review |
| employees | `employees.*` | `EmployeeController` | staff roles | employees | employee | upload profile images | needs-review |
| attendance | `attendances.create/store` | `AttendanceController` | developer, owner, admin, manager | attendance | attendance | desktop and mobile menus show Record Attendance only to these roles | ready |
| payroll | `attendances.manage-salary`, `attendances.generate-slip` | `AttendanceController` | developer, owner, admin, accountant | attendance | attendance | desktop and mobile menus show salary/slip links only to these roles | ready |
| expenses | `expenses.*` | `ExpenseController` | staff plus supplier index access | expenses | expense | page titles need cleanup | needs-review |
| utilities | `utility-bills.*`, `utility-accounts.*` | utility controllers | staff/store roles | utilities | utility | bills marked paid via PUT | needs-review |
| logistics | `cargos.*`, `bilties.*` | cargo/bilty controllers | staff roles | logistics | cargo, bilty | resource blank actions should be reviewed | needs-review |
| reports | `reports/*` | `ReportController` | varies by report; portal statement access scoped by role | reports | report | reads many modules; high dependency risk | risky |
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
| `POST update-bank-account-status` | `BankAccountController@updateStatus` | bank account status toggle | developer, owner, admin | blocked | ready |
| `PUT/POST bank-accounts/{account}/update-serial` | `BankAccountController@updateSerial` | bank account display order | developer, owner, admin | blocked | ready |
| `POST fabrics/issuePost` | `FabricController@issuePost` | fabric stock issue | developer, owner, admin, accountant, store_keeper | blocked | ready |
| `POST fabrics/returnPost` | `FabricController@returnPost` | fabric stock return | developer, owner, admin, accountant, store_keeper | blocked | ready |
| `POST update-employee-status` | `EmployeeController@updateStatus` | employee status toggle | developer, owner, manager, admin | blocked | ready |
| `POST attendances/store` | `AttendanceController@store` | attendance record import/upsert | developer, owner, admin, manager | blocked | ready |
| `POST attendances/manage-salary` | `AttendanceController@manageSalaryPost` | salary adjustment | developer, owner, admin, accountant | blocked | ready |
| `POST attendances/generate-slip` | `AttendanceController@generateSlipPost` | salary slip generation | developer, owner, admin, accountant | blocked | ready |
| `PUT utility-bills/{utilityBill}/mark-paid` | `UtilityBillController@markPaid` | marks utility bill paid | developer, owner, admin, accountant | blocked | ready |
| `POST daily-ledger` | `DailyLedgerController@store` | creates daily cash ledger record | developer, owner, admin, accountant | blocked | ready |

## Controller/Menu Permission Alignment

Direct URL access must not be broader than desktop/mobile menu visibility for sensitive admin, finance, and payroll pages unless the broader access is an intentional portal behavior.

| Area | Routes | Decision | Notes | Status |
| --- | --- | --- | --- | --- |
| users | `users.index` | guest removed from direct controller access | menu already hides Users from guest; manager/accountant direct access remains aligned with Show Users visibility | ready |
| customer payments | `customer-payments.index` | guest and manager removed from direct controller access | finance page is visible only to staff finance roles in menus | ready |
| supplier payments | `supplier-payments.index` | guest and manager removed from direct controller access | route exposes supplier payment details through direct URL/AJAX | ready |
| payment programs | `payment-programs.index` | guest and manager removed from direct controller access | summaries/create/store already use narrower staff finance roles | ready |
| bank accounts | `bank-accounts.index` | guest and manager removed from direct controller access | bank account listing and controls are finance-sensitive | ready |
| vouchers | `vouchers.index` | guest removed from direct controller access | voucher listing is finance-sensitive | ready |
| employee payments | `employee-payments.index` | guest and manager removed from direct controller access | payroll/payment listing is sensitive | ready |
| CR/DR | `cr.index/create/store`, `dr.index/create/store` | guest and manager removed; index actions have explicit role gates | CR/DR menu is staff-finance only; writes remain readonly-blocked | ready |
| customer portal | `orders.*`, customer `reports/statement` | kept intentionally broader | customer role has explicit portal menu and controller scoping | needs-review |
| supplier portal | `expenses`, `productions`, supplier `reports/statement` | kept intentionally broader | supplier role has explicit portal menu and controller scoping in places | needs-review |
| reports | `reports/*` | not tightened in this pass | broad report access has record-level checks in places and needs a separate report-specific product decision | risky |
| manager finance reads | finance index/list routes | tightened where hidden from menu and clearly finance/payroll-sensitive | manager still keeps non-finance permissions such as users and attendance recording | ready |

## Report Permission Policy

Reports can expose cross-module finance, customer, supplier, stock, production, and payment data. Report routes must therefore be reviewed separately from ordinary index pages.

| Route | Action | Decision | Portal behavior | Status |
| --- | --- | --- | --- | --- |
| `GET reports/statement` | `ReportController@statement` | guest, manager, and store_keeper removed; finance staff/customer/supplier access retained | customer/supplier users are forced to their linked customer/supplier statement | ready |
| `POST reports/statement/get-names` | `ReportController@getNames` | guest, manager, and store_keeper removed; finance staff/customer/supplier access retained | customer/supplier users receive only their own linked name option | ready |
| `GET reports/statement/record-details` | `ReportController@statementRecordDetails` | guest, manager, and store_keeper removed; portal record types are restricted | customer can fetch only invoice/customer payment details; supplier can fetch only expense/voucher/supplier payment details | ready |
| `GET reports/pending-payments` | `ReportController@pendingPayments` | explicit role gate added: developer, owner, admin, accountant | no portal access; this is a finance-sensitive receivables report | ready |
| `GET reports/article` | `ReportController@article` | guest removed; staff/store roles retained | no customer/supplier portal access | ready |
| `GET reports/physical-quantity` | `ReportController@physicalQuantity` | guest removed; staff/store roles retained | no customer/supplier portal access | ready |
| `GET/POST statement-adjustments/*` | `StatementAdjustmentController` | already restricted to developer, owner, admin, accountant | no portal access | ready |
| report access for manager/store_keeper | `reports/article`, `reports/physical-quantity` | not tightened further in this pass | stock/report access may be operationally useful; owner decision needed before hiding direct URLs | needs-review |

## Manager/Store Keeper Direct Access Review

| Area | Decision | Notes | Status |
| --- | --- | --- | --- |
| hidden finance pages for manager | tightened | manager no longer direct-opens finance/payment/bank/voucher/CR/DR/employee payment pages that are hidden from the menu | ready |
| bank account status/serial writes for manager | tightened | hidden finance controls are now developer, owner, admin only | ready |
| finance statement routes for manager/store_keeper | tightened | statement, name lookup, and record-detail helpers now exclude manager/store_keeper unless portal customer/supplier rules apply | ready |
| stock pages for store_keeper | kept | articles, physical quantities, fabrics, and stock reports appear operationally intended for store_keeper | ready |
| production/fabric access | kept | current role policy suggests operational access; changing it needs owner decision | needs-review |
| article/physical quantity reports for manager/store_keeper | kept for now | data is read-only but may expose customer/order/stock details; owner should confirm final policy | needs-review |

## Immediate Matrix Tasks

- Keep resource routes restricted to implemented actions with `only([...])`.
- Add feature keys for modules not yet represented if needed.
- Keep readonly preference exceptions explicit and covered by tests.
- Align sidebar entries with this matrix after route protection exists.
