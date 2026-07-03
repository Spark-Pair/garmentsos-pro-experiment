<?php

namespace App\Services\Licensing;

class MachineFingerprintService
{
    public function __construct(protected InstallationIdentityService $identity)
    {
    }

    public function machineName(): string
    {
        $hostname = gethostname();

        return is_string($hostname) && trim($hostname) !== ''
            ? trim($hostname)
            : 'unknown';
    }

    public function machineHash(): string
    {
        $signals = [
            'install_id' => $this->identity->installId(),
            'machine_name' => $this->machineName(),
            'os_family' => PHP_OS_FAMILY,
            'sapi' => PHP_SAPI,
        ];

        return hash('sha256', json_encode($signals, JSON_UNESCAPED_SLASHES));
    }

    public function shortHash(): string
    {
        return substr($this->machineHash(), 0, 12);
    }
}
