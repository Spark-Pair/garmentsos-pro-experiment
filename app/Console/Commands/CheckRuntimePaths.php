<?php

namespace App\Console\Commands;

use App\Services\Runtime\RuntimePathReadinessService;
use Illuminate\Console\Command;
use Throwable;

class CheckRuntimePaths extends Command
{
    protected $signature = 'runtime:check-paths';

    protected $description = 'Report runtime path readiness without modifying files';

    public function handle(RuntimePathReadinessService $readiness): int
    {
        try {
            $result = $readiness->check();
        } catch (Throwable) {
            $this->error('Runtime path readiness check failed.');

            return self::FAILURE;
        }

        $overallStatus = (string) ($result['overall_status'] ?? RuntimePathReadinessService::FAIL);
        $this->line('Overall status: '.$overallStatus);

        $hasFailures = false;

        foreach ($result['checks'] ?? [] as $check) {
            $level = (string) ($check['level'] ?? RuntimePathReadinessService::FAIL);
            $key = (string) ($check['key'] ?? 'unknown');
            $message = (string) ($check['message'] ?? 'No check message was provided.');
            $path = isset($check['path']) && is_string($check['path']) && $check['path'] !== ''
                ? ' | '.$check['path']
                : '';

            $this->line("[{$level}] {$key}: {$message}{$path}");

            if ($level === RuntimePathReadinessService::FAIL) {
                $hasFailures = true;
            }
        }

        return $hasFailures || $overallStatus === RuntimePathReadinessService::FAIL
            ? self::FAILURE
            : self::SUCCESS;
    }
}
