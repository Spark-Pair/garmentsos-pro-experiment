<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('branch_module_settings')) {
            return;
        }

        $safeModules = ['articles', 'vouchers', 'productions'];
        $hasBranchId = Schema::hasColumn('branch_module_settings', 'branch_id');

        foreach ($safeModules as $moduleKey) {
            $query = DB::table('branch_module_settings')->where('module_key', $moduleKey);
            if ($hasBranchId) {
                $query->whereNull('branch_id');
            }

            $query->update([
                'branch_enabled' => true,
                'allow_user_switching' => true,
                'status' => 'active',
                'updated_at' => now(),
            ]);

            if ($hasBranchId && Schema::hasTable('branches')) {
                foreach (DB::table('branches')->pluck('id') as $branchId) {
                    $updated = DB::table('branch_module_settings')
                        ->where('branch_id', $branchId)
                        ->where('module_key', $moduleKey)
                        ->update([
                            'branch_enabled' => true,
                            'allow_user_switching' => true,
                            'status' => 'active',
                            'metadata' => json_encode(['safe_default_repair' => true]),
                            'updated_at' => now(),
                        ]);

                    if ($updated === 0) {
                        DB::table('branch_module_settings')->insert([
                            'branch_id' => $branchId,
                            'module_key' => $moduleKey,
                            'branch_enabled' => true,
                            'allow_user_switching' => true,
                            'status' => 'active',
                            'metadata' => json_encode(['safe_default_repair' => true]),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Do not disable safe modules on rollback; that could hide branchable pages again.
    }
};
