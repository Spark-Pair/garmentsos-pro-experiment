<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statement_adjustments', function (Blueprint $table) {
            $table->id();
            $table->morphs('adjustable');
            $table->date('date');
            $table->string('entry_type');
            $table->string('direction');
            $table->decimal('amount', 14, 2);
            $table->string('remarks')->nullable();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statement_adjustments');
    }
};
