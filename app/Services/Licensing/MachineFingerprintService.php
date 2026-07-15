<?php

namespace App\Services\Licensing;

class MachineFingerprintService
{
    public function __construct(protected InstallationIdentityService $identity)
    {
    }

    public function machineName(): string
    {
        $installId = $this->identity->existingInstallId() ?? $this->identity->installId();

        return 'GarmentsOS Install ' . substr(hash('sha256', $installId), 0, 8);
    }

    public function machineHash(): string
    {
        $signals = [
            'install_id' => $this->identity->installId(),
            'installation_uuid' => $this->identity->uuid(),
            'installation_mode' => $this->identity->installationMode(),
        ];

        ksort($signals);

        return hash('sha256', 'garmentsos-pro-device-v2|' . json_encode($signals, JSON_UNESCAPED_SLASHES));
    }

    public function shortHash(): string
    {
        return substr($this->machineHash(), 0, 12);
    }

    public function source(): string
    {
        return 'stable_install_identity';
    }
}
