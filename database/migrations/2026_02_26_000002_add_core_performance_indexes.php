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
        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->index(['program_id', 'transaction_id', 'bank_account_id', 'date', 'amount'], 'sp_program_lookup_idx');
            $table->index('voucher_id', 'sp_voucher_id_idx');
            $table->index('cheque_id', 'sp_cheque_id_idx');
            $table->index('slip_id', 'sp_slip_id_idx');
        });

        Schema::table('payment_clears', function (Blueprint $table) {
            $table->index(['payment_id', 'clear_date'], 'pc_payment_clear_date_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['customer_id', 'date'], 'orders_customer_date_idx');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->index(['date', 'city'], 'shipments_date_city_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['shipment_no', 'cargo_name'], 'invoices_shipment_cargo_idx');
            $table->index(['customer_id', 'date'], 'invoices_customer_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropIndex('sp_program_lookup_idx');
            $table->dropIndex('sp_voucher_id_idx');
            $table->dropIndex('sp_cheque_id_idx');
            $table->dropIndex('sp_slip_id_idx');
        });

        Schema::table('payment_clears', function (Blueprint $table) {
            $table->dropIndex('pc_payment_clear_date_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_customer_date_idx');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex('shipments_date_city_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_shipment_cargo_idx');
            $table->dropIndex('invoices_customer_date_idx');
        });
    }
};
