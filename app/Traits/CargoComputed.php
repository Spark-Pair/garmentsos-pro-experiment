<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait CargoComputed
{
    public function toFormattedArray()
    {
        $invoices = collect($this->invoices)->map(fn($invoice) => [
            'id' => $invoice->id,
            'invoice_no' => $invoice->invoice_no,
            'date' => $invoice->date,
            'cotton_count' => (int) ($invoice->cotton_count ?? 0),
            'customer' => $invoice->customer ? [
                'id' => $invoice->customer->id,
                'customer_name' => $invoice->customer->customer_name,
                'city' => $invoice->customer->city ? [
                    'id' => $invoice->customer->city->id,
                    'title' => $invoice->customer->city->title,
                    'short_title' => $invoice->customer->city->short_title,
                ] : null,
            ] : null,
        ])->values();

        return [
            'id' => $this->id,
            'name' => $this->cargo_no,
            'details' => [
                'Cargo Name' => $this->cargo_name,
                'Date' => $this->date->format('d-M-Y, D'),
            ],
            'data' => [
                'id' => $this->id,
                'cargo_no' => $this->cargo_no,
                'cargo_name' => $this->cargo_name,
                'date' => $this->date,
                'invoices' => $invoices,
            ],
            'oncontextmenu' => "generateContextMenu(event)",
            'onclick' => "generateModal(this)",
        ];
    }

    public function scopeApplyModelFilters($query, $key, $value)
    {
        switch ($key) {
            case 'date':
                $start = $value['start'] ?? null;
                $end   = $value['end'] ?? null;

                if (!$start || !$end) return $query->where('method', 'cash');


                return $query->where(function ($q) use ($start, $end) {
                    // 1️⃣ slip_date exists
                    $q->Where(function ($q) use ($start, $end) {
                        $q->whereBetween('date', [$start.' 00:00:00', $end.' 23:59:59']);
                    });
                });

            default:
                return $query->where($key, 'like', "%$value%");
        }
    }
}
