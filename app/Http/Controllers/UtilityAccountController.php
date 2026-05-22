<?php

namespace App\Http\Controllers;

use App\Models\Setup;
use App\Models\UtilityAccount;
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
            $utilityAccounts = UtilityAccount::orderByDesc('id')
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

        $bill_types = Setup::where('type', 'utility_bill_type')->get();
        $locations = Setup::where('type', 'utility_bill_location')->get();

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

        UtilityAccount::create([
            'bill_type_id' => $request->bill_type_id,
            'location_id' => $request->location_id,
            'account_title' => $request->account_title,
            'account_no' => $request->account_no,
        ]);

        return redirect()->back()->with('success', 'Utility account added successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(UtilityAccount $utilityAccount)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(UtilityAccount $utilityAccount)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, UtilityAccount $utilityAccount)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(UtilityAccount $utilityAccount)
    {
        //
    }
}
