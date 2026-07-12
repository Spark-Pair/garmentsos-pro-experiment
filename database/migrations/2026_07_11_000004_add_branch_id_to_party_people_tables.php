<?php

use App\Services\Branches\ModuleBranchService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['customers', 'suppliers', 'employees', 'users'] as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('branch_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('branches')
                    ->nullOnDelete();
            });
        }

        $mainBranchId = app(ModuleBranchService::class)->mainBranch()?->id;
        if (!$mainBranchId) {
            return;
        }

        foreach (['customers', 'suppliers', 'employees', 'users'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            DB::table($table)
                ->whereNull('branch_id')
                ->update(['branch_id' => $mainBranchId]);
        }
    }

    public function down(): void
    {
        foreach (['customers', 'suppliers', 'employees', 'users'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('branch_id');
            });
        }
    }
};
