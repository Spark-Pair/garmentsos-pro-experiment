<?php

namespace App\Services\Licensing;

use App\Models\License;
use App\Models\LicenseCheck;
use App\Services\AuditLogService;
use App\Services\Updater\InstalledVersionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

class LicenseService
{
    public function __construct(
        protected InstallationIdentityService $identity,
        protected InstallationFingerprintService $fingerprints,
        protected SignedLicenseFileService $signedFiles,
        protected LicenseActivationClient $activationClient,
        protected LicensePayloadValidator $payloadValidator,
        protected AuditLogService $auditLogs,
        protected InstalledVersionService $versions,
    ) {
    }

    public function enabled(): bool
    {
        return (bool) config('licensing.enabled', false);
    }

    public function currentStatus(): LicenseStatus
    {
        if (!$this->enabled()) {
            return LicenseStatus::notEnforced();
        }

        if ($this->usesConfiguredLicense()) {
            return $this->statusForConfiguredLicense();
        }

        $installation = $this->identity->current();
        $license = License::query()
            ->where('app_installation_id', $installation->id)
            ->latest('id')
            ->first();

        if (!$license) {
            return LicenseStatus::problem(
                'unactivated',
                'blocked',
                'No license has been activated.',
            );
        }

        return $this->statusForLicense($license);
    }

    public function usesConfiguredLicense(): bool
    {
        return trim((string) config('licensing.license_key', '')) !== ''
            || trim((string) config('licensing.client_id', '')) !== '';
    }

    public function installId(): string
    {
        return $this->identity->installId();
    }

    protected function statusForConfiguredLicense(): LicenseStatus
    {
        $licenseKey = trim((string) config('licensing.license_key', ''));
        $clientId = trim((string) config('licensing.client_id', ''));

        if ($licenseKey === '' || $clientId === '') {
            return LicenseStatus::problem(
                'unactivated',
                'blocked',
                'License client ID and key must be configured.',
                ['source' => 'env_config'],
            );
        }

        $result = $this->activationClient->verify([
            'product' => config('updater.app_id', 'garmentsos-pro'),
            'client_id' => $clientId,
            'license_key' => $licenseKey,
            'install_id' => $this->identity->installId(),
            'app_version' => $this->versions->currentVersion(),
        ]);

        if (($result['ok'] ?? false) && is_array($result['body'] ?? null)) {
            $body = $result['body'];
            if (($body['valid'] ?? false) === true) {
                $this->writeVerifyCache($body);
            }

            return $this->statusFromVerifyResponse($body, 'server_verify');
        }

        return $this->statusFromVerifyCache(
            $result['message'] ?? 'License server is not reachable.',
            $result['error'] ?? null,
        );
    }

    protected function statusFromVerifyResponse(array $body, string $source): LicenseStatus
    {
        $status = strtolower(trim((string) ($body['status'] ?? 'invalid')));
        $message = trim((string) ($body['message'] ?? 'License verification completed.'));
        $expiresAt = $this->parseDate($body['expires_at'] ?? null);
        $graceDays = (int) ($body['grace_days'] ?? config('licensing.offline_grace_days', 7));
        $graceUntil = $expiresAt ? $expiresAt->copy()->addDays(max(0, $graceDays)) : null;

        if (($body['valid'] ?? false) === true && $status === 'active') {
            return LicenseStatus::valid($source, [
                'message' => $message !== '' ? $message : 'License active',
                'expires_at' => $expiresAt,
                'grace_until' => $graceUntil,
            ]);
        }

        if (in_array($status, ['blocked', 'tampered', 'installation_mismatch', 'security_issue'], true)) {
            return LicenseStatus::problem(
                $status,
                'blocked',
                $message !== '' ? $message : 'License verification failed.',
                [
                    'expires_at' => $expiresAt,
                    'grace_until' => $graceUntil,
                    'source' => $source,
                ],
            );
        }

        return LicenseStatus::problem(
            $status === 'expired' ? 'expired_readonly' : 'invalid_readonly',
            $this->defaultEnforcementMode(),
            $message !== '' ? $message : 'License is not valid.',
            [
                'expires_at' => $expiresAt,
                'grace_until' => $graceUntil,
                'source' => $source,
            ],
        );
    }

