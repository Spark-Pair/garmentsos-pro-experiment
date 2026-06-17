<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\CR;
use App\Models\CustomerPayment;
use App\Models\SupplierPayment;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CRController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        // $crs = CR::with('voucher.supplier')->orderBy('id', 'desc')->get()->makeHidden('creator');
        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');

        if ($request->ajax()) {
            $crs = CR::orderByDesc('id')
                ->applyFilters($request);

            return response()->json(['data' => $crs, 'authLayout' => $authLayout]);
        }

        return view('cr.index', compact('authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $voucher_options = [];
        $payment_options = [];

        $supplier_id = $request->supplier;
        $method = $request->method;
        $maxDate = $request->max_date . ' 00:00:00';
        $payment_options = [];

        if (Auth::user()->c_r_type == 'voucher') {
            $vouchers = Voucher::all();

            if ($vouchers) {
                foreach($vouchers as $voucher) {
                    $voucher_options[$voucher->voucher_no] = [
                        'text' => $voucher->voucher_no
                    ];
                }
            }
        } else {
            $CRs = CR::all();

            if ($CRs) {
                foreach($CRs as $CR) {
                    $voucher_options[$CR->c_r_no] = [
                        'text' => $CR->c_r_no
                    ];
                }
            }
        }

        if ($method === 'cheque') {
            $cheques = CustomerPayment::whereNotNull('cheque_no')->with('customer.city')->whereDoesntHave('cheque')->whereNull('bank_account_id')->where('date', '<=', $maxDate)->get()->makeHidden('creator');

            foreach ($cheques as $cheque) {
                $payment_options[(int)$cheque->id] = [
                    'text' => $cheque->amount . ' | ' . $cheque->customer->customer_name . ' | ' . $cheque->customer->city->title . ' | ' . $cheque->cheque_no . ' | ' . date('d-M-Y D', strtotime($cheque->cheque_date)),
                    'data_option' => $this->formatCustomerPaymentOptionPayload($cheque),
                ];
            }
        } else if ($method === 'slip') {
            $slips = CustomerPayment::whereNotNull('slip_no')->with('customer.city')->whereDoesntHave('slip')->whereNull('bank_account_id')->where('date', '<=', $maxDate)->get()->makeHidden('creator');

            foreach ($slips as $slip) {
                $payment_options[(int)$slip->id] = [
                    'text' => $slip->amount . ' | ' . $slip->customer->customer_name . ' | ' . $slip->customer->city->title . ' | ' . $slip->slip_no . ' | ' . date('d-M-Y D', strtotime($slip->slip_date)),
                    'data_option' => $this->formatCustomerPaymentOptionPayload($slip),
                ];
            }
        } else if ($method === 'self_cheque') {
            $self_accounts = BankAccount::where('category', 'self')->with('bank:id,title,short_title')->get();

            foreach ($self_accounts as $self_account) {
                foreach ($self_account->available_cheques as $available_cheque) {
                    $payment_options[(int)$available_cheque] = [
                        'text' => $available_cheque . ' |' . explode('|', $self_account->account_title)[1],
                        'data_option' => $this->formatBankAccountOptionPayload($self_account),
                    ];
                }
            }
        } else if ($method === 'program') {
            $payments = SupplierPayment::where('supplier_id', $supplier_id)
                ->with('program.customer.city:id,title,short_title')
                ->where('method', 'program')
                ->whereNull('voucher_id')
                ->where('date', '<=', $maxDate)
                ->get();

            foreach ($payments as $payment) {
                $payment_options[(int)$payment->id] = [
                    'text' => 'Rs. ' . \App\Support\Money::format($payment->amount) . ' | ' . $payment->program->customer->customer_name . ' | ' . $payment->program->customer->city->short_title,
                    'data_option' => $this->formatSupplierPaymentOptionPayload($payment),
                ];
            }
        }

        return view('cr.generate', compact('payment_options', 'voucher_options'));
    }

    private function formatCustomerPaymentOptionPayload(CustomerPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'date' => $payment->date?->format('Y-m-d'),
            'method' => $payment->method,
            'amount' => $payment->amount,
            'bank_account_id' => $payment->bank_account_id,
            'cheque_no' => $payment->cheque_no,
            'slip_no' => $payment->slip_no,
            'reff_no' => $payment->cheque_no ?? $payment->slip_no ?? $payment->transaction_id ?? $payment->reff_no,
            'cheque_date' => $payment->cheque_date?->format('Y-m-d'),
            'slip_date' => $payment->slip_date?->format('Y-m-d'),
            'customer' => $payment->customer ? [
                'id' => $payment->customer->id,
                'customer_name' => $payment->customer->customer_name,
                'city' => [
                    'id' => $payment->customer->city?->id,
                    'title' => $payment->customer->city?->title,
                    'short_title' => $payment->customer->city?->short_title,
                ],
            ] : null,
        ];
    }

    private function formatSupplierPaymentOptionPayload(SupplierPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'date' => $payment->date?->format('Y-m-d'),
            'method' => $payment->method,
            'amount' => $payment->amount,
            'bank_account_id' => $payment->bank_account_id,
            'transaction_id' => $payment->transaction_id,
            'program_id' => $payment->program_id,
        ];
    }

    private function formatBankAccountOptionPayload(BankAccount $account): array
    {
        return [
            'id' => $account->id,
            'category' => $account->category,
            'account_title' => $account->account_title,
            'bank' => [
                'id' => $account->bank?->id,
                'title' => $account->bank?->title,
                'short_title' => $account->bank?->short_title,
            ],
        ];
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
            'date' => 'required|date',
            'voucher_no' => 'required|string',
            'voucher_id' => 'required|integer|exists:vouchers,id',
            'c_r_no' => 'required|string',
            'returnPayments' => 'required|json',
            'newPayments' => 'required|json',
        ]);

        // Check for validation errors
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $returnPayments = $this->decodeJsonArray($request->returnPayments, 'returnPayments');
        $newPayments = $this->decodeJsonArray($request->newPayments, 'newPayments');
        $voucher = Voucher::findOrFail($request->voucher_id);

        $this->validateCrPayload($voucher, $returnPayments, $newPayments);

        $data = [
            'date' => $request->date,
            'voucher_no' => $request->voucher_no,
            'voucher_id' => $request->voucher_id,
            'c_r_no' => $request->c_r_no,
            'return_payments' => $returnPayments,
            'new_payments' => $newPayments,
        ];

        if (!str_starts_with($data['c_r_no'], 'CR-')) {
            $data['c_r_no'] = 'CR-' . $data['c_r_no'];
        }

        $returnEmpty = empty($data['return_payments']);
        $newEmpty = empty($data['new_payments']);

        if ($returnEmpty && $newEmpty) {
            return redirect()->back()->with('error', 'Payments not selected and Payments not added.');
        }

        if ($returnEmpty) {
            return redirect()->back()->with('error', 'Payments not selected.');
        }

        if ($newEmpty) {
            return redirect()->back()->with('error', 'Payments not added.');
        }

        DB::transaction(function () use (&$data, $voucher) {
        foreach($data['return_payments'] as $payment) {
            SupplierPayment::find($payment->id)->update(['is_return' => true]);
            CustomerPayment::find($payment->payment_id)->update(['is_return' => true]);
        }

        $cr = new CR($data);
        $cr->save(); // 👈 pehle save karenge taake $cr->id mil jaye

        foreach ($data['new_payments'] as $payment) {
            if ($payment->method == 'Payment Program') {
                SupplierPayment::find($payment->data_value)
                    ?->update(['method' => $payment->method . ' | CR']);
                $payment->payment_id = (int) $payment->data_value;
            } else {
                $columnMap = [
                    'Self Cheque' => 'cheque_no',
                    'Cheque'      => 'cheque_id',
                    'Slip'        => 'slip_id',
                ];

                // Skip unknown methods
                if (!isset($columnMap[$payment->method])) {
                    continue;
                }

                $newSupplierPayment = SupplierPayment::create([
                    'supplier_id'      => $voucher->supplier_id,
                    'date'             => $data['date'],
                    'method'           => $payment->method . ' | CR',
                    'amount'           => $payment->amount,
                    'bank_account_id'  => $payment->bank_account_id ?? null,
                    'voucher_id'       => null,
                    'c_r_id'           => $cr->id, // 👈 ab yahan id set ho jaegi
                    $columnMap[$payment->method] => $payment->data_value,
                ]);

                $payment->payment_id = $newSupplierPayment->id;
            }
        }

        $cr->new_payments = $data['new_payments'];
        $cr->save(); // 👈 dubara save karenge taake new_payments update ho jaye
        });

        return redirect()->route('cr.create')->with('success', 'CR Generated successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    private function decodeJsonArray(string $json, string $field): array
    {
        $decoded = json_decode($json);

        if (!is_array($decoded)) {
            throw ValidationException::withMessages([
                $field => 'Invalid payment payload.',
            ]);
        }

        return $decoded;
    }

    private function validateCrPayload(Voucher $voucher, array $returnPayments, array $newPayments): void
    {
        if (empty($returnPayments) || empty($newPayments)) {
            throw ValidationException::withMessages([
                'returnPayments' => 'Payments not selected and payments not added.',
            ]);
        }

        $returnTotal = 0;
        foreach ($returnPayments as $index => $payment) {
            $rowNumber = $index + 1;
            $supplierPaymentId = (int) ($payment->id ?? 0);
            $customerPaymentId = (int) ($payment->payment_id ?? 0);

            $supplierPayment = SupplierPayment::whereKey($supplierPaymentId)
                ->where('voucher_id', $voucher->id)
                ->first();
            $customerPayment = CustomerPayment::find($customerPaymentId);

            if (!$supplierPayment || !$customerPayment) {
                throw ValidationException::withMessages([
                    'returnPayments' => "Selected return payment row {$rowNumber} is invalid.",
                ]);
            }

            $returnTotal += (float) $supplierPayment->amount;
        }

        $newTotal = 0;
        foreach ($newPayments as $index => $payment) {
            $rowNumber = $index + 1;
            $method = (string) ($payment->method ?? '');
            $amount = $payment->amount ?? null;

            if (!in_array($method, ['Payment Program', 'Self Cheque', 'Cheque', 'Slip'], true)) {
                throw ValidationException::withMessages([
                    'newPayments' => "New payment row {$rowNumber} has an invalid method.",
                ]);
            }

            if ($amount === null || !is_numeric($amount) || (float) $amount <= 0) {
                throw ValidationException::withMessages([
                    'newPayments' => "New payment row {$rowNumber} amount must be greater than zero.",
                ]);
            }

            if (empty($payment->data_value)) {
                throw ValidationException::withMessages([
                    'newPayments' => "New payment row {$rowNumber} has an invalid reference.",
                ]);
            }

            if ($method === 'Payment Program' && !SupplierPayment::whereKey($payment->data_value)->whereNull('voucher_id')->exists()) {
                throw ValidationException::withMessages([
                    'newPayments' => "New payment row {$rowNumber} has an invalid payment program.",
                ]);
            }

            if (in_array($method, ['Cheque', 'Slip'], true) && !CustomerPayment::whereKey($payment->data_value)->exists()) {
                throw ValidationException::withMessages([
                    'newPayments' => "New payment row {$rowNumber} has an invalid customer payment.",
                ]);
            }

            if ($method === 'Self Cheque' && !BankAccount::whereKey($payment->bank_account_id ?? null)->exists()) {
                throw ValidationException::withMessages([
                    'newPayments' => "New payment row {$rowNumber} has an invalid bank account.",
                ]);
            }

            $newTotal += (float) $amount;
        }

        if (abs($returnTotal - $newTotal) > 0.01) {
            throw ValidationException::withMessages([
                'newPayments' => 'The total added amount must match the total selected amount.',
            ]);
        }
    }
}
