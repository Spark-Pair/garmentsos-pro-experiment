<?php

namespace App\Traits;

use App\Models\CustomerPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait DRComputed
{
    public function toFormattedArray()
    {
        $newPayments = collect($this->new_payments ?? []);
        $returnPayments = collect($this->return_payments ?? []);
        $allIds = $returnPayments
            ->merge($newPayments)
            ->filter()
            ->unique()
            ->values();

        $paymentsMap = $allIds->isEmpty()
            ? collect()
            : CustomerPayment::with(['bankAccount.bank', 'customer'])
                ->whereIn('id', $allIds)
                ->get()
                ->keyBy('id');

        $mapPayment = function ($id, string $type) use ($paymentsMap) {
            $cp = $paymentsMap->get($id);
            $reff = $cp?->cheque_no ?? $cp?->slip_no ?? $cp?->transaction_id ?? $cp?->reff_no ?? '-';
            return [
                'type' => $type,
                'date' => $cp?->date?->format('d-M-Y, D'),
                'method' => $cp?->method ?? '-',
                'amount' => $cp?->amount ?? 0,
                'reff' => $reff,
                'beneficiary' => $cp?->customer?->customer_name ?? '-',
                'account_title' => $cp?->bankAccount?->account_title ?? '-',
                'bank' => $cp?->bankAccount?->bank?->short_title ?? '-',
            ];
        };

        return [
            'id' => $this->id,
            'date' => $this->date->format('d-M-Y, D'),
            'd_r_no' => $this->d_r_no,
            'customer_name' => $this->customer->customer_name . ' | ' . $this->customer->city->title,
            'new_payments_count' => $newPayments->count(),
            'return_payments_count' => $returnPayments->count(),
            'new_payments_amount' => $newPayments->sum('amount'),
            'return_payments_amount' => $returnPayments->sum('amount'),
            'new_payments_details' => $newPayments->map(fn($id) => $mapPayment($id, 'New'))->values(),
            'return_payments_details' => $returnPayments->map(fn($id) => $mapPayment($id, 'Return'))->values(),
            'data' => [
                'id' => $this->id,
                'd_r_no' => $this->d_r_no,
                'date' => $this->date,
                'customer_id' => $this->customer_id,
                'new_payments' => $this->new_payments,
                'return_payments' => $this->return_payments,
            ],
            'oncontextmenu' => "generateContextMenu(event)",
            'onclick' => "generateModal(this)",
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'customer_name':
                return $query->where(function ($query) use ($value) {
                    $query->whereHas('customer', function ($q) use ($value) {
                        $q->where('customer_name', 'like', "%{$value}%")
                        ->orWhereHas('city', function ($q) use ($value) {
                            $q->where('title', 'like', "%{$value}%");
                        });
                    });
                });

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
