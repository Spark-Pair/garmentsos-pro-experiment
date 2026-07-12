<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('branches') && !Schema::hasColumn('branches', 'prefix')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->string('prefix', 20)->nullable()->after('code');
            });
        }

        if (Schema::hasTable('branches') && Schema::hasColumn('branches', 'prefix')) {
            DB::table('branches')
                ->whereNull('prefix')
                ->orWhere('prefix', '')
                ->orderBy('id')
                ->get(['id', 'code', 'name', 'is_main'])
                ->each(function ($branch) {
                    $base = $branch->is_main ? 'MAIN' : ($branch->code ?: $branch->name ?: 'BR');
                    $prefix = strtoupper(preg_replace('/[^A-Z0-9]+/', '', $base)) ?: 'BR';
                    DB::table('branches')->where('id', $branch->id)->update([
                        'prefix' => substr($prefix, 0, 20),
                    ]);
                });
        }

        if (Schema::hasTable('user_module_branch_preferences')) {
            Schema::table('user_module_branch_preferences', function (Blueprint $table) {
                if (!Schema::hasColumn('user_module_branch_preferences', 'selection_mode')) {
                    $table->string('selection_mode', 20)->default('single')->after('branch_id');
                }

                if (!Schema::hasColumn('user_module_branch_preferences', 'branch_ids')) {
                    $table->json('branch_ids')->nullable()->after('selection_mode');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_module_branch_preferences')) {
            Schema::table('user_module_branch_preferences', function (Blueprint $table) {
                if (Schema::hasColumn('user_module_branch_preferences', 'branch_ids')) {
                    $table->dropColumn('branch_ids');
                }

                if (Schema::hasColumn('user_module_branch_preferences', 'selection_mode')) {
                    $table->dropColumn('selection_mode');
                }
            });
        }

        if (Schema::hasTable('branches') && Schema::hasColumn('branches', 'prefix')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropColumn('prefix');
            });
        }
    }
};
