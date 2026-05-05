<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\InvoiceArticles;
use App\Models\Invoice;
use App\Models\OrderArticles;
use App\Models\SupplierPayment;
use App\Models\Supplier;
use App\Models\Voucher;
use App\Services\PhysicalQuantityReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\FacadesLog;

class ReportController extends Controller
{
    public function statement(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper', 'customer', 'supplier'])) {
            return $resp;
        }

        if (!empty($request)) {
            $type = $request->type;
            $category = $request->category;
            $id = $request->id;
            $dateFrom = $request->date_from;
            $dateTo = $request->date_to;

            if ($this->isCustomerRole()) {
                $customer = $this->currentCustomer();
                if (!$customer) {
                    return response()->json(['error' => 'Customer account not linked with this user.'], 403);
                }
                $category = 'customer';
                $id = $customer->id;
            } elseif ($this->isSupplierRole()) {
                $supplier = $this->currentSupplier();
                if (!$supplier) {
                    return response()->json(['error' => 'Supplier account not linked with this user.'], 403);
                }
                $category = 'supplier';
                $id = $supplier->id;
            }


            if ($request->withData) {
                // return $request;
                if ($category === 'customer') {
                    $customer = Customer::find($id);
                    if (!$customer) {
                        return response()->json(['error' => 'Customer not found'], 404);
                    }

                    $data = $customer->getStatement($dateFrom, $dateTo, $type);

                    return view("reports.statement", compact('data'));
                }

                if ($category === 'supplier') {
                    $supplier = Supplier::find($id);
                    if (!$supplier) {
                        return response()->json(['error' => 'Supplier not found'], 404);
                    }

                    $data = $supplier->getStatement($dateFrom, $dateTo, $type);

                    return view("reports.statement", compact('data'));
                }

                if ($category === 'bank account') {
                    $bank_account = BankAccount::find($id);
                    if (!$bank_account) {
                        return response()->json(['error' => 'Bank account not found'], 404);
                    }

                    $data = $bank_account->getStatement($dateFrom, $dateTo, $type);

                    return view("reports.statement", compact('data'));
                }
            }
        }

