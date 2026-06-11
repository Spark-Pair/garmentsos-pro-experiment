<?php

namespace Tests\Unit;

use App\Services\Backup\BackupCleanupService;
use DateTimeImmutable;
use Illuminate\Config\Repository;
use RuntimeException;
use Tests\TestCase;

class BackupCleanupServiceTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR
            .'garmentsos-cleanup-test-'.bin2hex(random_bytes(8));

        mkdir($this->temporaryDirectory, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_backup_config_defaults_match_current_paths(): void
    {
        $this->assertSame(storage_path('app/backups/tmp'), config('backup.temporary_backup_path'));
        $this->assertSame(storage_path('app/backups/manual'), config('backup.manual_backup_path'));
        $this->assertSame(storage_path('app/backups/auto'), config('backup.automatic_backup_path'));
        $this->assertSame(120, config('backup.temp_retention_minutes'));
    }

    public function test_missing_temporary_directory_returns_zero_summary(): void
    {
        $missing = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'missing';

        $this->assertSame($this->emptySummary(), $this->serviceFor($missing)->cleanTemporary());
    }

    public function test_old_matching_snapshot_is_deleted(): void
    {
        $path = $this->createFile('database_backup_20260611_120000_123456.sqlite');
        touch($path, $this->now()->getTimestamp() - 121 * 60);

        $summary = $this->serviceFor($this->temporaryDirectory)->cleanTemporary($this->now());

        $this->assertFileDoesNotExist($path);
        $this->assertSame([
            'scanned' => 1,
            'deleted' => 1,
            'kept_recent' => 0,
            'ignored' => 0,
            'skipped_links' => 0,
            'failed' => 0,
        ], $summary);
    }

    public function test_recent_matching_snapshot_is_kept(): void
    {
        $path = $this->createFile('database_backup_20260611_145000_123456.sqlite');
        touch($path, $this->now()->getTimestamp() - 60 * 60);

        $summary = $this->serviceFor($this->temporaryDirectory)->cleanTemporary($this->now());

        $this->assertFileExists($path);
        $this->assertSame(1, $summary['kept_recent']);
        $this->assertSame(0, $summary['deleted']);
    }

    public function test_non_matching_files_remain_untouched(): void
    {
        $files = [
            $this->createFile('.env'),
            $this->createFile('database.sqlite'),
            $this->createFile('database_backup_20260611_120000.sqlite'),
            $this->createFile('database_backup_20260611_120000_123456.sqlite.txt'),
        ];

        foreach ($files as $file) {
            touch($file, $this->now()->getTimestamp() - 300 * 60);
        }

        $summary = $this->serviceFor($this->temporaryDirectory)->cleanTemporary($this->now());

        foreach ($files as $file) {
            $this->assertFileExists($file);
        }

        $this->assertSame(4, $summary['scanned']);
        $this->assertSame(4, $summary['ignored']);
        $this->assertSame(0, $summary['deleted']);
    }

    public function test_nested_directories_and_contents_remain_untouched(): void
    {
        $nested = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'nested';
        mkdir($nested);
        $nestedBackup = $nested.DIRECTORY_SEPARATOR.'database_backup_20260611_120000_123456.sqlite';
        file_put_contents($nestedBackup, 'nested');
        touch($nestedBackup, $this->now()->getTimestamp() - 300 * 60);

        $summary = $this->serviceFor($this->temporaryDirectory)->cleanTemporary($this->now());

        $this->assertDirectoryExists($nested);
        $this->assertFileExists($nestedBackup);
        $this->assertSame(1, $summary['scanned']);
        $this->assertSame(1, $summary['ignored']);
    }

    public function test_file_symlink_is_skipped_when_supported(): void
    {
        $target = $this->createFile('target.sqlite');
        $link = $this->temporaryDirectory.DIRECTORY_SEPARATOR
            .'database_backup_20260611_120000_123456.sqlite';

        if (!@symlink($target, $link)) {
            $this->markTestSkipped('File symlinks are not available in this environment.');
        }

        $summary = $this->serviceFor($this->temporaryDirectory)->cleanTemporary($this->now());

        $this->assertFileExists($target);
        $this->assertTrue(is_link($link));
        $this->assertSame(1, $summary['skipped_links']);
        $this->assertSame(0, $summary['deleted']);
    }

    public function test_symlinked_configured_directory_is_refused_when_supported(): void
    {
        $target = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'target-directory';
        $link = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'linked-directory';
        mkdir($target);

        if (!@symlink($target, $link)) {
            $this->markTestSkipped('Directory symlinks are not available in this environment.');
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must not be a symbolic link');

        $this->serviceFor($link)->cleanTemporary($this->now());
    }

    public function test_invalid_retention_performs_no_scan_or_deletion(): void
    {
        $path = $this->createFile('database_backup_20260611_120000_123456.sqlite');
        touch($path, $this->now()->getTimestamp() - 300 * 60);

        $summary = $this->serviceFor($this->temporaryDirectory, 0)->cleanTemporary($this->now());

        $this->assertFileExists($path);
        $this->assertSame($this->emptySummary(), $summary);
    }

    public function test_failed_deletion_is_recorded_without_forcing_removal(): void
    {
        $path = $this->createFile('database_backup_20260611_120000_123456.sqlite');
        touch($path, $this->now()->getTimestamp() - 300 * 60);
        $config = $this->configFor($this->temporaryDirectory, 120);

        $service = new class($config) extends BackupCleanupService {
            protected function deleteFile(string $path): bool
            {
                return false;
            }
        };

        $summary = $service->cleanTemporary($this->now());

        $this->assertFileExists($path);
        $this->assertSame(1, $summary['failed']);
        $this->assertSame(0, $summary['deleted']);
    }

    private function serviceFor(string $path, mixed $retention = 120): BackupCleanupService
    {
        return new BackupCleanupService($this->configFor($path, $retention));
    }

    private function configFor(string $path, mixed $retention): Repository
    {
        return new Repository([
            'backup' => [
                'temporary_backup_path' => $path,
                'temp_retention_minutes' => $retention,
            ],
        ]);
    }

    private function createFile(string $name): string
    {
        $path = $this->temporaryDirectory.DIRECTORY_SEPARATOR.$name;
        file_put_contents($path, 'test');

        return $path;
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-06-11 15:30:00');
    }

    /**
     * @return array<string, int>
     */
    private function emptySummary(): array
    {
        return [
            'scanned' => 0,
            'deleted' => 0,
            'kept_recent' => 0,
            'ignored' => 0,
            'skipped_links' => 0,
            'failed' => 0,
        ];
    }

    private function removeDirectory(string $directory): void
    {
        if (is_link($directory)) {
            @unlink($directory);

            return;
        }

        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_link($path)) {
                @unlink($path);
            } elseif (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
