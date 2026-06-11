<?php

namespace Tests\Unit;

use App\Services\Backup\SQLiteBackupService;
use DateTimeImmutable;
use Illuminate\Config\Repository;
use PDO;
use RuntimeException;
use Tests\TestCase;

class SQLiteBackupServiceTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR
            .'garmentsos-backup-test-'.bin2hex(random_bytes(8));

        mkdir($this->temporaryDirectory, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_resolves_configured_live_database_path(): void
    {
        $sourcePath = $this->createSQLiteDatabase('live.sqlite');

        $this->assertSame(realpath($sourcePath), $this->serviceFor($sourcePath)->resolveDatabasePath());
    }

    public function test_rejects_missing_database_path(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing or unreadable');

        $this->serviceFor($this->temporaryDirectory.DIRECTORY_SEPARATOR.'missing.sqlite')
            ->resolveDatabasePath();
    }

    public function test_rejects_in_memory_database(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('In-memory');

        $this->serviceFor(':memory:')->resolveDatabasePath();
    }

    public function test_rejects_non_sqlite_file(): void
    {
        $path = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'not-sqlite.txt';
        file_put_contents($path, 'not a sqlite database');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not a valid SQLite database');

        $this->serviceFor($path)->resolveDatabasePath();
    }

    public function test_uses_only_configured_database_and_ignores_stale_copy(): void
    {
        $livePath = $this->createSQLiteDatabase('live.sqlite', 'live');
        $staleDirectory = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app';
        mkdir($staleDirectory, 0775, true);
        $stalePath = $this->createSQLiteDatabase(
            'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'database.sqlite',
            'stale'
        );

        $backupPath = $this->serviceFor($livePath)->createBackup(
            $this->temporaryDirectory.DIRECTORY_SEPARATOR.'backups'
        );

        $this->assertSame('live', $this->readValue($backupPath));
        $this->assertSame('stale', $this->readValue($stalePath));
    }

    public function test_creates_backup_directory_and_timestamped_backup(): void
    {
        $sourcePath = $this->createSQLiteDatabase('live.sqlite');
        $backupDirectory = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'nested'.DIRECTORY_SEPARATOR.'backups';
        $timestamp = new DateTimeImmutable('2026-06-11 15:30:45.123456');

        $backupPath = $this->serviceFor($sourcePath)->createBackup($backupDirectory, $timestamp);

        $this->assertDirectoryExists($backupDirectory);
        $this->assertFileExists($backupPath);
        $this->assertSame(
            'database_backup_20260611_153045_123456.sqlite',
            basename($backupPath)
        );
    }

    public function test_backup_captures_committed_wal_data(): void
    {
        $sourcePath = $this->createSQLiteDatabase('live.sqlite', 'first');
        $writer = $this->openDatabase($sourcePath);
        $writer->exec('PRAGMA journal_mode = WAL');
        $writer->exec("INSERT INTO records (value) VALUES ('wal-value')");

        $this->assertFileExists($sourcePath.'-wal');

        $backupPath = $this->serviceFor($sourcePath)->createBackup(
            $this->temporaryDirectory.DIRECTORY_SEPARATOR.'backups'
        );

        $values = $this->openDatabase($backupPath)
            ->query('SELECT value FROM records ORDER BY id')
            ->fetchAll(PDO::FETCH_COLUMN);

        $this->assertSame(['first', 'wal-value'], $values);
        $this->assertFileDoesNotExist($backupPath.'-wal');
        $this->assertFileDoesNotExist($backupPath.'-shm');

        $writer = null;
    }

    public function test_backup_has_valid_header_and_passes_integrity_check(): void
    {
        $sourcePath = $this->createSQLiteDatabase('live.sqlite');
        $backupPath = $this->serviceFor($sourcePath)->createBackup(
            $this->temporaryDirectory.DIRECTORY_SEPARATOR.'backups'
        );

        $handle = fopen($backupPath, 'rb');
        $header = fread($handle, 16);
        fclose($handle);

        $this->assertSame("SQLite format 3\0", $header);
        $this->assertSame(
            'ok',
            $this->openDatabase($backupPath)->query('PRAGMA integrity_check')->fetchColumn()
        );
    }

    public function test_existing_destination_file_is_rejected(): void
    {
        $sourcePath = $this->createSQLiteDatabase('live.sqlite');
        $backupDirectory = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'backups';
        mkdir($backupDirectory, 0775, true);
        $timestamp = new DateTimeImmutable('2026-06-11 15:30:45.123456');
        $destination = $backupDirectory.DIRECTORY_SEPARATOR
            .'database_backup_20260611_153045_123456.sqlite';
        file_put_contents($destination, 'keep me');

        try {
            $this->serviceFor($sourcePath)->createBackup($backupDirectory, $timestamp);
            $this->fail('Expected the existing destination to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('already exists', $exception->getMessage());
            $this->assertSame('keep me', file_get_contents($destination));
        }
    }

    public function test_incomplete_backup_is_cleaned_when_verification_fails(): void
    {
        $sourcePath = $this->createSQLiteDatabase('live.sqlite');
        $backupDirectory = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'backups';
        $timestamp = new DateTimeImmutable('2026-06-11 15:30:45.123456');
        $destination = $backupDirectory.DIRECTORY_SEPARATOR
            .'database_backup_20260611_153045_123456.sqlite';
        $config = $this->configFor($sourcePath);

        $service = new class($config) extends SQLiteBackupService {
            protected function verifyBackup(string $backupPath): void
            {
                throw new RuntimeException('Simulated verification failure.');
            }
        };

        try {
            $service->createBackup($backupDirectory, $timestamp);
            $this->fail('Expected verification to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated verification failure.', $exception->getMessage());
            $this->assertFileDoesNotExist($destination);
        }
    }

    public function test_source_database_remains_unchanged(): void
    {
        $sourcePath = $this->createSQLiteDatabase('live.sqlite', 'original');
        $sourceHash = hash_file('sha256', $sourcePath);
        $sourceSize = filesize($sourcePath);

        $this->serviceFor($sourcePath)->createBackup(
            $this->temporaryDirectory.DIRECTORY_SEPARATOR.'backups'
        );

        $this->assertSame($sourceHash, hash_file('sha256', $sourcePath));
        $this->assertSame($sourceSize, filesize($sourcePath));
        $this->assertSame('original', $this->readValue($sourcePath));
    }

    private function serviceFor(string $databasePath): SQLiteBackupService
    {
        return new SQLiteBackupService($this->configFor($databasePath));
    }

    private function configFor(string $databasePath): Repository
    {
        return new Repository([
            'database' => [
                'default' => 'sqlite',
                'connections' => [
                    'sqlite' => [
                        'driver' => 'sqlite',
                        'database' => $databasePath,
                    ],
                ],
            ],
        ]);
    }

    private function createSQLiteDatabase(string $relativePath, string $value = 'source'): string
    {
        $path = $this->temporaryDirectory.DIRECTORY_SEPARATOR.$relativePath;
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $database = $this->openDatabase($path);
        $database->exec('CREATE TABLE records (id INTEGER PRIMARY KEY, value TEXT NOT NULL)');
        $statement = $database->prepare('INSERT INTO records (value) VALUES (:value)');
        $statement->execute(['value' => $value]);
        $database = null;

        return $path;
    }

    private function readValue(string $databasePath): string
    {
        return (string) $this->openDatabase($databasePath)
            ->query('SELECT value FROM records ORDER BY id LIMIT 1')
            ->fetchColumn();
    }

    private function openDatabase(string $path): PDO
    {
        return new PDO('sqlite:'.$path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
