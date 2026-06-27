<?php

namespace App\Services\Licensing;

class OfflineActivationService
{
    public function __construct(
        protected InstallationIdentityService $identity,
        protected InstallationFingerprintService $fingerprint,
    ) {
    }

    public function requestCode(): string
    {
        $payload = [
            'app' => 'garmentsos-pro',
            'installation_uuid' => $this->identity->uuid(),
            'installation_mode' => $this->identity->installationMode(),
            'fingerprint_hash' => $this->fingerprint->fingerprintHash(),
            'generated_at' => now()->toIso8601String(),
            'nonce' => bin2hex(random_bytes(16)),
        ];

        return base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    public function reactivationRequestCode(string $reason, ?array $currentLicense = null): string
    {
        $payload = [
            'app' => 'garmentsos-pro',
            'type' => 'reactivation_request',
            'installation_uuid' => $this->identity->uuid(),
            'installation_mode' => $this->identity->installationMode(),
            'fingerprint_hash' => $this->fingerprint->fingerprintHash(),
            'old_license_reference' => $currentLicense['server_license_id'] ?? null,
            'reason' => $reason,
            'generated_at' => now()->toIso8601String(),
            'nonce' => bin2hex(random_bytes(16)),
        ];

        return base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    }
}
