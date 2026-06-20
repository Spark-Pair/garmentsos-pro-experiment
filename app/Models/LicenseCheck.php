<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'license_id',
        'app_installation_id',
        'check_type',
        'result',
        'enforcement',
        'checked_at',
        'message',
        'context',
        'user_id',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
        'context' => 'array',
    ];

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(AppInstallation::class, 'app_installation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
