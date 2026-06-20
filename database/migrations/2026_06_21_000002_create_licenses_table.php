<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_installation_id')->nullable()->constrained('app_installations')->nullOnDelete();
            $table->string('license_key_hash')->nullable()->unique();
            $table->string('client_name')->nullable();
            $table->string('business_name')->nullable();
            $table->string('status')->default('unactivated');
            $table->string('subscription_status')->default('inactive');
            $table->timestamp('subscription_expires_at')->nullable();
            $table->timestamp('license_expires_at')->nullable();
            $table->unsignedInteger('offline_grace_days')->default(7);
            $table->timestamp('offline_grace_until')->nullable();
            $table->string('enforcement_mode')->default('readonly');
            $table->json('allowed_modules')->nullable();
            $table->json('allowed_features')->nullable();
            $table->json('allowed_brand_ids')->nullable();
            $table->string('update_channel')->default('stable');
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('last_online_check_at')->nullable();
            $table->string('signed_payload_hash')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('app_installation_id');
            $table->index('status');
            $table->index('subscription_status');
            $table->index('subscription_expires_at');
            $table->index('license_expires_at');
            $table->index('last_verified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
