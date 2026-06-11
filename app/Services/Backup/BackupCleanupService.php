<?php

namespace App\Services\Backup;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Config\Repository;
use RuntimeException;

class BackupCleanupService
{
    private const TEMP_BACKUP_PATTERN = '/\Adatabase_backup_\d{8}_\d{6}_\d{6}\.sqlite\z/';

    public function __construct(private readonly Repository $config)
    {
    }

    /**
     * @return array{
     *     scanned: int,
     *     deleted: int,
     *     kept_recent: int,
     *     ignored: int,
     *     skipped_links: int,
     *     failed: int
     * }
     */
    public function cleanTemporary(?DateTimeInterface $now = null): array
    {
        $summary = $this->emptySummary();
        $configuredPath = $this->config->get('backup.temporary_backup_path');

        if (!is_string($configuredPath) || trim($configuredPath) === '') {
            throw new RuntimeException('The temporary backup path is not configured.');
        }

        $configuredPath = trim($configuredPath);

        if (!$this->isAbsolutePath($configuredPath)) {
            throw new RuntimeException('The temporary backup path must be absolute.');
        }

        if (!file_exists($configuredPath) && !is_link($configuredPath)) {
            return $summary;
        }

        if (is_link($configuredPath)) {
            throw new RuntimeException('The temporary backup path must not be a symbolic link.');
        }

        if (!is_dir($configuredPath)) {
            throw new RuntimeException('The temporary backup path is not a directory.');
        }

        $retentionMinutes = $this->config->get('backup.temp_retention_minutes');

        if (!is_int($retentionMinutes) || $retentionMinutes <= 0) {
            return $summary;
        }

        $entries = scandir($configuredPath);

        if ($entries === false) {
            throw new RuntimeException('The temporary backup directory could not be inspected.');
        }

        $now ??= new DateTimeImmutable();
        $cutoff = $now->getTimestamp() - ($retentionMinutes * 60);

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $summary['scanned']++;
            $path = $configuredPath.DIRECTORY_SEPARATOR.$entry;

            if (is_link($path)) {
                $summary['skipped_links']++;
                continue;
            }

            if (is_dir($path) || !is_file($path)) {
                $summary['ignored']++;
                continue;
            }

            if (preg_match(self::TEMP_BACKUP_PATTERN, $entry) !== 1) {
                $summary['ignored']++;
                continue;
            }

            $modifiedAt = filemtime($path);

            if ($modifiedAt === false) {
                $summary['failed']++;
                continue;
            }

            if ($modifiedAt >= $cutoff) {
                $summary['kept_recent']++;
                continue;
            }

            if ($this->deleteFile($path)) {
                $summary['deleted']++;
            } else {
                $summary['failed']++;
            }
        }

        return $summary;
    }

    protected function deleteFile(string $path): bool
    {
        return @unlink($path);
    }

    /**
     * @return array{
     *     scanned: int,
     *     deleted: int,
     *     kept_recent: int,
     *     ignored: int,
     *     skipped_links: int,
     *     failed: int
     * }
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

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1
            || str_starts_with($path, '\\\\');
    }
}
