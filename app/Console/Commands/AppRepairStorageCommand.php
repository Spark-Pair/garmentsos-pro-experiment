<?php

namespace App\Console\Commands;

use App\Services\AppStorageRepairService;
use App\Services\AppLaravelMaintenanceService;
use Illuminate\Console\Command;

class AppRepairStorageCommand extends Command
{
    protected $signature = 'app:repair-storage {--no-clear-cache : Do not clear storage/framework/cache/data}';

    protected $description = 'Repair GarmentsOS writable storage/cache directories.';

    public function handle(AppStorageRepairService $repair, AppLaravelMaintenanceService $maintenance): int
    {
        $results = $repair->repair(clearCacheData: !$this->option('no-clear-cache'));

        $this->table(['path', 'exists', 'writable', 'error'], array_map(
            fn (array $row): array => [
                $row['path'] ?? '-',
                ($row['exists'] ?? false) ? 'yes' : 'no',
                ($row['writable'] ?? false) ? 'yes' : 'no',
                $row['error'] ?? '',
            ],
            $results
        ));

        try {
            $repair->guardForBackupRestore();
            $this->info('Storage repair completed. Required restore paths are writable.');
            $maintenanceResults = $maintenance->run();
            $this->table(['command', 'success', 'exit_code'], collect($maintenanceResults)->map(
                fn (array $row, string $command): array => [
                    $command,
                    ($row['success'] ?? false) ? 'yes' : 'no',
                    $row['exit_code'] ?? '-',
                ]
            )->values()->all());

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
