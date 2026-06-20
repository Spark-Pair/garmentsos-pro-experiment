<?php

namespace App\Services;

use App\Models\BackupLog;
use App\Services\Licensing\InstallationIdentityService;
use Illuminate\Support\Facades\Auth;

class BackupService
{
    public function createLog(string $action, string $status = 'pending', array $attributes = []): BackupLog
    {
        $installation = app(InstallationIdentityService::class)->current();

        return BackupLog::create([
            'app_installation_id' => $installation->id,
            'user_id' => Auth::id(),
            'action' => $action,
            'status' => $status,
            'disk' => $attributes['disk'] ?? null,
            'path' => $this->privatePathOnly($attributes['path'] ?? null),
            'filename' => $attributes['filename'] ?? null,
            'size_bytes' => $attributes['size_bytes'] ?? null,
            'checksum' => $attributes['checksum'] ?? null,
            'started_at' => $attributes['started_at'] ?? now(),
            'completed_at' => $attributes['completed_at'] ?? null,
            'message' => $attributes['message'] ?? null,
            'context' => $attributes['context'] ?? null,
        ]);
    }

    public function privatePathOnly(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $normalized = str_replace('\\', '/', $path);

        if (str_contains($normalized, '/public/') || str_starts_with($normalized, 'public/')) {
            return '[public-path-redacted]';
        }

        return $path;
    }
}
