<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Services\Licensing\InstallationIdentityService;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    protected array $secretKeys = [
        'password',
        'token',
        'secret',
        'key',
        'license_key',
        'private_key',
        'authorization',
        'env',
        'app_key',
        'signed_license',
        'signed_payload',
        'signature',
        'payload',
    ];

    public function sanitizeContext(array $context): array
    {
        foreach ($context as $key => $value) {
            if ($this->isSecretKey((string) $key)) {
                $context[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $context[$key] = $this->sanitizeContext($value);
            }
        }

        return $context;
    }

    public function record(string $eventType, array $context = [], array $attributes = []): AuditLog
    {
        $installation = app(InstallationIdentityService::class)->current();
        $user = Auth::user();

        return AuditLog::create([
            'app_installation_id' => $installation->id,
            'user_id' => $user?->id,
            'user_name_snapshot' => $user?->name,
            'event_type' => $eventType,
            'module' => $attributes['module'] ?? null,
            'record_type' => $attributes['record_type'] ?? null,
            'record_id' => $attributes['record_id'] ?? null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'occurred_at' => now(),
            'context' => $this->sanitizeContext($context),
        ]);
    }

    protected function isSecretKey(string $key): bool
    {
        $key = strtolower($key);

        foreach ($this->secretKeys as $secretKey) {
            if (str_contains($key, $secretKey)) {
                return true;
            }
        }

        return false;
    }
}
