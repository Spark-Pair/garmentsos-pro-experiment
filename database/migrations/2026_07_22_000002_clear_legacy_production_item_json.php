<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('productions')) {
            return;
        }

        if (!Schema::hasTable('production_tags') || !Schema::hasTable('production_materials')) {
            return;
        }

        DB::table('productions')
            ->where(function ($query) {
                $query->whereNotNull('tags')
                    ->orWhereNotNull('materials');
            })
            ->where(function ($query) {
                $query->whereExists(function ($subQuery) {
                    $subQuery->selectRaw('1')
                        ->from('production_tags')
                        ->whereColumn('production_tags.production_id', 'productions.id');
                })->orWhereExists(function ($subQuery) {
                    $subQuery->selectRaw('1')
                        ->from('production_materials')
                        ->whereColumn('production_materials.production_id', 'productions.id');
                });
            })
            ->update([
                'tags' => null,
                'materials' => null,
            ]);
    }

    public function down(): void
    {
        // Legacy JSON arrays are intentionally not reconstructed from normalized rows.
    }
};
