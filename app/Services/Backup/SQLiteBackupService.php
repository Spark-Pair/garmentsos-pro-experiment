<?php

namespace App\Services\Backup;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Config\Repository;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class SQLiteBackupService
{
    private const SQLITE_HEADER = "SQLite format 3\0";

    public function __construct(private readonly Repository $config)
    {
    }

    public function resolveDatabasePath(): string
    {
        if ($this->config->get('database.default') !== 'sqlite') {
            throw new RuntimeException('The configured database connection is not supported for SQLite backup.');
        }

        if ($this->config->get('database.connections.sqlite.driver') !== 'sqlite') {
            throw new RuntimeException('The configured SQLite connection is invalid.');
        }

        $configuredPath = $this->config->get('database.connections.sqlite.database');

        if (!is_string($configuredPath) || trim($configuredPath) === '') {
            throw new RuntimeException('The configured SQLite database path is empty.');
        }

        $configuredPath = trim($configuredPath);

        if ($configuredPath === ':memory:') {
            throw new RuntimeException('In-memory SQLite databases cannot be backed up to a file.');
        }

        if (!$this->isAbsolutePath($configuredPath)) {
            throw new RuntimeException('The configured SQLite database path must be absolute.');
        }

        $resolvedPath = realpath($configuredPath);

        if ($resolvedPath === false || !is_file($resolvedPath) || !is_readable($resolvedPath)) {
            throw new RuntimeException('The configured SQLite database file is missing or unreadable.');
        }

        if (!$this->hasSQLiteHeader($resolvedPath)) {
            throw new RuntimeException('The configured database file is not a valid SQLite database.');
        }

        try {
            $this->openDatabase($resolvedPath)->query('PRAGMA schema_version')->fetchColumn();
        } catch (Throwable $exception) {
            throw new RuntimeException('The configured database file could not be opened as SQLite.', 0, $exception);
        }

        return $resolvedPath;
    }

    public function createBackup(
        string $destinationDirectory,
        ?DateTimeInterface $timestamp = null
    ): string {
        $sourcePath = $this->resolveDatabasePath();
        $destinationDirectory = $this->prepareDestinationDirectory($destinationDirectory);
        $timestamp ??= new DateTimeImmutable();

        $destinationPath = $destinationDirectory.DIRECTORY_SEPARATOR
            .'database_backup_'.$timestamp->format('Ymd_His_u').'.sqlite';

        if (file_exists($destinationPath)) {
            throw new RuntimeException('The backup destination file already exists.');
        }

        try {
            $source = $this->openDatabase($sourcePath);
            $quotedDestination = $source->quote($destinationPath);

            if ($quotedDestination === false) {
                throw new RuntimeException('The backup destination could not be prepared.');
            }

            $source->exec('VACUUM INTO '.$quotedDestination);
            $source = null;

            $this->verifyBackup($destinationPath);

            return $destinationPath;
        } catch (Throwable $exception) {
            if (is_file($destinationPath)) {
                @unlink($destinationPath);
            }

            if ($exception instanceof RuntimeException && !$exception instanceof PDOException) {
                throw $exception;
            }

            throw new RuntimeException('SQLite backup creation failed.', 0, $exception);
        }
    }

    protected function verifyBackup(string $backupPath): void
    {
        if (!is_file($backupPath) || filesize($backupPath) === false || filesize($backupPath) <= 0) {
            throw new RuntimeException('The SQLite backup file is empty or missing.');
        }

        if (!$this->hasSQLiteHeader($backupPath)) {
            throw new RuntimeException('The SQLite backup file has an invalid header.');
        }

        try {
            $backup = $this->openDatabase($backupPath);
            $result = $backup->query('PRAGMA integrity_check')->fetchColumn();
            $backup = null;
        } catch (Throwable $exception) {
            throw new RuntimeException('The SQLite backup could not be verified.', 0, $exception);
        }

        if ($result !== 'ok') {
            throw new RuntimeException('The SQLite backup failed its integrity check.');
        }
    }

    private function prepareDestinationDirectory(string $directory): string
    {
        $directory = trim($directory);

        if ($directory === '') {
            throw new RuntimeException('The backup destination directory is empty.');
        }

        if (file_exists($directory) && !is_dir($directory)) {
            throw new RuntimeException('The backup destination is not a directory.');
        }

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('The backup destination directory could not be created.');
        }

        $resolvedDirectory = realpath($directory);

        if ($resolvedDirectory === false || !is_writable($resolvedDirectory)) {
            throw new RuntimeException('The backup destination directory is not writable.');
        }

        return $resolvedDirectory;
    }

    private function openDatabase(string $path): PDO
    {
        return new PDO('sqlite:'.$path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private function hasSQLiteHeader(string $path): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        try {
            return fread($handle, strlen(self::SQLITE_HEADER)) === self::SQLITE_HEADER;
        } finally {
            fclose($handle);
        }
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
