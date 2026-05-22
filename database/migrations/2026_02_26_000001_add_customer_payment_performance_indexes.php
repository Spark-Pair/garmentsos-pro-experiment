<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->index(['customer_id', 'bank_id', 'cheque_date', 'cheque_no'], 'cp_customer_bank_cheque_lookup_idx');
            $table->index(['customer_id', 'slip_date', 'slip_no'], 'cp_customer_slip_lookup_idx');
            $table->index('transaction_id', 'cp_transaction_id_idx');
            $table->index('program_id', 'cp_program_id_idx');
        });

        Schema::table('payment_programs', function (Blueprint $table) {
            $table->index('status', 'pp_status_idx');
            $table->index('customer_id', 'pp_customer_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->dropIndex('cp_customer_bank_cheque_lookup_idx');
            $table->dropIndex('cp_customer_slip_lookup_idx');
            $table->dropIndex('cp_transaction_id_idx');
            $table->dropIndex('cp_program_id_idx');
        });

        Schema::table('payment_programs', function (Blueprint $table) {
            $table->dropIndex('pp_status_idx');
            $table->dropIndex('pp_customer_id_idx');
        });
    }
};
