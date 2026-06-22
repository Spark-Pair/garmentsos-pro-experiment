<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_settings', function (Blueprint $table) {
            $table->id();
            $table->string('module_key')->unique();
            $table->boolean('enabled')->default(true);
            $table->boolean('visible_in_sidebar')->default(true);
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['enabled', 'visible_in_sidebar']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_settings');
    }
};
