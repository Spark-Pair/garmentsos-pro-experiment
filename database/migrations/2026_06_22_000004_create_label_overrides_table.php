<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('label_key');
            $table->string('locale')->default('en');
            $table->string('default_text')->nullable();
            $table->string('override_text');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['label_key', 'locale']);
            $table->index('label_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_overrides');
    }
};
