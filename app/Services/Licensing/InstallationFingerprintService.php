<?php

namespace App\Services\Licensing;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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
            'mode' => $this->identity->installationMode(),
        ];

        if ($this->identity->installationMode() === 'cloud') {
            $components['app_url_host'] = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'unknown';
        } else {
            $components = array_merge($components, $this->localLanComponents());
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
        ];
    }

    protected function localLanComponents(): array
    {
        $components = [];

        foreach (['/etc/machine-id', '/var/lib/dbus/machine-id'] as $path) {
            if (File::exists($path) && File::isReadable($path)) {
                $value = trim((string) File::get($path));
                if ($value !== '') {
                    $components['server_machine_marker_hash'] = hash('sha256', $value);
                    break;
                }
            }
        }

        $hostname = gethostname();
        if (is_string($hostname) && trim($hostname) !== '') {
            $components['hostname_hash'] = hash('sha256', Str::lower(trim($hostname)));
        }

        $components['server_os_hash'] = hash('sha256', Str::lower(trim(php_uname('s') . '|' . php_uname('m'))));

        return $components;
    }
}
