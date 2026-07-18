<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

class AppLaravelMaintenanceService
{
    public function run(): array
    {
        return [
            'storage_link' => $this->call('storage:link', ['--force' => true], retryWithoutOptions: true),
            'optimize_clear' => $this->call('optimize:clear'),
            'optimize' => $this->call('optimize'),
        ];
    }

    private function call(string $command, array $parameters = [], bool $retryWithoutOptions = false): array
    {
        try {
            $exitCode = Artisan::call($command, $parameters);

            return [
                'success' => $exitCode === 0,
                'exit_code' => $exitCode,
                'output' => trim(Artisan::output()),
            ];
        } catch (Throwable $exception) {
            if ($retryWithoutOptions && $parameters !== []) {
                try {
                    $exitCode = Artisan::call($command);

                    return [
                        'success' => $exitCode === 0,
                        'exit_code' => $exitCode,
                        'output' => trim(Artisan::output()),
                        'retried_without_options' => true,
                    ];
                } catch (Throwable $retryException) {
                    Log::warning('Laravel maintenance command failed after retry.', [
                        'command' => $command,
                        'error' => $retryException->getMessage(),
                    ]);

                    return [
                        'success' => false,
                        'exit_code' => 1,
                        'output' => $retryException->getMessage(),
                        'retried_without_options' => true,
                    ];
                }
            }

            Log::warning('Laravel maintenance command failed.', [
                'command' => $command,
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'exit_code' => 1,
                'output' => $exception->getMessage(),
            ];
        }
    }
}
