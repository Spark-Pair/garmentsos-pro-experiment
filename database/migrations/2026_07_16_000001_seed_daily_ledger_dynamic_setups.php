<?php

use Database\Seeders\DailyLedgerSetupSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('setups')) {
            return;
        }

        $now = now();
        $branchId = null;
        if (Schema::hasColumn('setups', 'branch_id') && Schema::hasTable('branches')) {
            $branchId = DB::table('branches')->where('is_main', true)->value('id')
                ?: DB::table('branches')->orderBy('id')->value('id');
        }

        foreach (DailyLedgerSetupSeeder::items() as $item) {
            $exists = DB::table('setups')
                ->where('type', $item['type'])
                ->where('title', $item['title'])
                ->exists();

            if ($exists) {
                continue;
            }

            $row = [
                'type' => $item['type'],
                'title' => $item['title'],
                'short_title' => $item['short_title'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('setups', 'branch_id')) {
                $row['branch_id'] = $branchId;
            }

            DB::table('setups')->insert($row);
        }
    }

    public function down(): void
    {
        // Keep client setup data once added.
    }
};
