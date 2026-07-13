<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('branches')) {
            return;
        }

        $mainBranchId = DB::table('branches')->where('is_main', true)->value('id')
            ?: DB::table('branches')->orderBy('id')->value('id');

        if (!$mainBranchId) {
            return;
        }

        foreach ($this->tables() as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            if (!Schema::hasColumn($tableName, 'branch_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('branch_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('branches')
                        ->nullOnDelete();
                });
            }

            DB::table($tableName)
                ->whereNull('branch_id')
                ->update(['branch_id' => $mainBranchId]);
        }
    }

    public function down(): void
    {
        // Forward-only: keep branch ownership data once assigned.
    }

    private function tables(): array
    {
        return [
            'payment_programs',
            'bank_accounts',
            'daily_ledger_deposits',
            'daily_ledger_uses',
            'utility_accounts',
            'utility_bills',
            'statement_adjustments',
            'bilties',
            'c_r_s',
            'd_r_s',
            'fabrics',
            'issued_fabrics',
            'return_fabrics',
            'rates',
            'setups',
            'attendances',
            'salaries',
            'employee_payments',
        ];
    }
};
