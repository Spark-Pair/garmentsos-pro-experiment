<?php

namespace App\Http\Controllers;

use App\Models\Setup;
use App\Models\UtilityAccount;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\Request;

class UtilityAccountController extends Controller
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

        // $utilityAccounts = UtilityAccount::with('billType', 'location')->get();

        if ($request->ajax()) {
            $utilityAccounts = app(ModuleBranchService::class)
                ->applyScope(UtilityAccount::orderByDesc('id'), 'utility_accounts')
                ->applyFilters($request);

            return response()->json(['data' => $utilityAccounts, 'authLayout' => $authLayout]);
        }

        return view('utility-accounts.index', compact('authLayout'));
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
        $bill_types = $branches->applyRelatedScope(Setup::where('type', 'utility_bill_type'), 'setups', 'utility_accounts')->get();
        $locations = $branches->applyRelatedScope(Setup::where('type', 'utility_bill_location'), 'setups', 'utility_accounts')->get();

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

        return view('utility-accounts.add', compact('bill_type_options', 'location_options'));
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
            'bill_type_id' => 'required|integer|exists:setups,id',
            'location_id' => 'required|integer|exists:setups,id',
            'account_title' => 'required|string|max:200',
            'account_no' => 'required|string|max:200'
        ]);

        $branches = app(ModuleBranchService::class);
        $billType = $branches->applyRelatedScope(Setup::where('type', 'utility_bill_type'), 'setups', 'utility_accounts')->find($request->bill_type_id);
        $location = $branches->applyRelatedScope(Setup::where('type', 'utility_bill_location'), 'setups', 'utility_accounts')->find($request->location_id);
        if (!$billType || !$location) {
            return redirect()->back()->withErrors(['bill_type_id' => 'Selected bill type/location is not available for this branch.'])->withInput();
        }

        UtilityAccount::create($branches->assignBranchOnCreate([
            'bill_type_id' => $request->bill_type_id,
            'location_id' => $request->location_id,
            'account_title' => $request->account_title,
            'account_no' => $request->account_no,
        ], 'utility_accounts'));

        return redirect()->back()->with('success', 'Utility account added successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(UtilityAccount $utilityAccount)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($utilityAccount, 'utility_accounts');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(UtilityAccount $utilityAccount)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($utilityAccount, 'utility_accounts');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, UtilityAccount $utilityAccount)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($utilityAccount, 'utility_accounts');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(UtilityAccount $utilityAccount)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($utilityAccount, 'utility_accounts');
    }
}
