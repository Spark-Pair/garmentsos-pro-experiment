<?php

namespace App\Services\Licensing;

use App\Models\AppInstallation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;

class InstallationIdentityService
{
    public function current(): AppInstallation
    {
        $uuid = $this->uuid();
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

    public function uuid(): string
    {
        return $this->readOrCreateUuid();
    }

    public function installId(): string
    {
        $path = (string) config('licensing.install_id_path', storage_path('app/install-id.txt'));

        $existing = $this->existingInstallId();
        if ($existing !== null) {
            return $existing;
        }

        $installId = (string) Str::uuid();
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $installId . PHP_EOL);

        return $installId;
    }

    public function existingInstallId(): ?string
    {
        $path = (string) config('licensing.install_id_path', storage_path('app/install-id.txt'));

        if (!File::exists($path)) {
            return $this->recoverInstallIdFromLicenseCaches($path);
        }

        try {
            $installId = trim((string) File::get($path));

            if ($installId !== '') {
                return $installId;
            }
        } catch (\Throwable $e) {
            Log::warning('License install ID file could not be read.', [
                'path' => $path,
                'error' => $e->getMessage(),
                'type' => $e::class,
            ]);

            return $this->recoverInstallIdFromLicenseCaches($path);
        }

        return $this->recoverInstallIdFromLicenseCaches($path);
    }

    public function hasPersistedIdentity(): bool
    {
        if ($this->existingInstallId() !== null) {
            return true;
        }

        $identityPath = (string) config('licensing.identity_path');

        return $this->readUuidFromPath($identityPath) !== null
            || $this->readUuidFromPath($identityPath . '.recovery') !== null;
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
            $uuid = $this->readUuidFromPath($path);
            if ($uuid !== null) {
                return $uuid;
            }

            $recoveryPath = $path . '.recovery';
            $recoveryUuid = $this->readUuidFromPath($recoveryPath);
            if ($recoveryUuid !== null) {
                return $recoveryUuid;
            }

            return $this->writeUuid($recoveryPath);
        }

        return $this->writeUuid($path);
    }

    protected function readUuidFromPath(string $path): ?string
    {
        if (!File::exists($path)) {
            return null;
        }

        try {
            $document = json_decode((string) File::get($path), true, 512, JSON_THROW_ON_ERROR);
            $uuid = (string) ($document['installation_uuid'] ?? '');

            return Str::isUuid($uuid) ? $uuid : null;
        } catch (JsonException) {
            return null;
        } catch (\Throwable $e) {
            Log::warning('License installation identity file could not be read.', [
                'path' => $path,
                'error' => $e->getMessage(),
                'type' => $e::class,
            ]);

            return null;
        }
    }

    protected function writeUuid(string $path): string
    {
        $uuid = (string) Str::uuid();
        $installId = $this->existingInstallId() ?? (string) Str::uuid();
        $installIdPath = (string) config('licensing.install_id_path', storage_path('app/install-id.txt'));
        if ($this->existingInstallId() === null) {
            File::ensureDirectoryExists(dirname($installIdPath));
            File::put($installIdPath, $installId . PHP_EOL);
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'installation_uuid' => $uuid,
            'install_id_hash' => substr(hash('sha256', $installId), 0, 12),
            'fingerprint_source' => 'stable_install_identity',
            'fingerprint_version' => 2,
            'created_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $uuid;
    }

    protected function recoverInstallIdFromLicenseCaches(string $installIdPath): ?string
    {
        $cachePaths = [
            (string) config('licensing.request_cache_path'),
            (string) config('licensing.registration_cache_path'),
            (string) config('licensing.verify_cache_path'),
        ];

        foreach ($cachePaths as $cachePath) {
            if ($cachePath === '' || !File::exists($cachePath)) {
                continue;
            }

            try {
                $cache = json_decode((string) File::get($cachePath), true, 512, JSON_THROW_ON_ERROR);
                $installId = trim((string) ($cache['install_id'] ?? ''));
                if ($installId === '') {
                    continue;
                }

                File::ensureDirectoryExists(dirname($installIdPath));
                File::put($installIdPath, $installId . PHP_EOL);
                Log::warning('Recovered missing license install ID from preserved license cache.', [
                    'cache' => basename($cachePath),
                    'install_id_hash' => substr(hash('sha256', $installId), 0, 12),
                ]);

                return $installId;
            } catch (\Throwable $e) {
                Log::warning('License install ID recovery cache could not be read.', [
                    'cache' => $cachePath,
                    'error' => $e->getMessage(),
                    'type' => $e::class,
                ]);
            }
        }

        return null;
    }
}
