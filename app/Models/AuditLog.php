<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_installation_id',
        'user_id',
        'user_name_snapshot',
        'event_type',
        'module',
        'record_type',
        'record_id',
        'ip_address',
        'user_agent',
        'occurred_at',
        'context',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'context' => 'array',
    ];

    public function installation(): BelongsTo
    {
        return $this->belongsTo(AppInstallation::class, 'app_installation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