    protected function statusFromVerifyCache(string $message, ?string $error = null): LicenseStatus
    {
        $cache = $this->readVerifyCache();
        if (!$cache) {
            return LicenseStatus::problem(
                'network_error',
                'none',
                $message,
                ['source' => 'server_unreachable'],
            );
        }

        $expiresAt = $this->parseDate($cache['expires_at'] ?? null);
        $graceDays = (int) ($cache['grace_days'] ?? config('licensing.offline_grace_days', 7));
        $graceUntil = $expiresAt ? $expiresAt->copy()->addDays(max(0, $graceDays)) : null;

        if (!$expiresAt || Carbon::now()->lessThanOrEqualTo($graceUntil ?? $expiresAt)) {
            return LicenseStatus::problem(
                'offline_grace',
                'none',
                'License server is unreachable. Using cached license status.',
                [
                    'expires_at' => $expiresAt,
                    'grace_until' => $graceUntil,
                    'source' => 'verify_cache',
                    'error' => $error,
                ],
            );
        }

        return LicenseStatus::problem(
            'expired_readonly',
            $this->defaultEnforcementMode(),
            'Cached license grace has expired. App is in read-only mode.',
            [
                'expires_at' => $expiresAt,
                'grace_until' => $graceUntil,
                'source' => 'verify_cache',
            ],
        );
    }

    protected function writeVerifyCache(array $body): void
    {
        $path = (string) config('licensing.verify_cache_path');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'valid' => (bool) ($body['valid'] ?? false),
            'status' => (string) ($body['status'] ?? ''),
            'client_name' => (string) ($body['client_name'] ?? ''),
            'expires_at' => (string) ($body['expires_at'] ?? ''),
            'grace_days' => (int) ($body['grace_days'] ?? config('licensing.offline_grace_days', 7)),
            'message' => (string) ($body['message'] ?? ''),
            'checked_at' => now()->utc()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function readVerifyCache(): ?array
    {
        $path = (string) config('licensing.verify_cache_path');
        if (!File::exists($path)) {
            return null;
        }

        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) && ($decoded['valid'] ?? false) === true ? $decoded : null;
    }

