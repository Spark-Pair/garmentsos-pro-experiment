<?php

namespace Tests\Feature;

use App\Services\Backup\BackupCleanupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\TestCase;

class CleanTemporaryBackupsCommandTest extends TestCase
{
    public function test_command_calls_cleanup_service_and_prints_summary(): void
    {
        $cleanup = $this->mock(BackupCleanupService::class);
        $cleanup->shouldReceive('cleanTemporary')
            ->once()
            ->andReturn([
                'scanned' => 8,
                'deleted' => 2,
                'kept_recent' => 3,
                'ignored' => 1,
                'skipped_links' => 2,
                'failed' => 0,
            ]);

        $this->artisan('backups:clean-temp')
            ->expectsOutput('scanned: 8')
            ->expectsOutput('deleted: 2')
            ->expectsOutput('kept_recent: 3')
            ->expectsOutput('ignored: 1')
            ->expectsOutput('skipped_links: 2')
            ->expectsOutput('failed: 0')
            ->expectsOutput('Temporary backup cleanup completed.')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_command_returns_success_with_warning_when_deletions_fail(): void
    {
        $cleanup = $this->mock(BackupCleanupService::class);
        $cleanup->shouldReceive('cleanTemporary')
            ->once()
            ->andReturn([
                'scanned' => 2,
                'deleted' => 1,
                'kept_recent' => 0,
                'ignored' => 0,
                'skipped_links' => 0,
                'failed' => 1,
            ]);

        $this->artisan('backups:clean-temp')
            ->expectsOutput('failed: 1')
            ->expectsOutput('Cleanup completed, but some temporary backup files could not be deleted.')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_missing_directory_zero_summary_returns_success(): void
    {
        $cleanup = $this->mock(BackupCleanupService::class);
        $cleanup->shouldReceive('cleanTemporary')
            ->once()
            ->andReturn([
                'scanned' => 0,
                'deleted' => 0,
                'kept_recent' => 0,
                'ignored' => 0,
                'skipped_links' => 0,
                'failed' => 0,
            ]);

        $this->artisan('backups:clean-temp')
            ->expectsOutput('scanned: 0')
            ->expectsOutput('deleted: 0')
            ->expectsOutput('failed: 0')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_service_exception_returns_failure_without_exposing_details(): void
    {
        $cleanup = $this->mock(BackupCleanupService::class);
        $cleanup->shouldReceive('cleanTemporary')
            ->once()
            ->andThrow(new RuntimeException(
                'Unsafe path C:\\private\\client\\database.sqlite with secret details.'
            ));

        $this->artisan('backups:clean-temp')
            ->expectsOutput('Temporary backup cleanup failed. Check the application configuration and logs.')
            ->doesntExpectOutput('Unsafe path C:\\private\\client\\database.sqlite with secret details.')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_command_is_registered_in_artisan(): void
    {
        $this->assertArrayHasKey('backups:clean-temp', Artisan::all());
    }
}
