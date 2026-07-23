<?php

namespace App\Services\Production;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Production;
use App\Models\ProductionMaterial;
use App\Models\ProductionTag;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductionItemSyncService
{
    public function sync(Production $production, array $tags, array $materials): void
    {
        if (!$this->tablesReady()) {
            return;
        }

        DB::transaction(function () use ($production, $tags, $materials) {
            ProductionTag::where('production_id', $production->id)->delete();
            ProductionMaterial::where('production_id', $production->id)->delete();
            InventoryTransaction::where('source_type', Production::class)
                ->where('source_id', $production->id)
                ->delete();

            foreach ($this->normalizeTags($tags) as $tag) {
                ProductionTag::create([
                    'production_id' => $production->id,
                    'branch_id' => $production->branch_id,
                    'tag' => $tag['tag'],
                    'quantity' => $tag['quantity'],
                    'unit' => $tag['unit'] ?? null,
                    'worker_id' => $production->worker_id,
                ]);
            }

            foreach ($this->normalizeMaterials($materials) as $material) {
                $row = ProductionMaterial::create([
                    'production_id' => $production->id,
                    'branch_id' => $production->branch_id,
                    'inventory_item_id' => $material['inventory_item_id'] ?? null,
                    'title' => $material['title'],
                    'unit' => $material['unit'] ?? null,
                    'quantity' => $material['quantity'],
                    'unit_price' => $material['unit_price'] ?? null,
                    'amount' => $material['amount'] ?? null,
                ]);

                if (!empty($material['inventory_item_id'])) {
                    $item = InventoryItem::find($material['inventory_item_id']);
                    InventoryTransaction::create([
                        'branch_id' => $production->branch_id,
                        'inventory_item_id' => $material['inventory_item_id'],
                        'direction' => 'out',
                        'date' => $production->issue_date ?? $production->receive_date ?? now()->toDateString(),
                        'quantity' => $material['quantity'],
                        'unit' => $material['unit'] ?? $item?->unit,
                        'unit_price' => $material['unit_price'] ?? null,
                        'amount' => $material['amount'] ?? null,
                        'source_type' => Production::class,
                        'source_id' => $production->id,
                        'reference_no' => $production->ticket,
                        'remarks' => 'Used in production material: ' . $row->title,
                    ]);
                }
            }
        });
    }

    public function tagsForPayload(Production $production): array
    {
        if (!$this->tablesReady()) {
            return $this->normalizeTags($production->tags ?? [])->values()->all();
        }

        $production->loadMissing('productionTags');
        if ($production->productionTags->isNotEmpty()) {
            return $production->productionTags
                ->map(fn ($row) => [
                    'tag' => $row->tag,
                    'quantity' => $row->quantity,
                    'unit' => $row->unit,
                ])
                ->values()
                ->all();
        }

        return $this->normalizeTags($production->tags ?? [])->values()->all();
    }

    public function materialsForPayload(Production $production): array
    {
        if (!$this->tablesReady()) {
            return $this->normalizeMaterials($production->materials ?? [])->values()->all();
        }

        $production->loadMissing('productionMaterials.inventoryItem');
        if ($production->productionMaterials->isNotEmpty()) {
            return $production->productionMaterials
                ->map(fn ($row) => [
                    'inventory_item_id' => $row->inventory_item_id,
                    'title' => $row->title,
                    'unit' => $row->unit,
                    'quantity' => $row->quantity,
                    'unit_price' => $row->unit_price,
                    'amount' => $row->amount,
                    'source' => $row->inventory_item_id ? 'inventory' : 'manual',
                    'stock_quantity' => $row->inventoryItem?->stock_quantity,
                ])
                ->values()
                ->all();
        }

        return $this->normalizeMaterials($production->materials ?? [])->values()->all();
    }

    public function normalizeTags(array|Collection $tags): Collection
    {
        return collect($tags)
            ->map(function ($item) {
                $item = (array) $item;
                return [
                    'tag' => trim((string) ($item['tag'] ?? '')),
                    'quantity' => (float) ($item['quantity'] ?? 0),
                    'unit' => $item['unit'] ?? null,
                ];
            })
            ->filter(fn ($item) => $item['tag'] !== '' && $item['quantity'] > 0)
            ->values();
    }

    public function normalizeMaterials(array|Collection $materials): Collection
    {
        return collect($materials)
            ->map(function ($item) {
                $item = (array) $item;
                $quantity = (float) ($item['quantity'] ?? 0);
                $unitPrice = isset($item['unit_price']) && $item['unit_price'] !== ''
                    ? (float) $item['unit_price']
                    : null;

                return [
                    'inventory_item_id' => !empty($item['inventory_item_id']) ? (int) $item['inventory_item_id'] : null,
                    'title' => trim((string) ($item['title'] ?? $item['name'] ?? '')),
                    'unit' => $item['unit'] ?? null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'amount' => isset($item['amount']) && $item['amount'] !== ''
                        ? (float) $item['amount']
                        : ($unitPrice !== null ? $unitPrice * $quantity : null),
                ];
            })
            ->filter(fn ($item) => $item['title'] !== '' && $item['quantity'] > 0)
            ->values();
    }

    public function tablesReady(): bool
    {
        return Schema::hasTable('production_tags')
            && Schema::hasTable('production_materials')
            && Schema::hasTable('inventory_transactions');
    }
}
