<?php

namespace App\Services\Licensing;

class InstallationFingerprintService
{
    public function __construct(
        protected InstallationIdentityService $identity,
    ) {
    }

    public function fingerprintHash(): string
    {
        $components = [
            'installation_uuid' => $this->identity->uuid(),
            'install_id' => $this->identity->installId(),
            'mode' => $this->identity->installationMode(),
        ];

        if ($this->identity->installationMode() === 'cloud') {
            $components['app_url_host'] = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'unknown';
        }

        ksort($components);

        return hash('sha256', 'garmentsos-pro-installation-v1|' . json_encode($components, JSON_UNESCAPED_SLASHES));
    }

    public function fingerprintPreview(): string
    {
        $hash = $this->fingerprintHash();

        return substr($hash, 0, 8) . '...' . substr($hash, -8);
    }

    public function sanitizedContext(): array
    {
        return [
            'mode' => $this->identity->installationMode(),
            'fingerprint_preview' => $this->fingerprintPreview(),
            'source' => $this->fingerprintSource(),
        ];
    }

    public function fingerprintSource(): string
    {
        return $this->identity->installationMode() === 'cloud'
            ? 'stable_install_identity+app_url_host'
            : 'stable_install_identity';
    }
}
