<?php

namespace App\Services\Branches;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class BranchSerialService
{
    public function __construct(private readonly ModuleBranchService $branches)
    {
    }

    public function next(string $moduleKey, string $modelClass, string $column, ?string $middle = null, int $pad = 6): string
    {
        $branchId = $this->branches->shouldFilterRecords($moduleKey)
            ? $this->branches->selectedBranchIdForModule($moduleKey)
            : null;

        if (!$branchId) {
            return $this->nextGlobal($modelClass, $column, $pad);
        }

        $branch = Branch::query()->find($branchId);
        $prefix = $this->cleanPrefix($branch?->prefix ?: $branch?->code ?: 'BR');
        $base = $middle ? "{$prefix}-{$middle}-" : "{$prefix}-";
        $query = $modelClass::query()->where($column, 'like', $base . '%');

        /** @var Model $model */
        $model = new $modelClass();
        if (Schema::hasColumn($model->getTable(), 'branch_id')) {
            $query->where($model->getTable() . '.branch_id', $branchId);
        }

        $last = $query->orderByDesc('id')->value($column);
        $serial = $this->numericTail($last) + 1;

        return $base . str_pad((string) $serial, $pad, '0', STR_PAD_LEFT);
    }

    public function nextProductionTicket(string $workPrefix): string
    {
        return $this->next('productions', \App\Models\Production::class, 'ticket', $this->cleanPrefix($workPrefix), 3);
    }

    private function nextGlobal(string $modelClass, string $column, int $pad): string
    {
        $last = $modelClass::query()->orderByDesc('id')->value($column);
        $serial = $this->numericTail($last) + 1;

        return str_pad((string) $serial, $pad, '0', STR_PAD_LEFT);
    }

    private function numericTail(?string $value): int
    {
        if (!$value || !preg_match('/(\d+)$/', $value, $matches)) {
            return 0;
        }

        return (int) $matches[1];
    }

    private function cleanPrefix(string $prefix): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]+/', '', $prefix)) ?: 'BR';
    }
}
