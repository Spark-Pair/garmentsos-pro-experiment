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
| daily ledger | `daily-ledger.index/create/store` | `DailyLedgerController` | authenticated staff route group | daily_ledger | daily_ledger | show/edit/update/destroy removed because methods are blank | ready |
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
| AJAX helpers | `get-*`, `set-*` | base `Controller` | authenticated route group; mixed validation | varies | varies | map each helper to module before feature guards | risky |

## Immediate Matrix Tasks

- Keep resource routes restricted to implemented actions with `only([...])`.
- Add feature keys for modules not yet represented if needed.
- Map every AJAX helper to a module and dependency list.
- Align sidebar entries with this matrix after route protection exists.
