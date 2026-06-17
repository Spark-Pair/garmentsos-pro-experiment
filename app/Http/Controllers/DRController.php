<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DR;
use App\Models\Setup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DRController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        // $drs = DR::with('customer.city')->orderBy('id', 'desc')->get();
        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');

        if ($request->ajax()) {
            $drs = DR::orderByDesc('id')
                ->applyFilters($request);

            return response()->json(['data' => $drs, 'authLayout' => $authLayout]);
        }

        return view('dr.index', compact('authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $customer_options = Customer::select('id', 'customer_name', 'city_id')
            ->distinct()
            ->whereHas('user', function ($query) {
                $query->where('status', 'active');
            })
            ->orderBy('customer_name')
            ->get()
            ->mapWithKeys(function ($customer) {
                return [
                    (int)$customer->id => [
                        'text' => ucfirst($customer->customer_name) . ' | ' . strtoupper($customer->city->short_title),
                    ]
                ];
            });

        $bank_options = Setup::where('type', 'bank_name')
            ->distinct()
            ->orderBy('title')
            ->get()
            ->mapWithKeys(function ($bank) {
                return [
                    (int)$bank->id => [
                        'text' => ucfirst($bank->title),
                    ]
                ];
            });

        return view('dr.generate', compact('customer_options', 'bank_options'));
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
            'customer_id' => 'required|integer|exists:customers,id',
            'date' => 'required|date',
            'returnPayments' => 'required|json',
            'newPayments' => 'required|json',
        ]);

        // Check for validation errors
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $returnPayments = $this->decodeJsonArray($request->returnPayments, 'returnPayments');
        $newPayments = $this->decodeJsonArray($request->newPayments, 'newPayments');

        $this->validateDrPayload((int) $request->customer_id, $returnPayments, $newPayments);

        $data = [
            'customer_id' => $request->customer_id,
            'date' => $request->date,
            'return_payments' => $returnPayments,
            'new_payments_data' => $newPayments,
        ];

        $returnEmpty = empty($data['return_payments']);
        $newEmpty = empty($data['new_payments_data']);

        if ($returnEmpty && $newEmpty) {
            return redirect()->back()->with('error', 'Payments not selected and Payments not added.');
        }

        if ($returnEmpty) {
            return redirect()->back()->with('error', 'Payments not selected.');
        }

        if ($newEmpty) {
            return redirect()->back()->with('error', 'Payments not added.');
        }

        DB::transaction(function () use (&$data) {
            $data['new_payments'] = [];
            $data['d_r_no'] = 'DR-PENDING-' . uniqid();

            $dr = new DR($data);
            $dr->save(); // 👈 pehle save karenge taake $dr->id mil jaye
            $dr->d_r_no = 'DR-' . $dr->id;
            $dr->save(); // 👈 pehle save karenge taake $dr->id mil jaye

            foreach($data['return_payments'] as $paymentId) {
                CustomerPayment::find($paymentId)->update(['clear_date' => $data['date'], 'd_r_id' => $dr->id]);
            }

            foreach ($data['new_payments_data'] as $payment) {
                $newPayment = CustomerPayment::create([
                    'customer_id'     => $data['customer_id'],
                    'date'            => $payment->date ?? $data['date'],
                    'type'            => 'DR',
                    'method'          => strtolower($payment->method),
                    'amount'          => $payment->amount,
                    'cheque_no'          => $payment->cheque_no ?? null,
                    'slip_no'          => $payment->slip_no ?? null,
                    'transaction_id'          => $payment->transaction_id ?? null,
                    'cheque_date'          => $payment->cheque_date ?? null,
                    'slip_date'          => $payment->slip_date ?? null,
                    'bank_id'          => $payment->bank_id ?? null,
                    'remarks'          => $payment->remarks ?? null,
                ]);

                $data['new_payments'][] = $newPayment->id;
            }

            $dr->new_payments = $data['new_payments'];
            $dr->save(); // 👈 dubara save karenge taake new_payments update ho jaye
        });

        return redirect()->route('dr.create')->with('success', 'DR Generated successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(DR $dR)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DR $dR)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DR $dR)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DR $dR)
    {
        //
    }

    public function getPayments(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $payments = CustomerPayment::where('customer_id', $request->customer_id)->whereIn('method', ['cheque', 'slip'])->whereNull('d_r_id')->where('is_return', true)->get();

        return response()->json(['status' => 'success', 'data' => $payments]);
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

    private function validateDrPayload(int $customerId, array $returnPayments, array $newPayments): void
    {
        if (empty($returnPayments) || empty($newPayments)) {
            throw ValidationException::withMessages([
                'returnPayments' => 'Payments not selected and payments not added.',
            ]);
        }

        $returnTotal = 0;
        foreach ($returnPayments as $index => $paymentId) {
            $rowNumber = $index + 1;
            $payment = CustomerPayment::whereKey($paymentId)
                ->where('customer_id', $customerId)
                ->whereIn('method', ['cheque', 'slip'])
                ->whereNull('d_r_id')
                ->where('is_return', true)
                ->first();

            if (!$payment) {
                throw ValidationException::withMessages([
                    'returnPayments' => "Selected return payment row {$rowNumber} is invalid.",
                ]);
            }

            $returnTotal += (float) $payment->amount;
        }

        $newTotal = 0;
        foreach ($newPayments as $index => $payment) {
            $rowNumber = $index + 1;
            $method = (string) ($payment->method ?? '');
            $amount = $payment->amount ?? null;

            if (!in_array($method, ['cash', 'cheque', 'slip', 'online'], true)) {
                throw ValidationException::withMessages([
                    'newPayments' => "New payment row {$rowNumber} has an invalid method.",
                ]);
            }

            if ($amount === null || !is_numeric($amount) || (float) $amount <= 0) {
                throw ValidationException::withMessages([
                    'newPayments' => "New payment row {$rowNumber} amount must be greater than zero.",
                ]);
            }

            if ($method === 'cheque') {
                if (empty($payment->cheque_no) || empty($payment->cheque_date) || !strtotime((string) $payment->cheque_date)) {
                    throw ValidationException::withMessages([
                        'newPayments' => "New payment row {$rowNumber} must include valid cheque details.",
                    ]);
                }

                if (!Setup::whereKey($payment->bank_id ?? null)->exists()) {
                    throw ValidationException::withMessages([
                        'newPayments' => "New payment row {$rowNumber} has an invalid bank.",
                    ]);
                }
            }

            if ($method === 'slip' && (empty($payment->slip_no) || empty($payment->slip_date) || !strtotime((string) $payment->slip_date))) {
                throw ValidationException::withMessages([
                    'newPayments' => "New payment row {$rowNumber} must include valid slip details.",
                ]);
            }

            if ($method === 'online') {
                if (empty($payment->transaction_id) || empty($payment->date) || !strtotime((string) $payment->date)) {
                    throw ValidationException::withMessages([
                        'newPayments' => "New payment row {$rowNumber} must include valid online payment details.",
                    ]);
                }

                if (!Setup::whereKey($payment->bank_id ?? null)->exists()) {
                    throw ValidationException::withMessages([
                        'newPayments' => "New payment row {$rowNumber} has an invalid bank.",
                    ]);
                }
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
