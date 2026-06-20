<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait DailyLedgerDepositComputed
{
    public function toFormattedArray()
    {
        return [
            'id' => $this->id,
            'date' => $this->date->format('d-M-Y, D'),
            'description' => ucfirst($this->method) . ' | ' . ($this->reff_no ?? '-'),
            'deposit' => $this->amount,
            'use' => 0,
            'created_at' => $this->created_at,
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'description':
                return $query->where('method', 'like', "%{$value}%")->orWhere('reff_no', 'like', "%{$value}%");

            case 'type':
                if ($value === 'deposit') {
                    return $query->where('amount', '>', 0);
                } elseif ($value === 'use') {
                    return $query->whereRaw('1 = 0');
                }
                return $query;

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
