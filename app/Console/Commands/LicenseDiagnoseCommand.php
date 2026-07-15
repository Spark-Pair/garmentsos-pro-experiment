<?php

namespace App\Console\Commands;

use App\Services\Licensing\LicenseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class LicenseDiagnoseCommand extends Command
{
    protected $signature = 'license:diagnose';

    protected $description = 'Show safe GarmentsOS license identity diagnostics.';

    public function handle(LicenseService $licenses): int
    {
        $diagnostics = $licenses->diagnostics();
        $status = $licenses->currentStatus();

        $rows = [
            ['install_id', $licenses->installId()],
            ['installation_file_path', (string) config('licensing.identity_path')],
            ['installation_file_exists', File::exists((string) config('licensing.identity_path')) ? 'yes' : 'no'],
            ['machine_name', $licenses->machineName()],
            ['machine_hash_preview', $licenses->machineHashPreview()],
            ['previous_machine_hash_preview', $diagnostics['previous_machine_hash_preview'] ?? '-'],
            ['fingerprint_source', $diagnostics['fingerprint_source'] ?? '-'],
            ['verify_cache_exists', ($diagnostics['verify_cache_exists'] ?? false) ? 'yes' : 'no'],
            ['registration_cache_exists', ($diagnostics['registration_cache_exists'] ?? false) ? 'yes' : 'no'],
            ['request_cache_exists', ($diagnostics['request_cache_exists'] ?? false) ? 'yes' : 'no'],
            ['current_license_state', $status->state],
            ['current_enforcement', $status->enforcement],
            ['last_verified_at', $diagnostics['last_verified_at'] ?? '-'],
            ['app_version', $diagnostics['app_version'] ?? '-'],
        ];

        $this->table(['key', 'value'], $rows);

        if ($status->message) {
            $this->line('message: ' . $status->message);
        }

        return self::SUCCESS;
    }
}