        return view("reports.statement");
    }

    public function statementRecordDetails(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper', 'customer', 'supplier'])) {
            return $resp;
        }

        $validated = $request->validate([
            'type' => 'required|string|in:expense,voucher,supplier_payment,invoice,customer_payment',
            'id' => 'required|integer|min:1',
        ]);

        $payload = match ($validated['type']) {
            'expense' => $this->expenseStatementPayload((int) $validated['id']),
            'voucher' => $this->voucherStatementPayload((int) $validated['id']),
            'supplier_payment' => $this->supplierPaymentStatementPayload((int) $validated['id']),
            'invoice' => $this->invoiceStatementPayload((int) $validated['id']),
            'customer_payment' => $this->customerPaymentStatementPayload((int) $validated['id']),
            default => null,
        };

        if (!$payload) {
            return response()->json(['error' => 'Statement record not found.'], 404);
        }

        return response()->json($payload);
    }

    // fucntion get names based on category
    public function getNames(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper', 'customer', 'supplier'])) {
            return $resp;
        }

        $category = $request->category;

        if (!$category) {
            return response()->json(['error' => 'Category required'], 400);
        }

        if ($this->isCustomerRole()) {
            if ($category !== 'customer') {
                return response()->json([], 200);
            }

            $customer = $this->currentCustomer();
            if (!$customer) {
                return response()->json([], 200);
            }

            $customer->load('city');
            return response()->json([$customer]);
        }

        if ($this->isSupplierRole()) {
            if ($category !== 'supplier') {
                return response()->json([], 200);
            }

            $supplier = $this->currentSupplier();
            if (!$supplier) {
                return response()->json([], 200);
            }

            return response()->json([$supplier]);
        }

        if ($category === 'customer') {
            $customers = Customer::whereHas('user', function ($query) {
                $query->where('status', 'active');
            })->with('city')->get(); // select only needed fields
            return response()->json($customers);
        }

        if ($category === 'supplier') {
            $suppliers = Supplier::whereHas('user', function ($query) {
                $query->where('status', 'active');
            })->get();
            return response()->json($suppliers);
        }

        if ($category === 'bank_account') {
            $bank_accounts = BankAccount::with('bank')->where('status', 'active')->get();
            return response()->json($bank_accounts);
        }

        return response()->json(['error' => 'Invalid category'], 400);
    }
    public function pendingPayments(Request $request)
    {
        if (!empty($request)) {
            $date = $request->input('date'); // e.g. 2025-10-10
            if ($date) {
                // Base payments query
                $payments = CustomerPayment::with([
                        'customer.city',
                        'paymentClearRecord',
                    ])
                    ->whereNotNull('customer_id')
                    ->whereIn('method', ['cheque', 'slip'])
                    ->get()
                    ->filter(function ($payment) use ($date) {
                        $paymentDate = $payment->method === 'cheque'
                            ? $payment->cheque_date
                            : $payment->slip_date;

                        if (!$paymentDate) return false;

                        // Include only if paymentDate <= given $date
                        if (\Carbon\Carbon::parse($paymentDate)->gt(\Carbon\Carbon::parse($date))) {
                            return false;
                        }

                        $totalAmount = floatval($payment->amount);
                        $receivedAmount = 0;

                        if ($payment->paymentClearRecord && count($payment->paymentClearRecord) > 0) {
                            $receivedAmount = collect($payment->paymentClearRecord)->sum('amount');
                        } elseif ($payment->clear_date !== null) {
                            $receivedAmount = $totalAmount;
                        }

                        $balance = $totalAmount - $receivedAmount;

                        // ✅ Only include if balance > 0
                        if ($balance <= 0) return false;

                        // Add computed values for clarity
                        $payment->received_amount = $receivedAmount;
                        $payment->balance = $balance;

                        return true;
                    })
                    ->values();

                // ✅ Group payments by customer
                $grouped = $payments->groupBy(function ($p) {
                    $cityTitle = $p->customer?->city?->title ?? '';
                    return ($p->customer?->customer_name ?? 'Unknown') . ' | ' . $cityTitle;
                })
                ->map(function ($group, $customerKey) {
                    // Prepare totals
                    $totalAmount = $group->sum('amount');
                    $totalReceived = $group->sum('received_amount');
                    $totalBalance = $totalAmount - $totalReceived;

                    // Prepare simplified payment list
                    $paymentsArray = $group->map(function ($p) {
                        return [
                            'id' => $p->id,
                            'method' => $p->method,
                            'reff_no' => $p->cheque_no ?? $p->slip_no,
                            'date' => $p->method === 'cheque' ? $p->cheque_date : $p->slip_date,
                            'amount' => $p->amount,
                            'received_amount' => $p->received_amount,
                            'balance' => $p->balance,
                        ];
                    })->values();

                    return [
                        'customer' => $customerKey,
                        'payments' => $paymentsArray,
                        'totals' => [
                            'amount' => $totalAmount,
                            'received_amount' => $totalReceived,
                            'balance' => $totalBalance,
                        ],
                    ];
                })
                ->values();

                $data = $grouped;

                // return response()->json($data);
                return view("reports.pending-payments", compact('data'));
            }
        }

        return view("reports.pending-payments");
    }

    public function article(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper'])) {
            return $resp;
        }

        if ($request->ajax()) {

            $invoiceArticles = InvoiceArticles::with([
                'article',
                'invoice.customer.city'
            ]);

            /* 🔍 ARTICLE FILTER */
            if ($request->article_no) {
                $invoiceArticles->whereHas('article', function ($q) use ($request) {
                    $q->where('article_no', 'like', "%{$request->article_no}%");
                });
            }

            /* 🔍 CUSTOMER FILTER */
            if ($request->customer_name) {
                $invoiceArticles->whereHas('invoice.customer', function ($q) use ($request) {
                    $q->where('customer_name', 'like', "%{$request->customer_name}%");
                });
            }

            /* 🔍 INVOICE NO FILTER */
            if ($request->invoice_no) {
                $invoiceArticles->whereHas('invoice', function ($q) use ($request) {
                    $q->where('invoice_no', 'like', "%{$request->invoice_no}%");
                });
            }

            /* 📅 DATE RANGE FILTER */
            if ($request->date_range_start || $request->date_range_end) {

                $startDate = $request->date_range_start
                    ? Carbon::parse($request->date_range_start)->startOfDay()
                    : null;

                $endDate = $request->date_range_end
                    ? Carbon::parse($request->date_range_end)->endOfDay()
                    : null;

                $invoiceArticles->whereHas('invoice', function ($q) use ($startDate, $endDate) {
                    if ($startDate && $endDate) {
                        $q->whereBetween('date', [$startDate, $endDate]);
                    } elseif ($startDate) {
                        $q->whereDate('date', '>=', $startDate);
                    } elseif ($endDate) {
                        $q->whereDate('date', '<=', $endDate);
                    }
                });
            }

            /* ⚡ FIRST LOAD OPTIMIZATION */
            if ($request->limit) {
                $invoiceArticles
                    ->latest('id')        // or invoice_id / created_at
                    ->limit((int) $request->limit);
            }

            $data = $invoiceArticles->get()->map(function ($invoiceArticle) {

                $invoice  = $invoiceArticle->invoice;
                $customer = $invoice?->customer;
                $article  = $invoiceArticle->article;

                return [
                    'article_no'    => $article?->article_no,
                    'invoice_no'    => $invoice?->invoice_no,
                    'customer_name' => $customer?->customer_name . ' | ' . $customer?->city?->short_title,
                    'invoice_date'  => $invoice?->date?->format('d-M-Y, D'),
                    'invoice_pcs'   => $invoiceArticle->invoice_pcs,
                ];
            });

            $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');
            return response()->json([
                'data' => $data,
                'authLayout' => $authLayout
            ]);
        }

        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');
        return view('reports.article', compact('authLayout'));
    }

    public function physicalQuantity(Request $request, PhysicalQuantityReportService $physicalQuantityReportService)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper'])) {
            return $resp;
        }

        $articleOptions = $physicalQuantityReportService->getArticleOptions();
        $mode = $request->input('mode', 'all_articles');
        $reportType = Auth::user()?->physical_quantity_report_type ?? 'altration';
        if (!in_array($mode, ['all_articles', 'article_wise', 'proceed_by_wise'], true)) {
            $mode = 'all_articles';
        }
        if (!in_array($reportType, ['stock', 'altration'], true)) {
            $reportType = 'altration';
        }
        $data = null;

        if ($request->boolean('withData')) {
            $filters = [];
            $canGenerate = true;

            if ($mode === 'article_wise' && $request->filled('article_id')) {
                $filters['article_id'] = (int) $request->input('article_id');
            } elseif ($mode === 'article_wise') {
                $canGenerate = false;
            }

            if ($mode === 'proceed_by_wise' && $request->filled('proceed_by')) {
                $filters['processed_by'] = $request->input('proceed_by');
            } elseif ($mode === 'proceed_by_wise') {
                $canGenerate = false;
            }

            $rows = $canGenerate
                ? $physicalQuantityReportService->getArticleReportRows($filters, $reportType)
                : collect();
            $maxRowsPerColumn = 58;
            $maxRowsPerPage = $maxRowsPerColumn * 2;

            $pages = $rows->chunk($maxRowsPerPage)->map(function ($pageRows) use ($maxRowsPerColumn) {
                $leftCount = (int) ceil($pageRows->count() / 2);
                $leftCount = min($leftCount, $maxRowsPerColumn);

                return [
                    'left' => $pageRows->take($leftCount)->values(),
                    'right' => $pageRows->slice($leftCount)->values(),
                ];
            })->values();

            $data = [
                'mode' => $mode,
                'report_type' => $reportType,
                'article_id' => $request->input('article_id'),
                'proceed_by' => $request->input('proceed_by'),
                'rows' => $rows,
                'pages' => $pages,
                'generated_at' => now(),
            ];
        }

        return view('reports.physical-quantity', compact('articleOptions', 'mode', 'reportType', 'data'));
    }

    private function expenseStatementPayload(int $id): ?array
    {
        $expense = Expense::with(['supplier:id,supplier_name', 'expenseSetups:id,title'])->find($id);
        if (!$expense) return null;

        if ($this->isSupplierRole() && $expense->supplier_id !== $this->currentSupplier()?->id) {
            abort(403, 'You are not authorized to view this statement record.');
        }

        return [
            'type' => 'expense',
            'data' => $expense->toFormattedArray(),
        ];
    }

    private function voucherStatementPayload(int $id): ?array
    {
        $voucher = Voucher::with([
            'supplier:id,supplier_name',
            'payments.cheque.customer:id,customer_name,city_id',
            'payments.cheque.customer.city:id,short_title,title',
            'payments.slip.customer:id,customer_name,city_id',
            'payments.slip.customer.city:id,short_title,title',
            'payments.program.customer:id,customer_name,city_id',
            'payments.program.customer.city:id,short_title,title',
            'payments.bankAccount.bank:id,short_title',
            'payments.selfAccount.bank:id,short_title',
        ])->find($id);
        if (!$voucher) return null;

        if ($this->isSupplierRole() && $voucher->supplier_id !== $this->currentSupplier()?->id) {
            abort(403, 'You are not authorized to view this statement record.');
        }

        return [
            'type' => 'voucher',
            'data' => $voucher->toFormattedArray(),
        ];
    }

    private function supplierPaymentStatementPayload(int $id): ?array
    {
        $payment = SupplierPayment::with([
            'supplier:id,supplier_name',
            'voucher.supplier:id,supplier_name',
            'bankAccount.bank:id,short_title',
            'selfAccount.bank:id,short_title',
            'program.customer:id,customer_name,city_id',
            'program.customer.city:id,short_title,title',
            'program.customerPayments.paymentClearRecord.bankAccount.bank',
            'cheque.customer:id,customer_name,city_id',
            'cheque.customer.city:id,short_title,title',
            'cheque.paymentClearRecord.bankAccount.bank',
            'cheque.dr',
            'slip.customer:id,customer_name,city_id',
            'slip.customer.city:id,short_title,title',
            'slip.paymentClearRecord.bankAccount.bank',
            'slip.dr',
            'cr',
        ])->find($id);
        if (!$payment) return null;

        if ($this->isSupplierRole() && $payment->supplier_id !== $this->currentSupplier()?->id) {
            abort(403, 'You are not authorized to view this statement record.');
        }

        return [
            'type' => 'supplier_payment',
            'data' => $payment->toFormattedArray(),
        ];
    }

    private function invoiceStatementPayload(int $id): ?array
    {
        $invoice = Invoice::with([
            'order',
            'shipment',
            'invoiceArticles.article',
            'customer.city',
        ])->find($id);
        if (!$invoice) return null;

        if ($this->isCustomerRole() && $invoice->customer_id !== $this->currentCustomer()?->id) {
            abort(403, 'You are not authorized to view this statement record.');
        }

        return [
            'type' => 'invoice',
            'data' => $invoice->toFormattedArray(),
        ];
    }

    private function customerPaymentStatementPayload(int $id): ?array
    {
        $payment = CustomerPayment::whereNotNull('customer_id')
            ->with([
                'customer.city',
                'cheque.supplier',
                'cheque.voucher.supplier.bankAccounts.bank',
                'cheque.cr',
                'slip.supplier',
                'slip.voucher.supplier.bankAccounts.bank',
                'slip.cr',
                'program.subCategory',
                'bankAccount.subCategory',
                'paymentClearRecord.bankAccount.bank',
                'paymentClearRecord.creator',
                'dr',
            ])->find($id);
        if (!$payment) return null;

        if ($this->isCustomerRole() && $payment->customer_id !== $this->currentCustomer()?->id) {
            abort(403, 'You are not authorized to view this statement record.');
        }

        return [
            'type' => 'customer_payment',
            'data' => $payment->toFormattedArray(),
        ];
    }
}
