<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class License extends Model
{
    use HasFactory;

    protected $fillable = [
        'license_key_hash',
        'app_installation_id',
        'client_name',
        'business_name',
        'status',
        'subscription_status',
        'subscription_expires_at',
        'license_expires_at',
        'offline_grace_days',
        'offline_grace_until',
        'enforcement_mode',
        'allowed_modules',
        'allowed_features',
        'allowed_brand_ids',
        'update_channel',
        'last_verified_at',
        'last_online_check_at',
        'signed_payload_hash',
        'metadata',
    ];

    protected $casts = [
        'subscription_expires_at' => 'datetime',
        'license_expires_at' => 'datetime',
        'offline_grace_days' => 'integer',
        'offline_grace_until' => 'datetime',
        'allowed_modules' => 'array',
        'allowed_features' => 'array',
        'allowed_brand_ids' => 'array',
        'last_verified_at' => 'datetime',
        'last_online_check_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function installation(): BelongsTo
    {
        return $this->belongsTo(AppInstallation::class, 'app_installation_id');
    }

    public function checks(): HasMany
    {
        return $this->hasMany(LicenseCheck::class);
    }
}
