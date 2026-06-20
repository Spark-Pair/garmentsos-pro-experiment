<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_installation_id')->nullable()->constrained('app_installations')->nullOnDelete();
            $table->foreignId('license_id')->nullable()->constrained('licenses')->nullOnDelete();
            $table->string('check_type');
            $table->string('result');
            $table->string('enforcement')->default('none');
            $table->timestamp('checked_at');
            $table->string('message')->nullable();
            $table->json('context')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['app_installation_id', 'checked_at']);
            $table->index(['license_id', 'checked_at']);
            $table->index('result');
            $table->index('check_type');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_checks');
    }
};
