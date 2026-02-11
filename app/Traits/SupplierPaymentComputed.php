<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait SupplierPaymentComputed
{
    /**
     * Voucher number computation
     */
    public function newReffNo(): Attribute
    {
        return Attribute::get(function () {

            // direct relations
            if ($this->cheque) return $this->cheque->cheque_no;
            if ($this->slip) return $this->slip->slip_no;
            if ($this->cheque_no) return $this->cheque_no;
            if ($this->reff_no) return $this->reff_no;
            if ($this->transaction_id) return $this->transaction_id;

            return null;
        });
    }

    public function toFormattedArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->supplier->supplier_name ?? app('client_company')->name,
            'method' => $this->method,
            'date' => $this->slip_date ? $this->slip_date->format('d-M-Y, D') : ($this->cheque_date ? $this->cheque_date->format('d-M-Y, D') : $this->date->format('d-M-Y, D')),
            'amount' => number_format($this->amount, 1),
            'reff_no' => $this->new_reff_no,
            'voucher_no' => $this->voucher->voucher_no ?? $this->CR->c_r_no ?? '-',
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'supplier_name':
                return $query->whereHas('supplier', function ($q) use ($value) {
                    $q->where('supplier_name', 'like', "%$value%");
                });

            case 'method':
                return $query->where('method', $value);

            case 'reff_no':
                return $query->where(function ($q) use ($value) {

                    $q->whereHas('cheque', function ($q2) use ($value) {
                        $q2->where('cheque_no', 'like', "%$value%");
                    })

                    ->orWhereHas('slip', function ($q2) use ($value) {
                        $q2->where('slip_no', 'like', "%$value%");
                    })

                    ->orWhere('transaction_id', 'like', "%$value%")
                    ->orWhere('cheque_no', 'like', "%$value%")
                    ->orWhere('reff_no', 'like', "%$value%");
                });


            case 'voucher_no':
                return $query->whereHas('voucher', function ($q) use ($value) {
                    $q->where('voucher_no', 'like', "%$value%");
                });

            default:
                return $query->where($key, 'like', "%$value%");
        }
    }
}
