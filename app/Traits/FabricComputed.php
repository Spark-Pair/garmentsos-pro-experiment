<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait FabricComputed
{
    public function toFormattedArray()
    {
        return [
            'id' => $this->id,
            'type' => 'Received',
            'tag' => $this->tag,
            'quantity' => $this->quantity,
            'date' => $this->date->format('d-M-Y, D'),
            'supplier_name' => $this->supplier->supplier_name,
            'fabric' => $this->fabric->title,
            'color' => $this->color,
            'unit' => $this->unit,
            'remarks' => $this->remarks,
            'created_at' => $this->created_at,
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'supplier_name':
                return $query->where(function ($query) use ($value) {
                    $query->whereHas('supplier', function ($q) use ($value) {
                        $q->where('supplier_name', 'like', "%{$value}%");
                    });
                });
                
            case 'type':
                if ($value === 'Issued') {
                    return $query->whereRaw('1 = 0');
                } elseif ($value === 'Received') {
                    return $query->where('quantity', '>', 0);
                } elseif ($value === 'Returned') {
                    return $query->whereRaw('1 = 0');
                }
                return $query;

            case 'fabric':
                return $query->where('fabric_id', $value);

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
