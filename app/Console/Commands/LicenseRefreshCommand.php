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

        $this->table(['key', 'value'], [
            ['install_id', $licenses->installId()],
            ['machine_hash_preview', $licenses->machineHashPreview()],
            ['state', $status->state],
            ['enforcement', $status->enforcement],
            ['allowed', $status->isAllowed() ? 'yes' : 'no'],
            ['message', $status->message ?: '-'],
        ]);

        return $status->shouldBlock() ? self::FAILURE : self::SUCCESS;
    }
}
