<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait DailyLedgerUseComputed
{
    public function toFormattedArray()
    {
        return [
            'id' => $this->id,
            'ledger_type' => 'use',
            'date' => $this->date->format('d-M-Y, D'),
            'date_raw' => $this->date?->format('Y-m-d'),
            'description' => ucfirst($this->case) . ' | ' . ($this->remarks ?? '-'),
            'case' => $this->case,
            'remarks' => $this->remarks,
            'deposit' => 0,
            'use' => $this->amount,
            'created_at' => $this->created_at,
            'oncontextmenu' => "generateContextMenu(event)",
            'onclick' => "generateModal(this)",
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'description':
                return $query->where('case', 'like', "%{$value}%")->orWhere('remarks', 'like', "%{$value}%");

            case 'type':
                if ($value === 'deposit') {
                    return $query->whereRaw('1 = 0');
                } elseif ($value === 'use') {
                    return $query->where('amount', '>', 0);
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
