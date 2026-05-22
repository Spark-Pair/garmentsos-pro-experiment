<?php

namespace App\Http\Controllers;

use App\Models\Bilty;
use App\Models\Invoice;
use Illuminate\Http\Request;

class BiltyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // $bilties = Bilty::with('invoice.customer.city')->get();
        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');

        if ($request->ajax()) {
            $orders = Bilty::with('invoice.customer.city')->orderByDesc('id')
                ->applyFilters($request);

            return response()->json(['data' => $orders, 'authLayout' => $authLayout]);
        }

        return view('bilties.show', compact('authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $invoices = Invoice::with('customer.city')
            ->doesntHave('bilty')
            ->get();

        return view("bilties.add", compact('invoices'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'invoices_array' => 'required|json',
        ]);

        $invoicesArray = json_decode($request->invoices_array, true);

        // Validate that all invoices have biltyNo
        foreach ($invoicesArray as $invoice) {
            if (!isset($invoice['biltyNo'])) {
                return redirect()->back()->with('error', 'All invoices must have a Bilty number assigned');
            }
        }

        // Create bilties for each invoice
        foreach ($invoicesArray as $invoice) {
            Bilty::create([
                'date' => $request->date,
                'invoice_id' => $invoice['id'],
                'bilty_no' => $invoice['biltyNo'],
            ]);

            $updateData = array_filter([
                'cargo_name' => $invoice['cargoName'] ?? null,
                'cotton_count' => $invoice['cottonCount'] ?? null,
            ], fn($value) => !is_null($value));

            if (!empty($updateData)) {
                Invoice::where('id', $invoice['id'])->update($updateData);
            }
        }

        return redirect()->back()->with('success', 'Bilties created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Bilty $bilty)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Bilty $bilty)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Bilty $bilty)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Bilty $bilty)
    {
        //
    }
}
