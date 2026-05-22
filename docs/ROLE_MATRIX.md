# Role Matrix (Generated)

Each row is a controller method with its role check. This mirrors current `checkRole([...])` usage.

| Controller | Method | Roles |
|---|---|---|
| `app/Http/Controllers/ArticleController.php` | `addRate` | `developer, owner, admin, accountant, store_keeper` |
| `app/Http/Controllers/ArticleController.php` | `create` | `developer, owner, admin, accountant, store_keeper` |
| `app/Http/Controllers/ArticleController.php` | `edit` | `developer, owner, admin` |
| `app/Http/Controllers/ArticleController.php` | `index` | `developer, owner, manager, admin, accountant, guest, store_keeper` |
| `app/Http/Controllers/ArticleController.php` | `store` | `developer, owner, admin, accountant, store_keeper` |
| `app/Http/Controllers/ArticleController.php` | `update` | `developer, owner, admin` |
| `app/Http/Controllers/ArticleController.php` | `updateImage` | `developer, owner, admin, accountant, store_keeper` |
| `app/Http/Controllers/AttendanceController.php` | `create` | `developer, owner, manager, admin, accountant, guest` |
| `app/Http/Controllers/AttendanceController.php` | `manageSalary` | `developer, owner, manager, admin, accountant, guest` |
| `app/Http/Controllers/AttendanceController.php` | `manageSalaryPost` | `developer, owner, manager, admin, accountant, guest` |
| `app/Http/Controllers/AttendanceController.php` | `store` | `developer, owner, manager, admin, accountant, guest` |
| `app/Http/Controllers/BankAccountController.php` | `create` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/BankAccountController.php` | `index` | `developer, owner, manager, admin, accountant, guest` |
| `app/Http/Controllers/BankAccountController.php` | `store` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/BankAccountController.php` | `updateSerial` | `developer, owner, manager, admin` |
| `app/Http/Controllers/BankAccountController.php` | `updateStatus` | `developer, owner, manager, admin` |
| `app/Http/Controllers/CargoController.php` | `create` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/CargoController.php` | `index` | `developer, owner, manager, admin, accountant, guest` |
| `app/Http/Controllers/CargoController.php` | `store` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/CRController.php` | `create` | `developer, owner, manager, admin, accountant, guest` |
| `app/Http/Controllers/CRController.php` | `store` | `developer, owner, manager, admin, accountant, guest` |
| `app/Http/Controllers/CustomerController.php` | `create` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/CustomerController.php` | `edit` | `developer, owner, admin` |
| `app/Http/Controllers/CustomerController.php` | `index` | `developer, owner, manager, admin, accountant, guest` |
| `app/Http/Controllers/CustomerController.php` | `store` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/CustomerController.php` | `update` | `developer, owner, admin` |
| `app/Http/Controllers/CustomerPaymentController.php` | `clear` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/CustomerPaymentController.php` | `create` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/CustomerPaymentController.php` | `edit` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/CustomerPaymentController.php` | `index` | `developer, owner, manager, admin, accountant, guest` |
| `app/Http/Controllers/CustomerPaymentController.php` | `split` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/CustomerPaymentController.php` | `split` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/CustomerPaymentController.php` | `store` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/CustomerPaymentController.php` | `transfer` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/CustomerPaymentController.php` | `update` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/DRController.php` | `create` | `developer, owner, manager, admin, accountant, guest` |
| `app/Http/Controllers/DRController.php` | `store` | `developer, owner, manager, admin, accountant, guest` |
| `app/Http/Controllers/EmployeeController.php` | `edit` | `developer, owner, admin` |
| `app/Http/Controllers/EmployeeController.php` | `index` | `developer, owner, manager, admin, accountant, guest` |
| `app/Http/Controllers/EmployeeController.php` | `store` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/EmployeeController.php` | `update` | `developer, owner, admin` |
| `app/Http/Controllers/EmployeeController.php` | `updateStatus` | `developer, owner, manager, admin` |
| `app/Http/Controllers/EmployeePaymentController.php` | `create` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/EmployeePaymentController.php` | `index` | `developer, owner, manager, admin, accountant, guest` |
| `app/Http/Controllers/EmployeePaymentController.php` | `store` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/ExpenseController.php` | `create` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/ExpenseController.php` | `edit` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/ExpenseController.php` | `index` | `developer, owner, admin, accountant, guest` |
| `app/Http/Controllers/ExpenseController.php` | `store` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/ExpenseController.php` | `update` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/FabricController.php` | `create` | `developer, owner, admin, accountant, store_keeper` |
| `app/Http/Controllers/FabricController.php` | `index` | `developer, owner, manager, admin, accountant, guest, store_keeper` |
| `app/Http/Controllers/FabricController.php` | `issue` | `developer, owner, admin, accountant, store_keeper` |
| `app/Http/Controllers/FabricController.php` | `issuePost` | `developer, owner, admin, accountant, store_keeper` |
| `app/Http/Controllers/FabricController.php` | `return` | `developer, owner, admin, accountant, store_keeper` |
| `app/Http/Controllers/FabricController.php` | `returnPost` | `developer, owner, admin, accountant, store_keeper` |
| `app/Http/Controllers/FabricController.php` | `store` | `developer, owner, admin, accountant, store_keeper` |
| `app/Http/Controllers/InvoiceController.php` | `create` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/InvoiceController.php` | `index` | `developer, owner, manager, admin, accountant, guest` |
| `app/Http/Controllers/InvoiceController.php` | `store` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/InvoiceController.php` | `store` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/OrderController.php` | `create` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/OrderController.php` | `index` | `developer, owner, manager, admin, accountant, guest` |
| `app/Http/Controllers/OrderController.php` | `store` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/OrderController.php` | `update` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/PaymentProgramController.php` | `create` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/PaymentProgramController.php` | `CustomerSummary` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/PaymentProgramController.php` | `index` | `developer, owner, manager, admin, accountant, guest` |
| `app/Http/Controllers/PaymentProgramController.php` | `markPaid` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/PaymentProgramController.php` | `store` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/PaymentProgramController.php` | `SupplierSummary` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/PaymentProgramController.php` | `updateProgram` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/PermissionReportController.php` | `index` | `developer` |
| `app/Http/Controllers/PhysicalQuantityController.php` | `create` | `developer, owner, admin, accountant, store_keeper` |
| `app/Http/Controllers/PhysicalQuantityController.php` | `index` | `developer, owner, manager, admin, accountant, guest, store_keeper` |
| `app/Http/Controllers/PhysicalQuantityController.php` | `store` | `developer, owner, admin, accountant, store_keeper` |
| `app/Http/Controllers/ProductionController.php` | `create` | `developer, owner, manager, admin, accountant, guest, store_keeper` |
| `app/Http/Controllers/ProductionController.php` | `index` | `developer, owner, manager, admin, accountant, guest, store_keeper` |
| `app/Http/Controllers/ProductionController.php` | `store` | `developer, owner, manager, admin, accountant, guest, store_keeper` |
| `app/Http/Controllers/RateController.php` | `create` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/RateController.php` | `store` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/ReportController.php` | `article` | `developer, owner, manager, admin, accountant, guest, store_keeper` |
| `app/Http/Controllers/SalesReturnController.php` | `store` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/SetupController.php` | `create` | `developer, owner, admin, accountant, store_keeper` |
| `app/Http/Controllers/SetupController.php` | `index` | `developer, owner, admin, accountant, store_keeper` |
| `app/Http/Controllers/SetupController.php` | `store` | `developer, owner, admin, accountant, store_keeper` |
| `app/Http/Controllers/ShipmentController.php` | `create` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/ShipmentController.php` | `edit` | `developer, owner, admin` |
| `app/Http/Controllers/ShipmentController.php` | `index` | `developer, owner, manager, admin, accountant, guest` |
| `app/Http/Controllers/ShipmentController.php` | `store` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/ShipmentController.php` | `update` | `developer, owner, admin` |
| `app/Http/Controllers/SupplierController.php` | `create` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/SupplierController.php` | `edit` | `developer, owner, admin` |
| `app/Http/Controllers/SupplierController.php` | `index` | `developer, owner, manager, admin, accountant, guest` |
| `app/Http/Controllers/SupplierController.php` | `store` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/SupplierController.php` | `update` | `developer, owner, admin` |
| `app/Http/Controllers/SupplierController.php` | `updateSupplierCategory` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/SupplierPaymentController.php` | `index` | `developer, owner, manager, admin, accountant, guest` |
| `app/Http/Controllers/UserController.php` | `create` | `developer, owner, manager, admin` |
| `app/Http/Controllers/UserController.php` | `index` | `developer, owner, manager, admin, accountant, guest` |
| `app/Http/Controllers/UserController.php` | `resetPassword` | `developer, owner, admin` |
| `app/Http/Controllers/UserController.php` | `store` | `developer, owner, manager, admin` |
| `app/Http/Controllers/UserController.php` | `updateStatus` | `developer, owner, manager, admin` |
| `app/Http/Controllers/UtilityAccountController.php` | `create` | `developer, owner, admin, accountant, store_keeper` |
| `app/Http/Controllers/UtilityAccountController.php` | `index` | `developer, owner, admin, accountant, store_keeper` |
| `app/Http/Controllers/UtilityAccountController.php` | `store` | `developer, owner, admin, accountant, store_keeper` |
| `app/Http/Controllers/UtilityBillController.php` | `create` | `developer, owner, admin, accountant, store_keeper` |
| `app/Http/Controllers/UtilityBillController.php` | `index` | `developer, owner, admin, accountant, store_keeper` |
| `app/Http/Controllers/UtilityBillController.php` | `markPaid` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/UtilityBillController.php` | `store` | `developer, owner, admin, accountant, store_keeper` |
| `app/Http/Controllers/VoucherController.php` | `create` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/VoucherController.php` | `edit` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/VoucherController.php` | `index` | `developer, owner, manager, admin, accountant, guest` |
| `app/Http/Controllers/VoucherController.php` | `store` | `developer, owner, admin, accountant` |
| `app/Http/Controllers/VoucherController.php` | `update` | `developer, owner, admin, accountant` |