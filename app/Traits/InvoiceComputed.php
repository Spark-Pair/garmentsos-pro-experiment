<?php

namespace App\Traits;

use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait InvoiceComputed
{
    public function toFormattedArray()
    {
        $invoiceArticles = $this->invoiceArticles
            ? $this->invoiceArticles->map(fn($invoiceArticle) => [
                'id' => $invoiceArticle->id,
                'description' => $invoiceArticle->description,
                'invoice_pcs' => (int) ($invoiceArticle->invoice_pcs ?? 0),
                'returned_pcs' => (int) $this->salesReturns
                    ->where('article_id', $invoiceArticle->article_id)
                    ->sum('quantity'),
                'ordered_pcs' => (int) ($invoiceArticle->ordered_pcs ?? 0),
                'shipment_pcs' => (int) ($invoiceArticle->shipment_pcs ?? 0),
                'article' => $invoiceArticle->article ? [
                    'id' => $invoiceArticle->article->id,
                    'article_no' => $invoiceArticle->article->article_no,
                    'description' => $invoiceArticle->article->description,
                    'pcs_per_packet' => $invoiceArticle->article->pcs_per_packet,
                    'sales_rate' => $invoiceArticle->article->sales_rate,
                ] : null,
            ])->values()
            : collect();

        return [
            'id' => $this->id,
            'name' => $this->invoice_no,
            'details' => [
                'Customer' => $this->customer->customer_name . ' | ' . $this->customer->city->title,
                'Date' => $this->date->format('d-M-Y, D'),
                'Amount' => \App\Support\Money::format($this->netAmount),
                'Reff. No.' => $this->order_no ?? $this->shipment_no,
            ],
            'data' => [
                'id' => $this->id,
                'invoice_no' => $this->invoice_no,
                'order_no' => $this->order_no,
                'shipment_no' => $this->shipment_no,
                'date' => $this->date,
                'netAmount' => (float) ($this->netAmount ?? 0),
                'cotton_count' => (int) ($this->cotton_count ?? 0),
                'customer' => $this->customer ? [
                    'id' => $this->customer->id,
                    'customer_name' => $this->customer->customer_name,
                    'urdu_title' => $this->customer->urdu_title,
                    'address' => $this->customer->address,
                    'phone_number' => $this->customer->phone_number,
                    'city' => $this->customer->city ? [
                        'id' => $this->customer->city->id,
                        'title' => $this->customer->city->title,
                        'short_title' => $this->customer->city->short_title,
                    ] : null,
                ] : null,
                'order' => $this->order ? [
                    'id' => $this->order->id,
                    'order_no' => $this->order->order_no,
                    'discount' => (float) ($this->order->discount ?? 0),
                    'netAmount' => (float) ($this->order->netAmount ?? 0),
                ] : null,
                'shipment' => $this->shipment ? [
                    'id' => $this->shipment->id,
                    'shipment_no' => $this->shipment->shipment_no,
                    'discount' => (float) ($this->shipment->discount ?? 0),
                    'netAmount' => (float) ($this->shipment->netAmount ?? 0),
                ] : null,
                'invoice_articles' => $invoiceArticles,
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

            case 'reff_no':
                return $query->where('order_no', 'like', "%$value%")->orWhere('shipment_no', 'like', "%$value%");

            case 'city':
                return $query->whereHas('customer', function ($q) use ($value) {
                    $q->whereHas('city', fn($sq) => $sq->where('title', 'like', "%$value%"));
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
