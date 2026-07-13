<?php

namespace App\Http\Controllers;

use App\Models\Setup;
use App\Models\UtilityAccount;
use App\Models\UtilityBill;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\Request;

class UtilityBillController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }
        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');

        // $utilityBills = UtilityBill::with('account.billType', 'account.location')->get();

        if ($request->ajax()) {
            $utilityBills = app(ModuleBranchService::class)
                ->applyScope(UtilityBill::orderByDesc('id'), 'utility_bills')
                ->applyFilters($request);

            return response()->json(['data' => $utilityBills, 'authLayout' => $authLayout]);
        }

        return view('utility-bills.index', compact('authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        $bill_type_options = [];
        $location_options = [];

        $branches = app(ModuleBranchService::class);
        $bill_types = $branches->applyRelatedScope(Setup::where('type', 'utility_bill_type'), 'setups', 'utility_bills')->get();
        $locations = $branches->applyRelatedScope(Setup::where('type', 'utility_bill_location'), 'setups', 'utility_bills')->get();

        foreach($bill_types as $type) {
            $bill_type_options[(int)$type->id] = [
                'text' => $type->title
            ];
        }

        foreach($locations as $location) {
            $location_options[(int)$location->id] = [
                'text' => $location->title
            ];
        }

        return view('utility-bills.add', compact('bill_type_options', 'location_options'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        $request->validate([
            'account_id' => 'required|integer|exists:utility_accounts,id',
            'month' => 'required|date_format:Y-m',
            'units' => 'nullable|integer',
            'amount' => 'required|numeric|min:1',
            'due_date' => 'required|date',
        ]);

        $branches = app(ModuleBranchService::class);
        $account = $branches->applyRelatedScope(UtilityAccount::query(), 'utility_accounts', 'utility_bills')->find($request->account_id);
        if (!$account) {
            return redirect()->back()->withErrors(['account_id' => 'Selected utility account is not available for this branch.'])->withInput();
        }

        UtilityBill::create($branches->assignBranchOnCreate([
            'account_id' => $request->account_id,
            'month' => $request->month,
            'units' => $request->units,
            'amount' => $request->amount,
            'due_date' => $request->due_date,
        ], 'utility_bills'));

        return redirect()->back()->with('success', 'Utility Bill added successfully.');
    }

    public function markPaid(Request $request, UtilityBill $utilityBill)
    {
        if(!$this->checkRole(['developer', 'owner', 'admin', 'accountant']))
        {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        app(ModuleBranchService::class)->assertRecordInAllowedBranch($utilityBill, 'utility_bills');

        $utilityBill->is_paid = true;
        $utilityBill->save();

        return response()->json([
            'success' => true,
            'message' => 'Bill marked as paid successfully.',
        ]);
    }
}
