<?php

namespace App\Services;

use App\Models\Article;
use App\Models\InvoiceArticles;
use App\Models\PhysicalQuantity;
use App\Models\ShipmentArticles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PhysicalQuantityReportService
{
    public function getIndexRows(Request|array $filters = [], ?int $limit = null): Collection
    {
        $query = PhysicalQuantity::with('article')
            ->orderByDesc('id');

        $this->applyFilters($query, $filters);

        if ($limit) {
            $query->limit($limit);
        }

        $rows = $query->get()->filter(fn (PhysicalQuantity $row) => $row->article);

        return $this->mapArticleRows($rows);
    }

    public function getArticleReportRows(array $filters = []): Collection
    {
        return $this->getIndexRows($filters)
            ->map(function (array $row) {
                $processedBy = trim((string) ($row['processed_by'] ?? ''));
                return [
                    'article_no' => $row['article_no'] ?? '-',
                    'proceed_by' => $processedBy !== '' ? $processedBy : '-',
                    'received_qty' => $row['received_quantity'] ?? $this->formatPacketQuantity((float) ($row['received_packets_numeric'] ?? 0)),
                    'remaining_qty' => $row['remaining_quantity'] ?? $this->formatPacketQuantity((float) ($row['remaining_packets_numeric'] ?? 0)),
                    'received_qty_numeric' => (float) ($row['received_packets_numeric'] ?? 0),
                    'remaining_qty_numeric' => (float) ($row['remaining_packets_numeric'] ?? 0),
                ];
            })
            ->sortBy(function (array $row) {
                return mb_strtolower(($row['article_no'] ?? '') . ' ' . ($row['proceed_by'] ?? ''));
            })
            ->values();
    }

    public function getArticleOptions(): array
    {
        return Article::query()
            ->orderByDesc('id')
            ->get(['id', 'article_no', 'processed_by'])
            ->mapWithKeys(function (Article $article) {
                $processedBy = trim((string) $article->processed_by);
                $suffix = $processedBy !== '' ? ' | ' . $processedBy : '';

                return [
                    $article->id => [
                        'text' => $article->article_no . $suffix,
                    ],
                ];
            })
            ->all();
    }

    protected function applyFilters(Builder $query, Request|array $filters): void
    {
        if ($filters instanceof Request) {
            $query->applyFilters($filters, false);
            return;
        }

        if (!empty($filters['article_id'])) {
            $query->where('article_id', (int) $filters['article_id']);
        }

        if (!empty($filters['article_no'])) {
            $articleNo = $filters['article_no'];
            $query->whereHas('article', fn (Builder $articleQuery) => $articleQuery->where('article_no', 'like', "%{$articleNo}%"));
        }

        if (!empty($filters['processed_by'])) {
            $processedBy = mb_strtolower(trim((string) $filters['processed_by']));
            $query->whereHas('article', function (Builder $articleQuery) use ($processedBy) {
                $articleQuery->whereRaw('LOWER(processed_by) LIKE ?', ["%{$processedBy}%"]);
            });
        }

        if (!empty($filters['shipment']) && in_array($filters['shipment'], ['karachi', 'other', 'all'], true)) {
            $shipment = $filters['shipment'];

            $query->whereHas('article.shipmentArticles.shipment', function (Builder $shipmentQuery) use ($shipment) {
                if ($shipment === 'karachi') {
                    $shipmentQuery->where('city', 'karachi');
                } elseif ($shipment === 'other') {
                    $shipmentQuery->where('city', '!=', 'karachi');
                }
            });
        }
    }

    protected function mapArticleRows(Collection $rows): Collection
    {
        $articleIds = $rows->pluck('article_id')->unique()->values();

        if ($articleIds->isEmpty()) {
            return collect();
        }

        $invoicedMap = InvoiceArticles::query()
            ->whereIn('article_id', $articleIds)
            ->selectRaw('article_id, COALESCE(SUM(invoice_pcs), 0) as total_pcs')
            ->groupBy('article_id')
            ->pluck('total_pcs', 'article_id');

        $shipmentCitiesMap = ShipmentArticles::query()
            ->whereIn('article_id', $articleIds)
            ->whereHas('shipment')
            ->with('shipment:id,city')
            ->get()
            ->groupBy('article_id')
            ->map(fn (Collection $items) => $items->pluck('shipment.city')->filter()->unique()->values());

        return $rows
            ->groupBy('article_id')
            ->map(function (Collection $items) use ($invoicedMap, $shipmentCitiesMap) {
                /** @var \App\Models\PhysicalQuantity $model */
                $model = $items->first();
                $article = $model->article;
                $packets = (float) $items->sum('packets');
                $pcsPerPacket = (float) ($article->pcs_per_packet ?: 0);
                $totalPcs = (float) ($article->quantity + $article->extra_pcs);
                $totalPackets = $pcsPerPacket > 0 ? ($totalPcs / $pcsPerPacket) : 0;
                $soldPackets = $pcsPerPacket > 0 ? ((float) $article->sold_quantity / $pcsPerPacket) : 0;
                $currentStockPackets = $packets - $soldPackets;
                $remainingPackets = $totalPackets - $packets;
                $invoicePcs = (float) ($invoicedMap[$model->article_id] ?? 0);
                $invoicePackets = $pcsPerPacket > 0 ? ($invoicePcs / $pcsPerPacket) : 0;
                $shipment = $this->resolveShipment($shipmentCitiesMap->get($model->article_id, collect()));

                return [
                    'article_id' => $model->article_id,
                    'article_no' => $article->article_no,
                    'processed_by' => $article->processed_by,
                    'unit' => $article->pcs_per_packet,
                    'total_quantity' => floor($totalPcs / 12) . ' - Dz. | ' . $totalPackets . ' - Pkts.',
                    'received_quantity' => $packets . ' - Pkts.',
                    'current_stock' => $currentStockPackets . ' - Pkts.',
                    'invoiced_quantity' => $invoicePackets . ' - Pkts.',
                    'a_category' => $items->where('category', 'a')->sum('packets') . ' - Pkts.',
                    'b_category' => $items->where('category', 'b')->sum('packets') . ' - Pkts.',
                    'c_category' => $items->where('category', 'c')->sum('packets') . ' - Pkts.',
                    'remaining_quantity' => $remainingPackets . ' - Pkts.',
                    'shipment' => $shipment,
                    'total_packets_numeric' => $totalPackets,
                    'received_packets_numeric' => $packets,
                    'remaining_packets_numeric' => $remainingPackets,
                    'onclick' => 'generateModal(this)',
                    'oncontextmenu' => 'generateContextMenu(event)',
                ];
            })
            ->values();
    }

    protected function resolveShipment(Collection $cities): string
    {
        if ($cities->isEmpty()) {
            return '-';
        }

        $normalizedCities = $cities
            ->map(fn ($city) => mb_strtolower((string) $city))
            ->filter()
            ->unique()
            ->values();

        $hasKarachi = $normalizedCities->contains('karachi');

        if ($hasKarachi && $normalizedCities->count() === 1) {
            return 'Karachi';
        }

        if ($hasKarachi && $normalizedCities->count() > 1) {
            return 'All';
        }

        return 'Other';
    }

    protected function formatPacketQuantity(float|int $value): string
    {
        $formatted = number_format((float) $value, 2, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted . ' - Pkts.';
    }
}
