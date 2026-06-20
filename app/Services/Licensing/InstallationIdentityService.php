<?php

namespace App\Services\Licensing;

use App\Models\AppInstallation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JsonException;

class InstallationIdentityService
{
    public function current(): AppInstallation
    {
        $uuid = $this->readOrCreateUuid();
        $mode = $this->installationMode();

        return AppInstallation::firstOrCreate(
            ['installation_uuid' => $uuid],
            [
                'installation_mode' => $mode,
                'status' => 'active',
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'metadata' => [
                    'mode' => $mode,
                ],
            ],
        );
    }

    public function installationMode(): string
    {
        $mode = (string) config('licensing.installation_mode', 'local_lan');

        return in_array($mode, ['local_lan', 'cloud'], true) ? $mode : 'local_lan';
    }

    public function maskedUuid(?AppInstallation $installation = null): string
    {
        $uuid = $installation?->installation_uuid ?: $this->readOrCreateUuid();

        return substr($uuid, 0, 8) . '-...' . substr($uuid, -8);
    }

    protected function readOrCreateUuid(): string
    {
        $path = (string) config('licensing.identity_path');

        if (File::exists($path)) {
            try {
                $document = json_decode((string) File::get($path), true, 512, JSON_THROW_ON_ERROR);
                $uuid = (string) ($document['installation_uuid'] ?? '');
                if (Str::isUuid($uuid)) {
                    return $uuid;
                }
            } catch (JsonException) {
                // Invalid local identity file falls through to a new identity.
            }
        }

        $uuid = (string) Str::uuid();
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'installation_uuid' => $uuid,
            'created_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $uuid;
    }
}
