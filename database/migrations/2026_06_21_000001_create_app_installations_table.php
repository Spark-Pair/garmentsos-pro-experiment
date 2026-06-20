<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_installations', function (Blueprint $table) {
            $table->id();
            $table->uuid('installation_uuid')->unique();
            $table->string('installation_mode')->default('local_lan');
            $table->string('display_name')->nullable();
            $table->string('fingerprint_hash')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('installation_mode');
            $table->index('fingerprint_hash');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_installations');
    }
};
