<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\BiltyController;
use App\Http\Controllers\CargoController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CRController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerPaymentController;
use App\Http\Controllers\DailyLedgerController;
use App\Http\Controllers\DRController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeePaymentController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FabricController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PhysicalQuantityController;
use App\Http\Controllers\PaymentProgramController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\RateController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\PermissionReportController;
use App\Http\Controllers\SalesReturnController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\StatementAdjustmentController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierPaymentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UtilityAccountController;
use App\Http\Controllers\UtilityBillController;
use App\Http\Controllers\VoucherController;
use Illuminate\Support\Facades\Route;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('login', [AuthController::class, 'login'])->name('login');
Route::post('login', [AuthController::class, 'loginPost'])->middleware('subscriptionExpiry')->name('loginPost');
Route::get('subscription-expired', function () {
    return view('subscription-expired'); // ya controller agar chahiye
})->name('subscription-expired');

Route::group(['middleware' => ['auth', 'activeSession', 'subscriptionExpiry', 'readonly', 'dbTransaction']], function () {
    Route::get('/backup-db', [BackupController::class, 'downloadDatabase']);

    Route::get('', function () {
        return redirect(route('home'));
    });

    Route::get('home', [Controller::class, 'home'])->name('home');

    Route::resource('users', UserController::class)->only(['index', 'create', 'store']);
    Route::post('update-user-status', [UserController::class, 'updateStatus'])->name('update-user-status');
    Route::post('users.reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

    Route::post('update-theme', [AuthController::class, 'updateTheme'])->name('update-theme');

    Route::resource('setups', SetupController::class)->only(['index', 'create', 'store']);
    Route::get('permissions-report', [PermissionReportController::class, 'index'])->name('permissions-report');

    Route::resource('suppliers', SupplierController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::post('update-supplier-category', [SupplierController::class, 'updateSupplierCategory'])->name('update-supplier-category');

    Route::resource('customers', CustomerController::class)->only(['index', 'create', 'store', 'edit', 'update']);

    Route::resource('articles', ArticleController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::post('update-image', [ArticleController::class, 'updateImage'])->name('update-image');
    Route::post('add-rate', [ArticleController::class, 'addRate'])->name('add-rate');

    Route::resource('orders', OrderController::class)->only(['index', 'create', 'store', 'edit', 'update']);

    Route::resource('shipments', ShipmentController::class)->only(['index', 'create', 'store', 'edit', 'update']);

    Route::resource('physical-quantities', PhysicalQuantityController::class)->only(['index', 'create', 'store']);

    Route::resource('invoices', InvoiceController::class)->only(['index', 'create', 'store']);
    Route::get('print-invoices', [InvoiceController::class, 'print'])->name('invoices.print');

    Route::resource('customer-payments', CustomerPaymentController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::post('customer-payments/{id}/clear', [CustomerPaymentController::class, 'clear'])->name('customer-payments.clear');
    Route::post('customer-payments/{payment}/split', [CustomerPaymentController::class, 'split'])->name('customer-payments.split');

    Route::resource('supplier-payments', SupplierPaymentController::class)->only(['index']);

    Route::get('payment-programs/customer-summary', [PaymentProgramController::class, 'CustomerSummary'])->name('payment-programs.customer-summary');
    Route::get('payment-programs/supplier-summary', [PaymentProgramController::class, 'SupplierSummary'])->name('payment-programs.supplier-summary');
    Route::resource('payment-programs', PaymentProgramController::class)->only(['index', 'create', 'store']);
    Route::post('payment-programs.update-program', [PaymentProgramController::class, 'updateProgram'])->name('payment-programs.update-program');
    Route::post('payment-programs/{id}/mark-paid', [PaymentProgramController::class, 'markPaid'])->name('payment-programs.mark-paid');

    Route::resource('bank-accounts', BankAccountController::class)->only(['index', 'create', 'store']);
    Route::post('update-bank-account-status', [BankAccountController::class, 'updateStatus'])->name('update-bank-account-status');
    Route::put('bank-accounts/{account}/update-serial', [BankAccountController::class, 'updateSerial'])->name('bank-accounts.update-serial');
    Route::post('bank-accounts/{account}/update-serial', [BankAccountController::class, 'updateSerial'])->name('bank-accounts.update-serial-post');

    Route::resource('cargos', CargoController::class)->only(['index', 'create', 'store']);

    Route::resource('bilties', BiltyController::class)->only(['index', 'create', 'store']);

    Route::resource('expenses', ExpenseController::class)->only(['index', 'create', 'store', 'edit', 'update']);

    Route::resource('vouchers', VoucherController::class)->only(['index', 'create', 'store', 'edit', 'update']);

    Route::get('fabrics/issue', [FabricController::class, 'issue'])->name('fabrics.issue');
    Route::post('fabrics/issuePost', [FabricController::class, 'issuePost'])->name('fabrics.issuePost');
    Route::get('fabrics/return', [FabricController::class, 'return'])->name('fabrics.return');
    Route::post('fabrics/returnPost', [FabricController::class, 'returnPost'])->name('fabrics.returnPost');
    Route::resource('fabrics', FabricController::class)->only(['index', 'create', 'store']);

    Route::resource('rates', RateController::class)->only(['index', 'create', 'store']);

    Route::resource('productions', ProductionController::class)->only(['index', 'create', 'store']);

    Route::resource('employees', EmployeeController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::post('update-employee-status', [EmployeeController::class, 'updateStatus'])->name('update-employee-status');

    Route::resource('employee-payments', EmployeePaymentController::class)->only(['index', 'create', 'store']);

    Route::resource('cr', CRController::class)->only(['index', 'create', 'store']);

    Route::get('dr/get-payments', [DRController::class, 'getPayments']);
    Route::resource('dr', DRController::class)->only(['index', 'create', 'store']);

    Route::resource('daily-ledger', DailyLedgerController::class)->only(['index', 'create', 'store']);

    Route::resource('sales-returns', SalesReturnController::class)->only(['index', 'create', 'store']);
    Route::post('sales-returns/get-details', [SalesReturnController::class, 'getDetails'])->name('sales-returns.get-details');
    Route::resource('statement-adjustments', StatementAdjustmentController::class)->only(['create', 'store']);
    Route::post('statement-adjustments/first-transaction-date', [StatementAdjustmentController::class, 'firstTransactionDate'])->name('statement-adjustments.first-transaction-date');

    Route::get('attendances/create', [AttendanceController::class, 'create'])->name('attendances.create');
    Route::post('attendances/store', [AttendanceController::class, 'store'])->name('attendances.store');
    Route::get('attendances/manage-salary', [AttendanceController::class, 'manageSalary'])->name('attendances.manage-salary');
    Route::post('attendances/manage-salary', [AttendanceController::class, 'manageSalaryPost'])->name('attendances.manage-salary-post');
    Route::get('attendances/generate-slip', [AttendanceController::class, 'generateSlip'])->name('attendances.generate-slip');
    Route::post('attendances/generate-slip', [AttendanceController::class, 'generateSlipPost'])->name('attendances.generate-slip-post');

    Route::resource('utility-bills', UtilityBillController::class)->only(['index', 'create', 'store']);
    Route::put('utility-bills/{utilityBill}/mark-paid', [UtilityBillController::class, 'markPaid'])->name('utility-bills.mark-paid');

    Route::resource('utility-accounts', UtilityAccountController::class)->only(['index', 'create', 'store']);

    Route::post('get-order-details', [Controller::class, 'getOrderDetails'])->name('get-order-details');
    Route::post('get-category-data', [Controller::class, 'getCategoryData'])->name('get-category-data');
    Route::post('change-data-layout', [Controller::class, 'changeDataLayout'])->name('change-data-layout');
    Route::post('get-program-details', [Controller::class, 'getProgramDetails'])->name('get-program-details');
    Route::post('set-invoice-type', [Controller::class, 'setInvoiceType'])->name('set-invoice-type');
    Route::post('get-shipment-details', [Controller::class, 'getShipmentDetails'])->name('get-shipment-details');
    Route::post('set-voucher-type', [Controller::class, 'setVoucherType'])->name('set-voucher-type');
    Route::post('set-production-type', [Controller::class, 'setProductionType'])->name('set-production-type');
    Route::post('get-voucher-details', [Controller::class, 'getVoucherDetails'])->name('get-voucher-details');
    Route::post('get-employees-by-category', [Controller::class, 'getEmployeesByCategory'])->name('get-employees-by-category');
    Route::post('set-daily-ledger-type', [Controller::class, 'setDailyLedgerType'])->name('set-daily-ledger-type');
    Route::post('get-utility-accounts', [Controller::class, 'getUtilityAccounts'])->name('get-utility-accounts');
    Route::post('set-statement-type', [Controller::class, 'setStatementType'])->name('set-statement-type');
    Route::post('set-physical-quantity-report-type', [Controller::class, 'setPhysicalQuantityReportType'])->name('set-physical-quantity-report-type');

    Route::get('reports/statement', [ReportController::class, 'statement'])->name('reports.statement');
    Route::post('reports/statement/get-names', [ReportController::class, 'getNames'])->name('reports.statement.get-names');
    Route::get('reports/statement/record-details', [ReportController::class, 'statementRecordDetails'])->name('reports.statement.record-details');
    Route::get('reports/pending-payments', [ReportController::class, 'pendingPayments'])->name('reports.pending-payments');
    Route::get('reports/article', [ReportController::class, 'article'])->name('reports.article');
    Route::get('reports/physical-quantity', [ReportController::class, 'physicalQuantity'])->name('reports.physical-quantity');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');

    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('update-last-activity', [AuthController::class, 'updateLastActivity'])->name('update-last-activity');
    Route::post('update-menu-shortcuts', [AuthController::class, 'updateMenuShortcuts'])->name('updateMenuShortcuts');
});
