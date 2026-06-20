<?php

namespace App\Traits;

use App\Models\Invoice;
use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait ShipmentComputed
{
    public function toFormattedArray()
    {
        $articles = $this->articles
            ? $this->articles->map(fn($shipmentArticle) => [
                'id' => $shipmentArticle->id,
                'description' => $shipmentArticle->description,
                'ordered_pcs' => (int) ($shipmentArticle->ordered_pcs ?? 0),
                'invoice_pcs' => (int) ($shipmentArticle->invoice_pcs ?? 0),
                'shipment_pcs' => (int) ($shipmentArticle->shipment_pcs ?? 0),
                'article' => $shipmentArticle->article ? [
                    'id' => $shipmentArticle->article->id,
                    'article_no' => $shipmentArticle->article->article_no,
                    'description' => $shipmentArticle->article->description,
                    'pcs_per_packet' => $shipmentArticle->article->pcs_per_packet,
                    'sales_rate' => $shipmentArticle->article->sales_rate,
                ] : null,
            ])->values()
            : collect();

        return [
            'id' => $this->id,
            'name' => $this->shipment_no,
            'details' => [
                'Amount' => \App\Support\Money::format($this->netAmount),
                'Date' => $this->date->format('d-M-Y, D'),
            ],
            'isInvoiceHas' => $this->invoices()->exists(),
            'data' => [
                'id' => $this->id,
                'shipment_no' => $this->shipment_no,
                'date' => $this->date,
                'discount' => (float) ($this->discount ?? 0),
                'netAmount' => (float) ($this->netAmount ?? 0),
                'articles' => $articles,
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
