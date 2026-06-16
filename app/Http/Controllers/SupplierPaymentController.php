<?php

namespace App\Http\Controllers;

use App\Models\SupplierPayment;
use App\Models\Customer;
use App\Models\Setup;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class SupplierPaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }
        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');

        if ($request->ajax()) {
            $payments = SupplierPayment::with([
                'bankAccount.bank',
                'selfAccount.bank',
                'cheque.paymentClearRecord.bankAccount.bank',
                'slip.paymentClearRecord.bankAccount.bank',
                'program.customerPayments.paymentClearRecord.bankAccount.bank',
                'program.customerPayments.bankAccount.bank',
                'program.customer.city',
                'cheque.customer.city',
                'slip.customer.city',
                'voucher',
            ])->orderByDesc('id')->applyFilters($request);

            return response()->json(['data' => $payments, 'authLayout' => $authLayout]);
        }

        return view("supplier-payments.index", compact('authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

    }

    /**
     * Display the specified resource.
     */
    public function show(SupplierPayment $supplierPayment)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SupplierPayment $supplierPayment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SupplierPayment $supplierPayment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SupplierPayment $supplierPayment)
    {
        //
    }
}
