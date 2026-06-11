<?php

namespace Tests\Feature;

use App\Services\Runtime\RuntimePathReadinessService;
use Illuminate\Console\Command;
use Tests\TestCase;

class CheckRuntimePathsCommandTest extends TestCase
{
    public function test_pass_only_result_prints_checks_and_exits_successfully(): void
    {
        $service = $this->mock(RuntimePathReadinessService::class);
        $service->shouldReceive('check')
            ->once()
            ->andReturn([
                'overall_status' => RuntimePathReadinessService::PASS,
                'checks' => [
                    [
                        'level' => RuntimePathReadinessService::PASS,
                        'key' => 'database',
                        'message' => 'SQLite database path is ready.',
                        'path' => 'C:\\Runtime\\data\\database.sqlite',
                    ],
                ],
            ]);

        $this->artisan('runtime:check-paths')
            ->expectsOutput('Overall status: PASS')
            ->expectsOutput('[PASS] database: SQLite database path is ready. | C:\\Runtime\\data\\database.sqlite')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_warn_only_result_exits_successfully(): void
    {
        $service = $this->mock(RuntimePathReadinessService::class);
        $service->shouldReceive('check')
            ->once()
            ->andReturn([
                'overall_status' => RuntimePathReadinessService::WARN,
                'checks' => [
                    [
                        'level' => RuntimePathReadinessService::WARN,
                        'key' => 'public_storage',
                        'message' => 'public/storage exists but is not a symbolic link.',
                    ],
                ],
            ]);

        $this->artisan('runtime:check-paths')
            ->expectsOutput('Overall status: WARN')
            ->expectsOutput('[WARN] public_storage: public/storage exists but is not a symbolic link.')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_fail_result_exits_with_failure(): void
    {
        $service = $this->mock(RuntimePathReadinessService::class);
        $service->shouldReceive('check')
            ->once()
            ->andReturn([
                'overall_status' => RuntimePathReadinessService::FAIL,
                'checks' => [
                    [
                        'level' => RuntimePathReadinessService::PASS,
                        'key' => 'base_path',
                        'message' => 'Configured directory is ready.',
                    ],
                    [
                        'level' => RuntimePathReadinessService::FAIL,
                        'key' => 'uploads_path',
                        'message' => 'Configured directory does not exist.',
                        'path' => 'C:\\Runtime\\data\\uploads',
                    ],
                ],
            ]);

        $this->artisan('runtime:check-paths')
            ->expectsOutput('Overall status: FAIL')
            ->expectsOutput('[PASS] base_path: Configured directory is ready.')
            ->expectsOutput('[FAIL] uploads_path: Configured directory does not exist. | C:\\Runtime\\data\\uploads')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_fail_check_returns_failure_even_if_overall_status_is_inconsistent(): void
    {
        $service = $this->mock(RuntimePathReadinessService::class);
        $service->shouldReceive('check')
            ->once()
            ->andReturn([
                'overall_status' => RuntimePathReadinessService::PASS,
                'checks' => [
                    [
                        'level' => RuntimePathReadinessService::FAIL,
                        'key' => 'runtime_path',
                        'message' => 'Configured directory is not writable.',
                    ],
                ],
            ]);

        $this->artisan('runtime:check-paths')
            ->expectsOutput('[FAIL] runtime_path: Configured directory is not writable.')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_command_is_registered_in_artisan(): void
    {
        $this->assertArrayHasKey('runtime:check-paths', \Artisan::all());
    }
}
