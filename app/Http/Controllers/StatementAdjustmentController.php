<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\StatementAdjustment;
use App\Models\Supplier;
use App\Models\CustomerPayment;
use App\Models\SupplierPayment;
use App\Models\PaymentClear;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StatementAdjustmentController extends Controller
{
    public function create()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $categoryOptions = [
            'customer' => ['text' => 'Customer'],
            'supplier' => ['text' => 'Supplier'],
            'bank_account' => ['text' => 'Bank Account'],
        ];

        $entryTypeOptions = [
            'opening_balance' => ['text' => 'Opening Balance'],
            'adjustment' => ['text' => 'Adjustment'],
        ];

        $directionOptions = [
            'plus' => ['text' => 'Debit'],
            'minus' => ['text' => 'Credit'],
        ];

        return view('statement-adjustments.create', compact('categoryOptions', 'entryTypeOptions', 'directionOptions'));
    }

    public function firstTransactionDate(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'category' => 'required|in:customer,supplier,bank_account',
            'adjustable_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $modelClass = $this->resolveAdjustableClass($request->category);
        $adjustable = $modelClass::find($request->adjustable_id);

        if (!$adjustable) {
            return response()->json(['error' => 'Selected record not found.'], 404);
        }

        $date = $this->resolveOpeningBalanceDate($request->category, $adjustable);

        return response()->json([
            'date' => $date?->toDateString(),
        ]);
    }

    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'category' => 'required|in:customer,supplier,bank_account',
            'adjustable_id' => 'required|integer',
            'date' => 'required|date',
            'entry_type' => 'required|in:opening_balance,adjustment',
            'direction' => 'required|in:plus,minus',
            'amount' => 'required|numeric|min:0.01',
            'remarks' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $modelClass = $this->resolveAdjustableClass($request->category);
        $adjustable = $modelClass::find($request->adjustable_id);

        if (!$adjustable) {
            return redirect()->back()->withErrors(['adjustable_id' => 'Selected record not found.'])->withInput();
        }

        $resolvedDate = $request->date;
        if ($request->entry_type === 'opening_balance') {
            $openingBalanceDate = $this->resolveOpeningBalanceDate($request->category, $adjustable);
            if ($openingBalanceDate) {
                $resolvedDate = $openingBalanceDate->toDateString();
            }
        }

        DB::transaction(function () use ($request, $adjustable, $resolvedDate) {
            $adjustable->statementAdjustments()->create([
                'date' => $resolvedDate,
                'entry_type' => $request->entry_type,
                'direction' => $request->direction,
                'amount' => $request->amount,
                'remarks' => $request->remarks,
            ]);
        });

        return redirect()->route('statement-adjustments.create')->with('success', 'Opening balance / adjustment added successfully.');
    }

    private function resolveAdjustableClass(string $category): string
    {
        return match ($category) {
            'customer' => Customer::class,
            'supplier' => Supplier::class,
            'bank_account' => BankAccount::class,
        };
    }

    private function resolveFirstTransactionDate(string $category, $adjustable): ?Carbon
    {
        if ($category === 'customer' && $adjustable instanceof Customer) {
            $invoiceMin = $adjustable->invoices()
                ->whereNotNull('shipment_no')
                ->min('date');
            $paymentMin = $adjustable->payments()
                ->where('type', '!=', 'DR')
                ->min('date');

            return $this->minCarbonDate([$invoiceMin, $paymentMin]);
        }

        if ($category === 'supplier' && $adjustable instanceof Supplier) {
            $expenseMin = $adjustable->expenses()->min('date');
            $paymentMin = $adjustable->payments()
                ->whereNotNull('voucher_id')
                ->whereIn('method', [
                    'Cheque',
                    'Cash',
                    'Slip',
                    'ATM',
                    'Self Cheque',
                    'Program',
                    'Adjustment',
                    'program',
                ])
                ->min('date');

            $productionMin = null;
            if ($adjustable->worker) {
                $productionMin = $adjustable->worker->productions()->min('receive_date');
            }

            return $this->minCarbonDate([$expenseMin, $paymentMin, $productionMin]);
        }

        if ($category === 'bank_account' && $adjustable instanceof BankAccount) {
            $adjustMin = $adjustable->statementAdjustments()->min('date');

            if ($adjustable->category === 'self') {
                $normalMin = CustomerPayment::query()
                    ->where('bank_account_id', $adjustable->id)
                    ->whereNull('cheque_no')
                    ->whereNull('slip_no')
                    ->min('date');

                $outflowMin = SupplierPayment::query()
                    ->where('bank_account_id', $adjustable->id)
                    ->min('date');

                $chequeVoucherMin = $this->minVoucherDateForCustomerPayments(
                    bankAccountId: $adjustable->id,
                    mode: 'cheque'
                );

                $slipVoucherMin = $this->minVoucherDateForCustomerPayments(
                    bankAccountId: $adjustable->id,
                    mode: 'slip'
                );

                return $this->minCarbonDate([$normalMin, $outflowMin, $chequeVoucherMin, $slipVoucherMin, $adjustMin]);
            }

            $clearMin = PaymentClear::query()
                ->where('bank_account_id', $adjustable->id)
                ->where('method', '!=', 'cash')
                ->min('clear_date');

            return $this->minCarbonDate([$clearMin, $adjustMin]);
        }

        return null;
    }

    private function resolveOpeningBalanceDate(string $category, $adjustable): ?Carbon
    {
        $firstDate = $this->resolveFirstTransactionDate($category, $adjustable);

        return $firstDate?->copy()->subDay();
    }

    private function minVoucherDateForCustomerPayments(int $bankAccountId, string $mode): ?string
    {
        $mode = strtolower($mode);
        if (!in_array($mode, ['cheque', 'slip'], true)) {
            return null;
        }

        $fkColumn = $mode === 'cheque' ? 'cheque_id' : 'slip_id';

        return DB::table('customer_payments as cp')
            ->join('supplier_payments as sp', function ($join) use ($fkColumn) {
                $join->on("sp.$fkColumn", '=', 'cp.id')
                    ->where('sp.is_return', '=', 0);
            })
            ->join('vouchers as v', 'v.id', '=', 'sp.voucher_id')
            ->where('cp.bank_account_id', $bankAccountId)
            ->whereNotNull($mode === 'cheque' ? 'cp.cheque_no' : 'cp.slip_no')
            ->min('v.date');
    }

    private function minCarbonDate(array $dates): ?Carbon
    {
        $clean = array_values(array_filter($dates, fn($d) => filled($d)));
        if (count($clean) === 0) {
            return null;
        }

        $min = collect($clean)
            ->map(fn($d) => Carbon::parse($d)->startOfDay())
            ->sort()
            ->first();

        return $min ?: null;
    }
}
