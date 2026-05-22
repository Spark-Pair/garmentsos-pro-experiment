<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait OrderComputed
{
    public function toFormattedArray()
    {
        $customerName = trim(($this->customer?->customer_name ?? '-') . ' | ' . ($this->customer?->city?->title ?? '-'), ' |');
        $remainingOrder = $this->articles
            ? $this->articles->sum(function ($article) {
                $ordered = (int) ($article->ordered_pcs ?? 0);
                $dispatched = (int) ($article->dispatched_pcs ?? 0);

                return max(0, $ordered - $dispatched);
            })
            : 0;

        return [
            'id' => $this->id,
            'name' => $this->order_no,
            'order_no' => $this->order_no,
            'date' => $this->date?->format('d-M-Y, D'),
            'customer_name' => $customerName,
            'discount' => (float) ($this->discount ?? 0),
            'net_amount' => (float) ($this->netAmount ?? 0),
            'balance_order' => $remainingOrder,
            'details' => [
                'Date' => $this->date?->format('d-M-Y, D'),
                'Order No' => $this->order_no,
                'Customer' => $customerName,
                'Discount' => number_format((float) ($this->discount ?? 0), 1) . '%',
                'Net Amount' => \App\Support\Money::format((float) ($this->netAmount ?? 0)),
                'Balance Order' => number_format($remainingOrder) . ' Pcs',
            ],
            'status' => $this->status,
            'data' => $this,
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
