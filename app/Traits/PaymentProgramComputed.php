<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait PaymentProgramComputed
{
    public function beneficiary(): Attribute
    {
        return Attribute::get(function () {

            if ($this->category == 'supplier') return $this->subCategory?->supplier_name ?? '-';
            if ($this->category == 'self_account') return $this->subCategory?->account_title ?? '-';
            if ($this->category == 'waiting') return $this->remarks ?? '-';

            return '-';
        });
    }

    public function toFormattedArray()
    {
        $paymentRows = $this->customerPayments->map(function ($payment) {
            $bankAccount = $payment->bankAccount;

            return [
                'id' => $payment->id,
                'date' => $payment->date,
                'amount' => (float) $payment->amount,
                'transaction_id' => $payment->transaction_id,
                'bank_account' => $bankAccount ? [
                    'id' => $bankAccount->id,
                    'account_title' => $bankAccount->account_title,
                    'bank' => $bankAccount->bank ? [
                        'id' => $bankAccount->bank->id,
                        'short_title' => $bankAccount->bank->short_title,
                        'title' => $bankAccount->bank->title,
                    ] : null,
                    'sub_category' => $bankAccount->subCategory ? [
                        'id' => $bankAccount->subCategory->id,
                        'supplier_name' => $bankAccount->subCategory->supplier_name ?? null,
                        'customer_name' => $bankAccount->subCategory->customer_name ?? null,
                    ] : null,
                ] : null,
            ];
        })->values();

        return [
            'id' => $this->id,
            'date' => $this->date->format('d-M-Y, D'),
            'customer_name' => ($this->customer?->customer_name ?? '-') . ' | ' . ($this->customer?->city?->title ?? '-'),
            'o_p_no' => $this->order_no ?? $this->program_no,
            'category' => $this->category,
            'beneficiary' => $this->beneficiary,
            'amount' => $this->amount,
            'payment' => $this->payment,
            'balance' => $this->balance,
            'status' => $this->status,
            'type' => $this->order_no ? 'order' : 'program',
            'sub_category' => $this->subCategory ? [
                'id' => $this->subCategory->id,
            ] : null,
            'data' => [
                'id' => $this->id,
                'sub_category_id' => $this->sub_category_id,
                'sub_category_type' => $this->sub_category_type,
                'payments' => $paymentRows,
            ],
            'oncontextmenu' => "generateContextMenu(event)",
            'onclick' => "generateModal(this)",
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'customer_name':
                return $query->whereHas('customer', function ($q) use ($value) {
                    $q->where('customer_name', 'like', "%$value%")
                    ->orWhereHas('city', fn($sq) => $sq->where('title', 'like', "%$value%"));
                });

            case 'city':
                return $query->whereHas('customer.city', function ($q) use ($value) {
                    // Nested function isliye taake 'OR' condition sirf city ke andar rahe
                    $q->where(function($sq) use ($value) {
                        $sq->where('title', 'like', "%{$value}%")
                        ->orWhere('short_title', 'like', "%{$value}%");
                    });
                });

            case 'type':
                return $query->where(function ($q) use ($value) {
                    if ($value == 'order') {
                        $q->whereNotNull('order_no');
                    } else {
                        $q->whereNull('order_no');
                    }
                });

            case 'beneficiary':
                return $query->where(function($q) use ($value) {
                    // Has subCategory: check supplier_name or account_title
                    $q->whereHas('subCategory', function($sq) use ($value) {
                        $sq->where('supplier_name', 'like', "%$value%")
                        ->orWhere('account_title', 'like', "%$value%");
                    })
                    // Does NOT have subCategory: check remarks
                    ->orWhere(function($q) use ($value) {
                        $q->whereDoesntHave('subCategory')
                        ->where('remarks', 'like', "%$value%");
                    });
                });

            case 'status':
                return $query->where('status', $value);

            case 'date':
                $start = $value['start'] ?? null;
                $end   = $value['end'] ?? null;

                if (!$start || !$end) return $query;


                \App\Support\DateRange::apply($query, 'date', $start, $end);
                return $query;

            default:
                return $query->where($key, 'like', "%$value%");
        }
    }
}
