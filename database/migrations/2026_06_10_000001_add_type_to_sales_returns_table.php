<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->string('type')->default('return')->after('invoice_id');
            $table->index(['article_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->dropIndex(['article_id', 'type']);
            $table->dropColumn('type');
        });
    }
};
