<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppInstallation extends Model
{
    use HasFactory;

    protected $fillable = [
        'installation_uuid',
        'installation_mode',
        'display_name',
        'fingerprint_hash',
        'status',
        'first_seen_at',
        'last_seen_at',
        'metadata',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    public function licenseChecks(): HasMany
    {
        return $this->hasMany(LicenseCheck::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function backupLogs(): HasMany
    {
        return $this->hasMany(BackupLog::class);
    }
}
