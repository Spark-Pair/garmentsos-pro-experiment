<?php

namespace App\Http\Controllers;

use App\Models\Cargo;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CargoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!$this->checkRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        $authLayout = $this->getAuthLayout($request->route()->getName());

        if ($request->ajax()) {
            $orders = Cargo::orderByDesc('id')
                ->applyFilters($request);

            return response()->json(['data' => $orders, 'authLayout' => $authLayout]);
        }

        // $cargos = Cargo::all();
        return view('cargos.index', compact('authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (!$this->checkRole(['developer', 'owner', 'admin', 'accountant'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        $invoices = Invoice::with('customer.city')
            ->whereNotNull('shipment_no')
            ->where(function ($query) {
                $query->whereNull('cargo_name')
                    ->orWhere('cargo_name', '');
            })
            ->get()
            ->values();

        $last_cargo = [];
        $last_cargo = Cargo::orderby('id', 'desc')->first();

        if (!$last_cargo) {
            $last_cargo = new Cargo();
            $last_cargo->cargo_no = '0000';
        }

        return view('cargos.generate', compact('invoices', 'last_cargo'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!$this->checkRole(['developer', 'owner', 'admin', 'accountant'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'cargo_name' => 'required|string',
            'cargo_no' => 'required|string',
            'invoices_array' => 'required|json',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::transaction(function () use ($request) {
            $invoicesArray = json_decode($request->invoices_array, true) ?? [];
            $invoiceIds = collect($invoicesArray)->pluck('id')->filter()->values();

            if ($invoiceIds->isNotEmpty()) {
                Invoice::whereIn('id', $invoiceIds)->update([
                    'cargo_name' => $request->cargo_name,
                ]);
            }

            Cargo::create([
                'date' => $request->date,
                'cargo_name' => $request->cargo_name,
                'cargo_no' => $request->cargo_no,
                'invoices_array' => $request->invoices_array,
            ]);
        });

        return redirect()->back()->with(['success' => 'Cargo List Generated Successfuly!']);
    }

    /**
     * Display the specified resource.
     */
    public function show(Cargo $cargo)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Cargo $cargo)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Cargo $cargo)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Cargo $cargo)
    {
        //
    }
}
