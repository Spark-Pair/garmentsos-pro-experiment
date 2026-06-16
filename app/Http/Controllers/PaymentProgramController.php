<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\PaymentProgram;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaymentProgramController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant'])) {
            return $resp;
        }
        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');

        if ($request->ajax()) {
            if ($request->get('status') === '__all__') {
                $request->merge(['status' => '']);
            }

            $payment_programs = PaymentProgram::with('customer.city', 'subCategory')->withPaymentDetails()->orderByDesc('id')
                ->applyFilters($request);

            $totalAmount = (float) $payment_programs->sum(fn($p) => (float) ($p['amount'] ?? 0));
            $totalPayment = (float) $payment_programs->sum(fn($p) => (float) ($p['payment'] ?? 0));
            $totalBalance = (float) $payment_programs->sum(fn($p) => (float) ($p['balance'] ?? 0));

            return response()->json([
                'data' => $payment_programs,
                'authLayout' => $authLayout,
                'calculations' => [
                    'total_amount' => $totalAmount,
                    'total_payment' => $totalPayment,
                    'balance' => $totalBalance,
                ],
            ]);
        }

        // // Fetch and sort orders by date and created_at
        // $orders = Order::with(['customer.city', 'paymentPrograms.subCategory'])
        //     ->whereHas('customer', function ($q) {
        //         $q->where('category', 'cash');
        //     })
        //     ->orderBy('date', 'asc')
        //     ->orderBy('created_at', 'asc')
        //     ->get();

        // $ordersArray = [];

        // foreach($orders as $order) {
        //     $order['paymentPrograms']['customer'] = $order['customer'];
        //     $ordersArray[] = $order['paymentPrograms'];
        // }

        // // Fetch and sort payment programs by date and created_at
        // $paymentPrograms = PaymentProgram::with('customer.city', 'subCategory')
        //     ->where('order_no', null)
        //     ->orderBy('date', 'asc')
        //     ->orderBy('created_at', 'asc')
        //     ->withPaymentDetails()
        //     ->get();

        // $paymentProgramsArray = $paymentPrograms->toArray();

        // // Combine both arrays manually
        // $finalData = array_merge($ordersArray, $paymentProgramsArray);

        // usort($finalData, function ($a, $b) {
        //     if ($a['date'] == $b['date']) {
        //         return strtotime($b['created_at']) - strtotime($a['created_at']); // time DESC
        //     }
        //     return strtotime($b['date']) - strtotime($a['date']); // date DESC
        // });

        return view("payment-programs.index", compact('authLayout'));
    }
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $start = microtime(true); // ⏱️ Start timing

        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $customers = Customer::with('city:id,title')
            ->withSum('invoices as total_invoice_amount', 'netAmount')
            ->withSum(['payments as total_paid_amount' => fn($q) => $q->where('type', '!=', 'DR')], 'amount')
            ->withSum(['statementAdjustments as adjustment_plus_amount' => fn($q) => $q->where('direction', 'plus')], 'amount')
            ->withSum(['statementAdjustments as adjustment_minus_amount' => fn($q) => $q->where('direction', 'minus')], 'amount')
            ->whereHas('user', function ($query) {
                $query->where('status', 'active');
            })
            ->select('id', 'customer_name', 'date', 'city_id')
            ->get();

        $customers_options = [];

        foreach ($customers as $customer) {
            $balance = (float) ($customer->total_invoice_amount ?? 0)
                - (float) ($customer->total_paid_amount ?? 0)
                + (float) ($customer->adjustment_plus_amount ?? 0)
                - (float) ($customer->adjustment_minus_amount ?? 0);

            $customers_options[(int)$customer->id] = [
                'text' => $customer->customer_name . ' | ' . ($customer->city->title ?? 'N/A') . ' | Balance: ' . \App\Support\Money::format($balance),
                'data_option' => [
                    'id' => $customer->id,
                    'customer_name' => $customer->customer_name,
                    'date' => $customer->date?->format('Y-m-d'),
                    'balance' => $balance,
                    'city' => [
                        'id' => $customer->city?->id,
                        'title' => $customer->city?->title,
                    ],
                ],
            ];
        }

        $loadTime = round(microtime(true) - $start, 3); // ⏱️ End timing

        if (app()->isLocal()) {
            Log::info("PaymentProgram create() load time: {$loadTime} seconds");
        }

        return view('payment-programs.create', compact('customers_options', 'loadTime'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'date'=> 'required|date',
            'customer_id'=> 'required|integer|exists:customers,id',
            'category'=> 'required|in:supplier,self_account,customer,waiting',
            'sub_category'=> 'nullable|integer',
            'amount'=> 'required|numeric|min:1',
            'remarks'=> 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::transaction(function () use ($request) {
            $subCategoryModel = $this->resolveSubCategoryModel($request->category, $request->sub_category);

            $nextProgramNo = ((int) PaymentProgram::query()->lockForUpdate()->max('program_no')) + 1;

            $program = new PaymentProgram([
                'program_no' => $nextProgramNo,
                'date' => $request->date,
                'customer_id' => $request->customer_id,
                'category' => $request->category,
                'amount' => $request->amount,
                'remarks' => $request->remarks,
            ]);

            if ($subCategoryModel) {
                $subCategoryModel->paymentPrograms()->save($program);
            } else {
                $program->save();
            }
        });

        return redirect()->route('payment-programs.create')->with('success', 'Payment program added successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(PaymentProgram $paymentProgram)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PaymentProgram $paymentProgram)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaymentProgram $paymentProgram)
    {
        //
    }
    public function updateProgram(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'program_id' => 'required|integer|exists:payment_programs,id',
            'category' => 'required|in:supplier,self_account,customer,waiting',
            'sub_category' => 'nullable|integer',
            'remarks' => 'nullable|string',
            'amount' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->with('error', $validator->errors()->first());
        }

        $program = PaymentProgram::find($request->program_id);
        $subCategoryModel = $this->resolveSubCategoryModel($request->category, $request->sub_category);

        $program->category = $request->category;
        $program->remarks = $request->remarks;
        if ($request->amount !== null) {
            $program->amount = $request->amount;
        }

        if ($subCategoryModel) {
            $subCategoryModel->paymentPrograms()->save($program);
        } else {
            $program->save();
        }

        return redirect()->route('payment-programs.index')->with('success', 'Program updated successfully.');
    }

    public function markPaid($id)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $program = PaymentProgram::findOrFail($id);
        $paidAmount = (float) $program->customerPayments()->sum('amount');
        $balance = (float) $program->amount - $paidAmount;

        $program->status = $balance < 0 ? 'Overpaid' : 'Paid';
        $program->save();

        return redirect()->route('payment-programs.index')->with('success', 'Program status updated successfully.');
    }

    public function CustomerSummary(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }
        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');

        if ($request->ajax()) {
            $customersQuery = Customer::whereHas('paymentPrograms', function ($q) {
                    $q->where('status', 'Unpaid');
                })
                ->with([
                    'city:id,title,short_title',

                    'paymentPrograms' => fn($q) => $q
                        ->where('status', 'Unpaid')
                        ->select('id', 'customer_id', 'amount', 'status')

                        // customer paid amount
                        ->withSum('customerPayments as paid_amount', 'amount')

                        // supplier pending voucher payment
                        ->withSum([
                            'supplierPayments as voucher_pending_payment' => fn($sq) => $sq
                                ->whereIn('method', ['program', 'Program'])
                                ->whereNull('voucher_id'),
                        ], 'amount'),
                ])
                ->applyFilters($request, false, true);

            $customers = $customersQuery->get()->map(function ($customer) {

                $customer->paymentPrograms->transform(function ($program) {

                    $program->setAppends([]);

                    $programAmount = (float) $program->amount;

                    $paidAmount = (float) ($program->paid_amount ?? 0);

                    $voucherPendingPayment = (float) ($program->voucher_pending_payment ?? 0);

                    $balance = max(0, $programAmount - $paidAmount);

                    $program->setAttribute('program_amount', $programAmount);

                    $program->setAttribute('payment', $voucherPendingPayment);

                    $program->setAttribute('balance', $balance);

                    return $program;
                });

                return [
                    'id' => $customer->id,
                    'name' => $customer->customer_name,
                    'name_city' => $customer->customer_name . ' | ' . ($customer->city?->title ?? '-'),
                    'data' => [
                        'payment_programs' => $customer->paymentPrograms->map(fn($program) => [
                            'id' => $program->id,
                            'amount' => (float) $program->program_amount,
                            'payment' => (float) $program->payment,
                            'balance' => (float) $program->balance,
                            'status' => $program->status,
                        ])->values(),
                    ],
                ];
            });

            return response()->json(['data' => $customers, 'authLayout' => $authLayout]);
        }

        return view('payment-programs.customerSummary', compact('authLayout'));
    }

    public function SupplierSummary(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }
        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');

        if ($request->ajax()) {

    $openProgramQuery = fn($q) => $q->where(function ($qq) {
        $qq->where('status', 'Unpaid')
            ->orWhereHas('supplierPayments', fn($sq) => $sq
                ->whereIn('method', ['program', 'Program'])
                ->whereNull('voucher_id'));
    });

    $suppliersQuery = Supplier::whereHas('paymentPrograms', $openProgramQuery)
        ->with([
            'paymentPrograms' => fn($q) => $openProgramQuery($q)
                ->select(
                    'id',
                    'sub_category_id',
                    'sub_category_type',
                    'amount',
                    'status'
                )

                // customer paid amount
                ->withSum('customerPayments as paid_amount', 'amount')

                // ONLY pending supplier payments
                ->withSum([
                    'supplierPayments as voucher_pending_payment' => fn($sq) => $sq
                        ->whereIn('method', ['program', 'Program'])
                        ->whereNull('voucher_id'),
                ], 'amount'),
        ])
        ->applyFilters($request, false, true);

    $suppliers = $suppliersQuery->get()->map(function ($supplier) {

        $supplier->paymentPrograms->transform(function ($program) {

            $program->setAppends([]);

            $originalAmount = (float) $program->getRawOriginal('amount');

            $paidAmount = (float) ($program->paid_amount ?? 0);

            // ONLY voucher_id null payments
            $pendingPayment = (float) ($program->voucher_pending_payment ?? 0);

            // unpaid balance after customer payments
            $balance = max(0, $originalAmount - $paidAmount);

            // custom attributes
            $program->setAttribute('program_amount', $originalAmount);

            $program->setAttribute('payment', $pendingPayment);

            $program->setAttribute('balance', $balance);

            return $program;
        });

        return [
            'id' => $supplier->id,
            'name' => $supplier->supplier_name,
            'data' => [
                'payment_programs' => $supplier->paymentPrograms->map(fn($program) => [
                    'id' => $program->id,
                    'amount' => (float) $program->program_amount,
                    'payment' => (float) $program->payment,
                    'balance' => (float) $program->balance,
                    'status' => $program->status,
                ])->values(),
            ],
        ];
    });

    return response()->json([
        'data' => $suppliers,
        'authLayout' => $authLayout
    ]);
}

        return view('payment-programs.supplierSummary', compact('authLayout'));
    }

    private function resolveSubCategoryModel(string $category, ?int $subCategoryId): Supplier|BankAccount|Customer|null
    {
        if ($category === 'waiting' || !$subCategoryId) {
            return null;
        }

        return match ($category) {
            'supplier' => Supplier::find($subCategoryId),
            'self_account' => BankAccount::find($subCategoryId),
            'customer' => Customer::find($subCategoryId),
            default => null,
        };
    }
}
