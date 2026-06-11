<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupCleanupService;
use Illuminate\Console\Command;
use Throwable;

class CleanTemporaryBackups extends Command
{
    protected $signature = 'backups:clean-temp';

    protected $description = 'Clean orphaned temporary database backup snapshots';

    public function handle(BackupCleanupService $cleanup): int
    {
        try {
            $summary = $cleanup->cleanTemporary();
        } catch (Throwable) {
            $this->error('Temporary backup cleanup failed. Check the application configuration and logs.');

            return self::FAILURE;
        }

        foreach ([
            'scanned',
            'deleted',
            'kept_recent',
            'ignored',
            'skipped_links',
            'failed',
        ] as $field) {
            $this->line($field.': '.($summary[$field] ?? 0));
        }

        if (($summary['failed'] ?? 0) > 0) {
            $this->warn('Cleanup completed, but some temporary backup files could not be deleted.');
        } else {
            $this->info('Temporary backup cleanup completed.');
        }

        return self::SUCCESS;
    }
}
