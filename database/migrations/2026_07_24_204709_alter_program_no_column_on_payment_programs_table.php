<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_programs', function (Blueprint $table) {
            $table->string('program_no')->change();
        });
    }

    public function down(): void
    {
        Schema::table('payment_programs', function (Blueprint $table) {
            $table->integer('program_no')->change();
            $table->unique('program_no');
        });
    }
};
