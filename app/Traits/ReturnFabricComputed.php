<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait ReturnFabricComputed
{
    public function toFormattedArray()
    {
        return [
            'id' => $this->id,
            'type' => 'Returned',
            'tag' => $this->tag,
            'quantity' => $this->quantity,
            'date' => $this->date->format('d-M-Y, D'),
            'employee_name' => $this->worker->employee_name,
            'remarks' => $this->remarks,
            'created_at' => $this->created_at,
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'employee_name':
                return $query->where(function ($query) use ($value) {
                    $query->whereHas('worker', function ($q) use ($value) {
                        $q->where('employee_name', 'like', "%{$value}%");
                    });
                });

            case 'type':
                if ($value === 'Issued') {
                    return $query->whereRaw('1 = 0');
                } elseif ($value === 'Received') {
                    return $query->whereRaw('1 = 0');
                } elseif ($value === 'Returned') {
                    return $query->where('quantity', '>', 0);
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