    protected function parseDate(mixed $date): ?Carbon
    {
        $date = trim((string) $date);
        if ($date === '') {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (\Throwable) {
            return null;
        }
    }

    public function statusForLicense(License $license): LicenseStatus
    {
        $payload = [
            'expires_at' => $license->subscription_expires_at,
            'grace_until' => $license->offline_grace_until,
            'modules' => $license->allowed_modules ?? [],
            'features' => $license->allowed_features ?? [],
            'brands' => $license->allowed_brand_ids ?? [],
            'update_channel' => $license->update_channel,
            'source' => 'database',
        ];

        if ($license->status === 'unactivated') {
            return LicenseStatus::problem('unactivated', 'blocked', 'No license has been activated.', $payload);
        }

        if (in_array($license->status, ['suspended', 'blocked'], true)) {
            return LicenseStatus::problem(
                $license->status === 'suspended' ? 'revoked' : 'blocked',
                'blocked',
                'License is ' . $license->status . '.',
                $payload,
            );
        }

        if ($license->license_expires_at && Carbon::now()->greaterThan($license->license_expires_at)) {
            return LicenseStatus::problem(
                'expired_readonly',
                $license->enforcement_mode ?: $this->defaultEnforcementMode(),
                'License has expired. App is in read-only mode.',
                $payload,
            );
        }

        $installation = $license->installation;

        if (!$installation || $installation->fingerprint_hash !== $this->fingerprints->fingerprintHash()) {
            return LicenseStatus::problem(
                'blocked',
                'blocked',
                'This app installation does not match the activated license.',
                $payload,
            );
        }

        if ($license->subscription_status === 'grace' && $license->offline_grace_until) {
            if (Carbon::now()->lessThanOrEqualTo($license->offline_grace_until)) {
                return LicenseStatus::problem(
                    'offline_grace',
                    'none',
                    'Offline grace is active. License server refresh is recommended.',
                    $payload,
                );
            }

            return LicenseStatus::problem(
                'expired_readonly',
                $license->enforcement_mode ?: $this->defaultEnforcementMode(),
                'Offline grace has expired.',
                $payload,
            );
        }

        if (
            $license->subscription_status === 'expired'
            || ($license->subscription_expires_at && Carbon::now()->greaterThan($license->subscription_expires_at))
        ) {
            return LicenseStatus::problem(
                'expired_readonly',
                $license->enforcement_mode ?: $this->defaultEnforcementMode(),
                'Subscription has expired. Read-only mode should be used when enforcement is enabled.',
                $payload,
            );
        }

        if ($license->subscription_expires_at) {
            $days = (int) config('licensing.expiring_soon_days', 14);
            $daysLeft = Carbon::now()->startOfDay()->diffInDays($license->subscription_expires_at->copy()->startOfDay(), false);
            if ($daysLeft >= 0 && $daysLeft <= $days) {
                return LicenseStatus::valid('database', array_merge($payload, [
                    'state' => 'expiring_soon',
                    'message' => 'License is active and expiring soon.',
                ]));
            }
        }

        return LicenseStatus::valid('database', $payload);
    }

    public function activate(string $licenseKey): LicenseStatus
    {
        $installation = $this->identity->current();
        $fingerprintHash = $this->fingerprints->fingerprintHash();

        $this->auditLogs->record('license.online_activation_attempted', [
            'installation_uuid' => $this->identity->maskedUuid($installation),
            'fingerprint_preview' => $this->fingerprints->fingerprintPreview(),
        ]);

        $result = $this->activationClient->activate($licenseKey, [
            'installation_uuid' => $installation->installation_uuid,
            'fingerprint_hash' => $fingerprintHash,
            'installation_mode' => $installation->installation_mode,
        ]);

        if (!($result['ok'] ?? false)) {
            $status = LicenseStatus::problem(
                'activation_failed',
                'none',
                $result['message'] ?? 'License activation failed.',
            );
            $this->recordCheck($installation, null, 'activation', $status);
            $this->auditLogs->record('license.online_activation_failed', [
                'reason' => $status->message,
            ]);

            return $status;
        }

        return $this->persistSignedDocument([
            'payload' => $result['payload'] ?? null,
            'signature' => $result['signature'] ?? null,
        ], 'activation', 'server');
    }

    public function importSignedLicense(string $signedLicense, string $checkType = 'offline_activation'): LicenseStatus
    {
        $this->auditLogs->record('license.offline_import_attempted');

        $decoded = $this->signedFiles->decodeDocument($signedLicense);
        if (!($decoded['valid'] ?? false)) {
            $status = LicenseStatus::problem('tampered', 'blocked', 'Signed license payload is invalid.');
            $this->recordCheck($this->identity->current(), null, $checkType, $status);
            $this->auditLogs->record('license.offline_import_failed', [
                'reason' => $decoded['reason'] ?? 'invalid',
            ]);

            return $status;
        }

        return $this->persistSignedDocument($decoded['document'], $checkType, 'signed_import');
    }

    public function refresh(): LicenseStatus
    {
        $installation = $this->identity->current();
        $license = License::query()
            ->where('app_installation_id', $installation->id)
            ->latest('id')
            ->first();

        $this->auditLogs->record('license.refresh_attempted');

        if (!$license) {
            $status = LicenseStatus::problem('unactivated', 'blocked', 'No license has been activated.');
            $this->recordCheck($installation, null, 'online', $status);
            $this->auditLogs->record('license.refresh_failed', ['reason' => 'unactivated']);

            return $status;
        }

        $result = $this->activationClient->refresh([
            'server_license_id' => $license->metadata['server_license_id'] ?? null,
            'license_key_hash' => $license->license_key_hash,
            'installation_uuid' => $installation->installation_uuid,
            'fingerprint_hash' => $this->fingerprints->fingerprintHash(),
            'signed_payload_hash' => $license->signed_payload_hash,
            'app' => 'garmentsos-pro',
            'app_version' => config('app.version', 'local'),
        ]);

        if (!($result['ok'] ?? false)) {
            $status = LicenseStatus::problem(
                'network_error',
                'none',
                $result['message'] ?? 'License refresh failed.',
            );
            $this->recordCheck($installation, $license, 'online', $status);
            $this->auditLogs->record('license.refresh_failed', [
                'reason' => $status->message,
            ]);

            return $status;
        }

        return $this->persistSignedDocument([
            'payload' => $result['payload'] ?? null,
            'signature' => $result['signature'] ?? null,
        ], 'online', 'server');
    }

    public function statusFromSignedCache(): LicenseStatus
    {
        $read = $this->signedFiles->read();

        if (($read['valid'] ?? false) !== true) {
            $reason = (string) ($read['reason'] ?? 'invalid');

            if ($reason === 'missing') {
                return LicenseStatus::problem(
                    'unactivated',
                    'blocked',
                    'No signed license cache is present.',
                    ['source' => 'signed_cache'],
                );
            }

            return LicenseStatus::problem(
                'tampered',
                'blocked',
                'Signed license cache is invalid or tampered.',
                ['source' => 'signed_cache'],
            );
        }

        $result = $this->payloadValidator->validatePayload($read['payload']);
        if (($result['valid'] ?? false) === true) {
            return LicenseStatus::valid('signed_cache', [
                'message' => 'Signed license cache is valid.',
            ]);
        }

        $reason = (string) ($result['reason'] ?? 'invalid');
        return LicenseStatus::problem(
            $this->statusStateForValidationFailure($reason),
            'blocked',
            'Signed license cache is invalid or tampered.',
            ['source' => 'signed_cache'],
        );
    }

    public function persistSignedDocument(array $document, string $checkType, string $source): LicenseStatus
    {
        $installation = $this->identity->current();
        $result = $this->payloadValidator->validateDocument($document);

        if (!($result['valid'] ?? false)) {
            $status = LicenseStatus::problem(
                $this->statusStateForValidationFailure((string) ($result['reason'] ?? 'invalid')),
                'blocked',
                'Signed license payload could not be verified.',
                ['source' => $source],
            );
            $this->recordCheck($installation, null, $checkType, $status);
            $this->auditLogs->record($this->auditEventForCheckType($checkType, false), [
                'reason' => $result['reason'] ?? 'invalid',
            ]);

            return $status;
        }

        $payload = $result['payload'];
        $installation->update([
            'installation_mode' => $payload['installation_mode'],
            'fingerprint_hash' => $payload['fingerprint_hash'],
            'last_seen_at' => now(),
            'metadata' => [
                'signature_version' => $payload['signature_version'],
            ],
        ]);

        $existingLicense = License::query()
            ->where('app_installation_id', $installation->id)
            ->latest('id')
            ->first();

        $license = License::updateOrCreate(
            ['app_installation_id' => $installation->id],
            [
                'license_key_hash' => $payload['license_key_hash'],
                'client_name' => $payload['client_name'],
                'business_name' => $payload['business_name'],
                'status' => $payload['license_status'],
                'subscription_status' => $payload['subscription_status'],
                'subscription_expires_at' => $payload['subscription_expires_at'],
                'license_expires_at' => $payload['license_expires_at'],
                'offline_grace_days' => (int) config('licensing.offline_grace_days', 7),
                'offline_grace_until' => $payload['offline_grace_until'] ?? $payload['cache_until'],
                'enforcement_mode' => 'readonly',
                'allowed_modules' => $payload['allowed_modules'] ?? [],
                'allowed_features' => $payload['allowed_features'] ?? [],
                'allowed_brand_ids' => $payload['allowed_brand_ids'] ?? [],
                'update_channel' => $payload['update_channel'],
                'last_verified_at' => now(),
                'last_online_check_at' => in_array($checkType, ['activation', 'online'], true)
                    ? now()
                    : $existingLicense?->last_online_check_at,
                'signed_payload_hash' => $result['payload_hash'],
                'metadata' => [
                    'server_license_id' => $payload['server_license_id'],
                    'signature_version' => $payload['signature_version'],
                    'payload_hash' => $payload['payload_hash'],
                ],
            ],
        );

        $this->signedFiles->write($payload, (string) $document['signature']);

        $status = $this->statusForLicense($license->fresh('installation'));
        $this->recordCheck($installation, $license, $checkType, $status);
        $this->auditLogs->record($this->auditEventForCheckType($checkType, true), [
            'server_license_id' => $payload['server_license_id'],
            'subscription_status' => $payload['subscription_status'],
            'license_status' => $payload['license_status'],
        ]);

        return $status;
    }

    public function recordCheck(mixed $installation, ?License $license, string $type, LicenseStatus $status): void
    {
        LicenseCheck::create([
            'app_installation_id' => $installation?->id,
            'license_id' => $license?->id,
            'check_type' => $type,
            'result' => $status->state,
            'enforcement' => $status->enforcement,
            'checked_at' => now(),
            'message' => $status->message,
            'context' => [
                'source' => $status->source,
            ],
            'user_id' => Auth::id(),
        ]);
    }

    protected function defaultEnforcementMode(): string
    {
        return (string) config('licensing.default_enforcement_mode', 'readonly');
    }

    protected function statusStateForValidationFailure(string $reason): string
    {
        return match ($reason) {
            'installation_uuid_mismatch', 'fingerprint_mismatch' => 'installation_mismatch',
            default => 'tampered',
        };
    }

    protected function auditEventForCheckType(string $checkType, bool $success): string
    {
        $suffix = $success ? 'succeeded' : 'failed';

        return match ($checkType) {
            'activation' => 'license.online_activation_' . $suffix,
            'online' => 'license.refresh_' . $suffix,
            default => 'license.offline_import_' . $suffix,
        };
    }
}
