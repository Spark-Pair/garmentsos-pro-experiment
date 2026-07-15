<?php

namespace App\Console\Commands;

use App\Services\Licensing\LicenseService;
use Illuminate\Console\Command;

class LicenseRefreshCommand extends Command
{
    protected $signature = 'license:refresh';

    protected $description = 'Refresh GarmentsOS license approval using the current install identity.';

    public function handle(LicenseService $licenses): int
    {
        $status = $licenses->verifyNow();
        $diagnostics = $licenses->diagnostics();
        $lastResponse = $licenses->lastResponseCache() ?? [];

        $this->table(['key', 'value'], [
            ['install_id', $licenses->installId()],
            ['verify_url', (string) ($lastResponse['verify_url'] ?? config('licensing.server_url', ''))],
            ['http_status', (string) ($lastResponse['http_status'] ?? '-')],
            ['json_parsed', ($lastResponse['json_parsed'] ?? false) ? 'yes' : 'no'],
            ['machine_hash_preview', $licenses->machineHashPreview()],
            ['previous_machine_hash_preview', $diagnostics['previous_machine_hash_preview'] ?? '-'],
            ['fingerprint_source', $diagnostics['fingerprint_source'] ?? '-'],
            ['response_status', (string) ($lastResponse['status'] ?? '-')],
            ['device_approval', (string) ($lastResponse['device_approval'] ?? '-')],
            ['rebind_performed', ($lastResponse['rebind_performed'] ?? false) ? 'yes' : 'no'],
            ['state', $status->state],
            ['enforcement', $status->enforcement],
            ['allowed', $status->isAllowed() ? 'yes' : 'no'],
            ['message', $status->message ?: '-'],
            ['server_message', (string) ($lastResponse['message'] ?? '-')],
        ]);

        return $status->shouldBlock() ? self::FAILURE : self::SUCCESS;
    }
}
