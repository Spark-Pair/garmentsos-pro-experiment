<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\StatementAdjustment;
use App\Models\Supplier;
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
            'plus' => ['text' => 'Plus (+)'],
            'minus' => ['text' => 'Minus (-)'],
        ];

        return view('statement-adjustments.create', compact('categoryOptions', 'entryTypeOptions', 'directionOptions'));
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

        DB::transaction(function () use ($request, $adjustable) {
            $adjustable->statementAdjustments()->create([
                'date' => $request->date,
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
}
