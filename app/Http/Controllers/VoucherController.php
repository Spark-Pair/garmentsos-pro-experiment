<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Nette\Schema\Expect;

class VoucherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest'])) {
            return $resp;
        }

        $authLayout = $this->getAuthLayout($request->route()->getName());

        if ($request->ajax()) {
            $vouchers = Voucher::with([
                    'supplier:id,supplier_name',
                    'payments.cheque.customer',
                    'payments.slip.customer',
                    'payments.program.customer',
                    'payments.bankAccount.bank',
                    'payments.selfAccount.bank'
                ])->orderByDesc('id')
                ->applyFilters($request);

            return response()->json(['data' => $vouchers, 'authLayout' => $authLayout]);
        }

        // // Eager load all relations needed
        // $vouchers = Voucher::with([
        //     'supplier:id,supplier_name',
        //     'payments.cheque.customer',
        //     'payments.slip.customer',
        //     'payments.program.customer',
        //     'payments.bankAccount.bank',
        //     'payments.selfAccount.bank'
        // ])
        // ->orderByDesc('id')
        // ->get();

        // // Preload supplier balances in batch to reduce queries (optional if calculateBalance is query-heavy)
        // $supplierIds = $vouchers->pluck('supplier.id')->filter()->unique();
        // $supplierBalances = [];
        // foreach ($supplierIds as $id) {
        //     $supplierBalances[$id] = Supplier::find($id)->calculateBalance(null, now(), false, false);
        // }

        // foreach ($vouchers as $voucher) {
        //     // Calculate previous balance only if supplier exists
        //     if ($voucher->supplier) {
        //         $voucher->previous_balance = $supplierBalances[$voucher->supplier->id] ?? 0;
        //     }

        //     // Sum of all payments
        //     $voucher->total_payment = $voucher->payments->sum('amount');
        // }

        return view("vouchers.index", compact( "authLayout"));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        if ($request->ajax()) {
            $supplier_id = $request->supplier_id;
            $paymentMethod = $request->payment_method;
            $date = $request->date . ' 00:00:00';
            $payments_options = [];
            $supplierBalanceAtDate = 0;

            if ($supplier_id && $request->date) {
                $supplier = Supplier::find($supplier_id);
                if ($supplier) {
                    $supplierBalanceAtDate = $supplier->calculateBalance(null, $request->date, false, true);
                }
            }

            if ($paymentMethod == 'cheque') {
                $cheques = CustomerPayment::whereNotNull('cheque_no')
                    ->with('customer.city')
                    ->whereDoesntHave('cheque')
                    ->whereNull('bank_account_id')
                    ->get();

                $payments_options = $cheques->map(function ($cheque) {
                    return [
                        'id' => (int)$cheque->id,
                        'text' => \App\Support\Money::format($cheque->amount) . ' | ' . $cheque->customer->customer_name . ' | ' . $cheque->customer->city->title . ' | ' . $cheque->cheque_no . ' | ' . date('d-M-Y D', strtotime($cheque->cheque_date)),
                        'dataset' => $cheque->makeHidden('creator'),
                    ];
                })->values()->toArray();
            } else if ($paymentMethod == 'slip') {
                $slips = CustomerPayment::whereNotNull('slip_no')
                    ->with('customer.city')
                    ->whereDoesntHave('slip')
                    ->whereNull('bank_account_id')
                    ->get();

                $payments_options = $slips->map(function ($slip) {
                    return [
                        'id' => (int)$slip->id,
                        'text' => \App\Support\Money::format($slip->amount) . ' | ' . $slip->customer->customer_name . ' | ' . $slip->customer->city->title . ' | ' . $slip->slip_no . ' | ' . date('d-M-Y D', strtotime($slip->slip_date)),
                        'dataset' => $slip->makeHidden('creator'),
                    ];
                })->values()->toArray();
            } else if ($paymentMethod == 'purchase_return') {
                $expenses = Expense::where('supplier_id', $supplier_id)
                    ->where('date', '>=', $date)
                    ->with('expenseSetups')
                    ->get();

                $payments_options = $expenses->map(function ($expense) {
                    return [
                        'id' => (int)$expense->id,
                        'text' => \App\Support\Money::format($expense->amount) . ' | ' . $expense->reff_no . ' | ' . date('d-M-Y D', strtotime($expense->date)),
                        'dataset' => $expense,
                    ];
                })->values()->toArray();
            } else if ($paymentMethod === 'program') {
                $payments = SupplierPayment::where('supplier_id', $supplier_id)
                    ->where('method', 'program')
                    ->whereNull('voucher_id')
                    ->select(
                        'id',
                        'program_id',
                        'amount',
                        'transaction_id',
                        'date'
                    )
                    ->with([
                        'program:id,customer_id',
                        'program.customer:id,customer_name,city_id',
                        'program.customer.city:id,short_title',
                    ])
                    ->get();

                $payments_options = $payments->map(function ($payment) {

                    // 🔥 IMPORTANT: disable appends on PaymentProgram
                    if ($payment->relationLoaded('program') && $payment->program) {
                        $payment->program->setAppends([]);
                    }

                    return [
                        'id' => (int)$payment->id,
                        'text' =>
                            \App\Support\Money::format($payment->amount) . ' | ' .
                            ($payment->program?->customer?->customer_name ?? '-') . ' | ' .
                            ($payment->program?->customer?->city?->short_title ?? '-') . ' | ' .
                            ($payment->transaction_id ?? '-') . ' | ' .
                            optional($payment->date)->format('d-M-Y D'),
                        'dataset' => $payment,
                    ];
                })->values()->toArray();
            } else if ($paymentMethod == 'self_cheque' || $paymentMethod == 'atm') {
                $self_accounts = BankAccount::where('category', 'self')
                    ->with('bank')
                    ->get()
                    ->makeHidden('creator');

                $payments_options = $self_accounts->map(function ($account) {
                    return [
                        'id' => (int)$account->id,
                        'text' => $account->account_title . ' | ' . $account->bank->short_title,
                        'dataset' => $account,
                    ];
                })->values()->toArray();
            }

            return response()->json([
                'payments_options' => $payments_options,
                'supplier_balance_at_date' => $supplierBalanceAtDate,
            ]);
        }

        $voucherType = auth()->user()->voucher_type;

        // --- Last voucher ---
        $last_voucher = Voucher::orderByDesc('id')->first();
        if (!$last_voucher) {
            $last_voucher = (object)['voucher_no' => '00/149'];
        }

        // --- Self Accounts (needed for Self Cheque / ATM even in supplier vouchers) ---
        $self_accounts = BankAccount::where('category', 'self')
            ->with('bank')
            ->get()
            ->makeHidden('creator');

        $self_accounts_options = $self_accounts->mapWithKeys(function ($account) {
            return [
                (int)$account->id => [
                    'text' => $account->account_title . ' - ' . $account->bank->short_title,
                    'data_option' => $account,
                ]
            ];
        })->toArray();

        if ($voucherType == 'supplier') {
            // --- Suppliers ---
            $suppliers = Supplier::whereHas('user', fn($q) => $q->where('status', 'active'))->select('id', 'supplier_name', 'date')->get();

            $suppliers_options = $suppliers->mapWithKeys(function ($supplier) {
                return [
                    (int)$supplier->id => [
                        'text' => $supplier->supplier_name,
                        'data_option' => $supplier,
                    ]
                ];
            })->toArray();

            return view("vouchers.create", compact("suppliers_options", 'self_accounts_options', 'last_voucher'));
        } else {
            return view("vouchers.create", compact("self_accounts_options", 'last_voucher'));
        }
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
            "voucher_no" => "required|string|unique:vouchers,voucher_no",
            "supplier_id" => "nullable|integer|exists:suppliers,id",
            "date" => "required|date",
            "program_id" => "nullable|exists:payment_programs,id",
            "payment_details_array" => "required|json",
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->with('error', $validator->errors()->first());
        }

        $paymentDetailsArray = json_decode($request->payment_details_array, true) ?? [];
        $this->assertVoucherPaymentsAreUnique($paymentDetailsArray);

        DB::transaction(function () use ($request, $paymentDetailsArray) {
            $voucher = Voucher::create([
                'voucher_no' => $request->voucher_no,
                'supplier_id' => $request->supplier_id,
                'date' => $request->date,
            ]);

            foreach ($paymentDetailsArray as $paymentDetails) {
                if (isset($paymentDetails['self_account_id'])) {
                    if ($paymentDetails['method'] == 'Cash' || $paymentDetails['method'] == 'Adjustment') {
                        CustomerPayment::create([
                            'date' => $request->date,
                            'type' => 'self_account_deposit',
                            'method' => $paymentDetails['method'],
                            'amount' => $paymentDetails['amount'],
                            'remarks' => $paymentDetails['remarks'],
                            'bank_account_id' => $paymentDetails['self_account_id'],
                        ]);

                        SupplierPayment::create([
                            'date' => $request->date,
                            'method' => $paymentDetails['method'],
                            'amount' => $paymentDetails['amount'],
                            'remarks' => $paymentDetails['remarks'],
                            'self_account_id' => $paymentDetails['self_account_id'],
                            'voucher_id' => $voucher->id,
                        ]);
                    } else if ($paymentDetails['method'] == 'Cheque') {
                        $customerPayment = CustomerPayment::find($paymentDetails['cheque_id']);
                        if ($customerPayment) {
                            $customerPayment->update([
                                'bank_account_id' => $paymentDetails['self_account_id'],
                                'is_return' => false,
                            ]);

                            SupplierPayment::create([
                                'date' => $request->date,
                                'method' => $paymentDetails['method'],
                                'amount' => $paymentDetails['amount'],
                                'cheque_id' => $paymentDetails['cheque_id'],
                                'remarks' => $paymentDetails['remarks'],
                                'self_account_id' => $paymentDetails['self_account_id'],
                                'voucher_id' => $voucher->id,
                            ]);
                        }
                    } else if ($paymentDetails['method'] == 'Slip') {
                        $customerPayment = CustomerPayment::find($paymentDetails['slip_id']);
                        if ($customerPayment) {
                            $customerPayment->update([
                                'bank_account_id' => $paymentDetails['self_account_id'],
                                'is_return' => false,
                            ]);

                            SupplierPayment::create([
                                'date' => $request->date,
                                'method' => $paymentDetails['method'],
                                'amount' => $paymentDetails['amount'],
                                'slip_id' => $paymentDetails['slip_id'],
                                'remarks' => $paymentDetails['remarks'],
                                'self_account_id' => $paymentDetails['self_account_id'],
                                'voucher_id' => $voucher->id,
                            ]);
                        }
                    } else if ($paymentDetails['method'] == 'Self Cheque') {
                        $customerPayment = CustomerPayment::create([
                            'date'           => $request->date,
                            'type'           => 'self_account_deposit',
                            'method'         => $paymentDetails['method'],
                            'amount'         => $paymentDetails['amount'],
                            'cheque_no'      => $paymentDetails['cheque_no'],
                            'cheque_date'    => $paymentDetails['cheque_date'],
                            'remarks'        => $paymentDetails['remarks'],
                            'bank_account_id'=> $paymentDetails['self_account_id'],
                        ]);

                        SupplierPayment::create([
                            'date'           => $request->date,
                            'method'         => $paymentDetails['method'],
                            'amount'         => $paymentDetails['amount'],
                            'cheque_id'      => $customerPayment->id, // link, not duplicate
                            'remarks'        => $paymentDetails['remarks'],
                            'bank_account_id'=> $paymentDetails['bank_account_id'],
                            'self_account_id'=> $paymentDetails['self_account_id'],
                            'voucher_id'     => $voucher->id,
                        ]);
                    } else if ($paymentDetails['method'] == 'ATM') {
                        $customerPayment = CustomerPayment::create([
                            'date'           => $request->date,
                            'type'           => 'self_account_deposit',
                            'method'         => $paymentDetails['method'],
                            'amount'         => $paymentDetails['amount'],
                            'reff_no'        => $paymentDetails['reff_no'],
                            'remarks'        => $paymentDetails['remarks'],
                            'bank_account_id'=> $paymentDetails['self_account_id'],
                        ]);

                        SupplierPayment::create([
                            'date'           => $request->date,
                            'method'         => $paymentDetails['method'],
                            'amount'         => $paymentDetails['amount'],
                            'atm_id'         => $customerPayment->id, // ya cheque_id — jo column use kar rahe ho
                            'remarks'        => $paymentDetails['remarks'],
                            'bank_account_id'=> $paymentDetails['bank_account_id'],
                            'self_account_id'=> $paymentDetails['self_account_id'],
                            'voucher_id'     => $voucher->id,
                        ]);
                    }
                } else {
                    $paymentDetails['supplier_id'] = $request->supplier_id;
                    $paymentDetails['date'] = $request->date;
                    $paymentDetails['voucher_id'] = $voucher->id;

                    if ($paymentDetails['method'] == 'Cheque' || $paymentDetails['method'] == 'Slip') {
                        $customerPayment = CustomerPayment::find($paymentDetails[$paymentDetails['method'] == 'Cheque' ? 'cheque_id' : 'slip_id']);
                        if ($customerPayment) {
                            $customerPayment->update([
                                'bank_account_id' => $paymentDetails['bank_account_id'] ?? null,
                                'is_return' => false,
                            ]);
                        }
                    }

                    if ($paymentDetails['payment_id'] ?? false) {
                        $payment = SupplierPayment::find($paymentDetails['payment_id']);

                        if ($payment) {
                            $payment->update(['voucher_id' => $voucher->id]);
                        }
                    } else {
                        SupplierPayment::create($paymentDetails);
                    }
                }
            }
        });

        return redirect()->route('vouchers.create')->with('success', 'Voucher Added successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Voucher $voucher)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Voucher $voucher)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        // $voucher->load([
        //     'supplier' => fn ($q) => $q->with([
        //         'payments' => fn ($q) =>
        //             $q->where('method', 'program')
        //             ->whereNull('voucher_id')
        //             ->with('program.customer.city:id,title'),
        //         'expenses',
        //     ]),
        //     'payments.cheque.customer.city',
        //     'payments.slip.customer.city',
        //     'payments.program.customer.city',
        //     'payments.bankAccount.bank',
        //     'payments.selfAccount.bank',
        // ]);

        $voucher->load([
            'supplier' => fn ($q) => $q->with([
                'payments' => fn ($q) =>
                    $q->where('method', 'program')
                    ->whereNull('voucher_id')
                    ->with('program.customer.city:id,title'),
                'expenses',
            ]),
            'payments.cheque' => fn($q) => $q->whereDoesntHave('paymentClearRecord'),
            'payments.cheque.customer.city',
            'payments.slip' => fn($q) => $q->whereDoesntHave('paymentClearRecord'),
            'payments.slip.customer.city',
            'payments.program.customer.city',
            'payments.bankAccount.bank',
            'payments.selfAccount.bank',
        ]);

        if ($voucher->supplier && $voucher->date) {
            $voucher->supplier->balance_at_date = $voucher->supplier->calculateBalance(null, $voucher->date, false, true);
        }

        $cheques = CustomerPayment::whereNotNull('cheque_no')->with('customer.city')->whereDoesntHave('cheque')->whereNull('bank_account_id')->get();
        $cheques_options = [];

        foreach ($cheques as $cheque) {
            $chequePayload = $this->formatVoucherCustomerPaymentPayload($cheque);
            $cheques_options[(int)$cheque->id] = [
                'text' => $cheque->amount . ' | ' . $cheque->customer->customer_name . ' | ' . $cheque->customer->city->title . ' | ' . $cheque->cheque_no . ' | ' . date('d-M-Y D', strtotime($cheque->cheque_date)),
                'data_option' => $chequePayload,
            ];
        }

        $slips = CustomerPayment::whereNotNull('slip_no')->with('customer.city')->whereDoesntHave('slip')->whereNull('bank_account_id')->get();
        $slips_options = [];

        foreach ($slips as $slip) {
            $slipPayload = $this->formatVoucherCustomerPaymentPayload($slip);
            $slips_options[(int)$slip->id] = [
                'text' => $slip->amount . ' | ' . $slip->customer->customer_name . ' | ' . $slip->customer->city->title . ' | ' . $slip->slip_no . ' | ' . date('d-M-Y D', strtotime($slip->slip_date)),
                'data_option' => $slipPayload,
            ];
        }

        $self_accounts = BankAccount::where('category', 'self')->with('bank')->get();
        $selfAccountsPayload = $self_accounts
            ->map(fn ($account) => $this->formatVoucherBankAccountPayload($account))
            ->values()
            ->all();

        $self_accounts_options = [];

        foreach ($self_accounts as $account) {
            $accountPayload = $this->formatVoucherBankAccountPayload($account);
            $self_accounts_options[(int)$account->id] = [
                'text' => $account->account_title . ' - ' . $account->bank->short_title,
                'data_option' => $accountPayload,
            ];
        }

        if ($voucher->supplier_id === null && Auth::user()->voucher_type == 'supplier') {
            $user = Auth::user();
            $user->voucher_type = 'self_account';
            $user->save();
        } else if ($voucher->supplier_id !== null && Auth::user()->voucher_type == 'self_account') {
            $user = Auth::user();
            $user->voucher_type = 'supplier';
            $user->save();
        }

        $voucherPayload = $this->formatVoucherEditPayload($voucher);

        return view("vouchers.edit", compact('voucher', 'voucherPayload', 'cheques_options', 'slips_options', 'selfAccountsPayload', 'self_accounts_options'));
    }

    private function formatVoucherEditPayload(Voucher $voucher): array
    {
        return [
            'id' => $voucher->id,
            'voucher_no' => $voucher->voucher_no,
            'supplier_id' => $voucher->supplier_id,
            'date' => optional($voucher->date)->toJSON(),
            'supplier' => $voucher->supplier ? $this->formatVoucherSupplierPayload($voucher->supplier) : null,
            'payments' => $voucher->payments
                ? $voucher->payments->map(fn ($payment) => $this->formatVoucherSupplierPaymentPayload($payment))->values()->all()
                : [],
        ];
    }

    private function formatVoucherSupplierPayload(Supplier $supplier): array
    {
        return [
            'id' => $supplier->id,
            'supplier_name' => $supplier->supplier_name,
            'date' => optional($supplier->date)->toJSON(),
            'balance' => (float) ($supplier->balance_at_date ?? 0),
            'balance_at_date' => (float) ($supplier->balance_at_date ?? 0),
            'payments' => $supplier->payments
                ? $supplier->payments->map(fn ($payment) => $this->formatVoucherSupplierPaymentPayload($payment))->values()->all()
                : [],
            'expenses' => $supplier->expenses
                ? $supplier->expenses->map(fn ($expense) => $this->formatVoucherExpensePayload($expense))->values()->all()
                : [],
        ];
    }

    private function formatVoucherSupplierPaymentPayload(SupplierPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'payment_id' => $payment->id,
            'supplier_id' => $payment->supplier_id,
            'voucher_id' => $payment->voucher_id,
            'date' => optional($payment->date)->toJSON(),
            'method' => $payment->method,
            'amount' => (float) ($payment->amount ?? 0),
            'reff_no' => $payment->reff_no,
            'cheque_no' => $payment->cheque_no,
            'cheque_id' => $payment->cheque_id,
            'slip_id' => $payment->slip_id,
            'program_id' => $payment->program_id,
            'bank_account_id' => $payment->bank_account_id,
            'self_account_id' => $payment->self_account_id,
            'transaction_id' => $payment->transaction_id,
            'remarks' => $payment->remarks,
            'cheque' => $payment->cheque ? $this->formatVoucherCustomerPaymentPayload($payment->cheque) : null,
            'slip' => $payment->slip ? $this->formatVoucherCustomerPaymentPayload($payment->slip) : null,
            'program' => $payment->program ? $this->formatVoucherPaymentProgramPayload($payment->program) : null,
            'bank_account' => $payment->bankAccount ? $this->formatVoucherBankAccountPayload($payment->bankAccount) : null,
            'self_account' => $payment->selfAccount ? $this->formatVoucherBankAccountPayload($payment->selfAccount) : null,
        ];
    }

    private function formatVoucherCustomerPaymentPayload(CustomerPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'customer_id' => $payment->customer_id,
            'date' => optional($payment->date)->toJSON(),
            'method' => $payment->method,
            'type' => $payment->type,
            'amount' => (float) ($payment->amount ?? 0),
            'cheque_no' => $payment->cheque_no,
            'slip_no' => $payment->slip_no,
            'reff_no' => $payment->reff_no,
            'transaction_id' => $payment->transaction_id,
            'cheque_date' => optional($payment->cheque_date)->toJSON(),
            'slip_date' => optional($payment->slip_date)->toJSON(),
            'remarks' => $payment->remarks,
            'customer' => $payment->customer ? [
                'id' => $payment->customer->id,
                'customer_name' => $payment->customer->customer_name,
                'city' => $payment->customer->city ? [
                    'id' => $payment->customer->city->id,
                    'title' => $payment->customer->city->title,
                    'short_title' => $payment->customer->city->short_title,
                ] : null,
            ] : null,
        ];
    }

    private function formatVoucherPaymentProgramPayload($program): array
    {
        return [
            'id' => $program->id,
            'program_no' => $program->program_no,
            'customer_id' => $program->customer_id,
            'date' => optional($program->date)->toJSON(),
            'customer' => $program->customer ? [
                'id' => $program->customer->id,
                'customer_name' => $program->customer->customer_name,
                'city' => $program->customer->city ? [
                    'id' => $program->customer->city->id,
                    'title' => $program->customer->city->title,
                    'short_title' => $program->customer->city->short_title,
                ] : null,
            ] : null,
        ];
    }

    private function formatVoucherBankAccountPayload(BankAccount $account): array
    {
        return [
            'id' => $account->id,
            'category' => $account->category,
            'account_title' => $account->account_title,
            'account_no' => $account->account_no,
            'date' => optional($account->date)->toJSON(),
            'balance' => (float) ($account->balance ?? 0),
            'available_cheques' => $account->available_cheques ?? [],
            'bank' => $account->bank ? [
                'id' => $account->bank->id,
                'title' => $account->bank->title,
                'short_title' => $account->bank->short_title,
            ] : null,
        ];
    }

    private function formatVoucherExpensePayload(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'date' => optional($expense->date)->toJSON(),
            'supplier_id' => $expense->supplier_id,
            'expense' => $expense->expense,
            'reff_no' => $expense->reff_no,
            'amount' => (float) ($expense->amount ?? 0),
            'lot_no' => $expense->lot_no,
            'remarks' => $expense->remarks,
        ];
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Voucher $voucher)
    {
        // -----------------------------
        // Step 1: Authorization check
        // -----------------------------
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        // -----------------------------
        // Step 2: Validation
        // -----------------------------
        $validator = Validator::make($request->all(), [
            "payment_details_array" => "required|json",
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->with('error', $validator->errors()->first());
        }

        $requestPayments = json_decode($request->payment_details_array, true);
        $this->assertVoucherPaymentsAreUnique($requestPayments ?? [], $voucher);

        DB::transaction(function () use ($voucher, $requestPayments) {

            // -----------------------------
            // Step 3: Delete existing payments
            // -----------------------------
            $existingPayments = $voucher->payments()->get();

            foreach ($existingPayments as $old) {
                
                if (in_array($old->method, ['Cheque', 'cheque']) && $old->cheque_id) {
                    CustomerPayment::where('id', $old->cheque_id)->update([
                        'bank_account_id' => null,
                        'is_return'       => false,
                    ]);
                }

                if (in_array($old->method, ['Slip', 'slip']) && $old->slip_id) {
                    CustomerPayment::where('id', $old->slip_id)->update([
                        'bank_account_id' => null,
                        'is_return'       => false,
                    ]);
                }

                // Self Cheque — CustomerPayment cheque_id se linked hai, direct delete karo
                if ($old->method === 'Self Cheque' && $old->cheque_id) {
                    CustomerPayment::where('id', $old->cheque_id)->delete();
                }

                // ATM — atm_id se linked CustomerPayment delete karo
                if ($old->method === 'ATM' && $old->atm_id) {
                    CustomerPayment::where('id', $old->atm_id)->delete();
                }

                // Cash / Adjustment self deposit — cheque_id nahi hota, fields se match karo
                if (in_array($old->method, ['Cash', 'Adjustment']) && !empty($old->self_account_id)) {
                    CustomerPayment::where([
                        'type'           => 'self_account_deposit',
                        'method'         => $old->method,
                        'amount'         => $old->amount,
                        'bank_account_id'=> $old->self_account_id,
                    ])->delete();
                }

                if (in_array($old->method, ['program', 'Program'])) {
                    $old->update(['voucher_id' => null]);
                } else {
                    $old->delete();
                }
            }

            // -----------------------------
            // Step 4: Re-create payments
            // -----------------------------
            foreach ($requestPayments as $pd) {

                $pd['supplier_id'] = $voucher->supplier_id;
                $pd['voucher_id']  = $voucher->id;
                $pd['date']        = $voucher->date;

                if (in_array($pd['method'], ['program', 'Program'])) {
                    $supplierPayment = SupplierPayment::find($pd['payment_id'] ?? $pd['id'] ?? null);
                    if ($supplierPayment) {
                        $supplierPayment->update(['voucher_id' => $pd['voucher_id']]);
                    }
                    continue;
                }

                // Self account flows — CustomerPayment pehle banao, phir link karo
                if (!empty($pd['self_account_id'])) {

                    $cpBase = [
                        'date'           => $pd['date'],
                        'type'           => 'self_account_deposit',
                        'method'         => $pd['method'],
                        'amount'         => $pd['amount'],
                        'remarks'        => $pd['remarks'] ?? null,
                        'bank_account_id'=> $pd['self_account_id'],
                    ];

                    if (in_array($pd['method'], ['Cash', 'Adjustment'])) {
                        CustomerPayment::create($cpBase);
                        SupplierPayment::create($pd);
                    }

                    if ($pd['method'] === 'Cheque') {
                        CustomerPayment::where('id', $pd['cheque_id'])->update([
                            'is_return'       => false,
                            'bank_account_id' => $pd['self_account_id'],
                        ]);
                        SupplierPayment::create($pd);
                    }

                    if ($pd['method'] === 'Slip') {
                        CustomerPayment::where('id', $pd['slip_id'])->update([
                            'is_return'       => false,
                            'bank_account_id' => $pd['self_account_id'],
                        ]);
                        SupplierPayment::create($pd);
                    }

                    if ($pd['method'] === 'Self Cheque') {
                        $cp = CustomerPayment::create(array_merge($cpBase, [
                            'cheque_no'   => $pd['cheque']['cheque_no'] ?? null,   // fix
                            'cheque_date' => $pd['cheque']['date'] ?? null, // fix
                            'bank_account_id' => $pd['self_account_id'],
                        ]));

                        SupplierPayment::create(array_merge($pd, [
                            'cheque_id' => $cp->id,
                            'cheque_no' => null,
                            'bank_account_id' => $pd['bank_account_id'],
                            'self_account_id' => $pd['self_account_id'],
                        ]));        
                    }

                    if ($pd['method'] === 'ATM') {
                        $cp = CustomerPayment::create(array_merge($cpBase, [
                            'reff_no' => $pd['reff_no'] ?? null, // fix
                            'bank_account_id' => $pd['self_account_id'],
                        ]));

                        SupplierPayment::create(array_merge($pd, [
                            'atm_id'  => $cp->id,
                            'reff_no' => null,
                            'bank_account_id' => $pd['bank_account_id'],
                            'self_account_id' => $pd['self_account_id'],
                        ]));
                    }

                } else {
                    // Normal supplier payment (non-self-account)
                    if (in_array($pd['method'], ['Cheque', 'Slip'])) {
                        $linkKey = $pd['method'] === 'Cheque' ? 'cheque_id' : 'slip_id';
                        if (!empty($pd[$linkKey])) {
                            CustomerPayment::where('id', $pd[$linkKey])->update([
                                'bank_account_id' => $pd['bank_account_id'] ?? null,
                                'is_return'       => false,
                            ]);
                        }
                    }

                    if (!empty($pd['payment_id'])) {
                        $existing = SupplierPayment::find($pd['payment_id']);
                        if ($existing) {
                            $existing->update(['voucher_id' => $voucher->id]);
                        }
                    } else {
                        SupplierPayment::create($pd);
                    }
                }
            }

        }); // End transaction

        return redirect()->route('vouchers.index')->with('success', 'Voucher updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Voucher $voucher)
    {
        //
    }

    private function assertVoucherPaymentsAreUnique(array $paymentDetailsArray, ?Voucher $currentVoucher = null): void
    {
        $seen = [
            'cheque_id' => [],
            'slip_id' => [],
            'program_payment_id' => [],
            'expense_id' => [],
            'cheque_no' => [],
        ];

        $currentVoucherId = $currentVoucher?->id;

        foreach ($paymentDetailsArray as $index => $paymentDetails) {
            $rowNumber = $index + 1;
            $method = $this->normalizeVoucherPaymentMethod($paymentDetails['method'] ?? '');

            foreach (['cheque_id', 'slip_id', 'expense_id'] as $key) {
                $value = (int) ($paymentDetails[$key] ?? 0);
                if ($value <= 0) {
                    continue;
                }

                if (isset($seen[$key][$value])) {
                    throw ValidationException::withMessages([
                        'payment_details_array' => "Payment row {$rowNumber} mein duplicate selection allowed nahi hai.",
                    ]);
                }

                $seen[$key][$value] = true;
            }

            if ($method === 'program') {
                $paymentId = (int) ($paymentDetails['payment_id'] ?? $paymentDetails['id'] ?? 0);
                if ($paymentId > 0) {
                    if (isset($seen['program_payment_id'][$paymentId])) {
                        throw ValidationException::withMessages([
                            'payment_details_array' => "Payment row {$rowNumber} mein same program payment dobara select hui hai.",
                        ]);
                    }

                    $seen['program_payment_id'][$paymentId] = true;

                    $programPayment = SupplierPayment::find($paymentId);
                    if (!$programPayment || ($programPayment->voucher_id && $programPayment->voucher_id !== $currentVoucherId)) {
                        throw ValidationException::withMessages([
                            'payment_details_array' => "Payment row {$rowNumber} wali program payment pehle se kisi aur voucher ke saath linked hai.",
                        ]);
                    }
                }
            }

            if ($method === 'selfcheque') {
                $chequeNo = trim((string) ($paymentDetails['cheque_no'] ?? ''));
                if ($chequeNo !== '') {
                    if (isset($seen['cheque_no'][$chequeNo])) {
                        throw ValidationException::withMessages([
                            'payment_details_array' => "Payment row {$rowNumber} mein same self cheque number dobara use hua hai.",
                        ]);
                    }

                    $seen['cheque_no'][$chequeNo] = true;

                    $chequeExists = SupplierPayment::query()
                        ->where('cheque_no', $chequeNo)
                        ->when($currentVoucherId, fn($q) => $q->where('voucher_id', '!=', $currentVoucherId))
                        ->exists();

                    if ($chequeExists) {
                        throw ValidationException::withMessages([
                            'payment_details_array' => "Self cheque no. {$chequeNo} pehle se use ho chuka hai.",
                        ]);
                    }
                }
            }

            foreach (['cheque_id', 'slip_id'] as $paymentKey) {
                $paymentId = (int) ($paymentDetails[$paymentKey] ?? 0);
                if ($paymentId <= 0) {
                    continue;
                }

                $linkedExists = SupplierPayment::query()
                    ->where($paymentKey, $paymentId)
                    ->where('is_return', false) 
                    ->when($currentVoucherId, fn($q) => $q->where('voucher_id', '!=', $currentVoucherId))
                    ->exists();

                if ($linkedExists) {
                    throw ValidationException::withMessages([
                        'payment_details_array' => "Payment row {$rowNumber} wali selected " . str_replace('_', ' ', $paymentKey) . " pehle se kisi aur supplier payment mein use ho chuki hai.",
                    ]);
                }
            }
        }
    }

    private function normalizeVoucherPaymentMethod(?string $method): string
    {
        return strtolower(str_replace([' ', '_', '.'], '', (string) $method));
    }
}
