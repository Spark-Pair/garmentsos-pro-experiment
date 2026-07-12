<?php

namespace App\Services;

use App\Models\BackupLog;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class BackupStorageService
{
    public function basePath(): string
    {
        return storage_path('app/' . trim((string) config('backup.path', 'private/backups/database'), '/'));
    }

    public function ensureDirectoryExists(): void
    {
        File::ensureDirectoryExists($this->basePath());
        File::ensureDirectoryExists($this->tempPath());
    }

    public function tempPath(): string
    {
        return $this->basePath() . DIRECTORY_SEPARATOR . '.tmp';
    }

    public function generateFilename(): string
    {
        $prefix = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) config('backup.filename_prefix', 'garmentsos-backup'));
        $timestamp = now()->format('Y-m-d-H-i-s');

        return sprintf('%s-%s-%s.sqlite', trim($prefix, '-'), $timestamp, Str::lower(Str::random(6)));
    }

    public function temporaryFilePath(string $filename): string
    {
        $this->assertSafeFilename($filename);

        return $this->tempPath() . DIRECTORY_SEPARATOR . $filename . '.tmp';
    }

    public function restoreStagingFilePath(string $filename): string
    {
        $this->assertSafeFilename($filename);

        return $this->tempPath() . DIRECTORY_SEPARATOR . 'restore_' . now()->format('Ymd_His') . '_' . Str::lower(Str::random(8)) . '_' . $filename;
    }

    public function restoreRollbackFilePath(string $filename): string
    {
        $this->assertSafeFilename($filename);

        return $this->tempPath() . DIRECTORY_SEPARATOR . 'rollback_' . now()->format('Ymd_His') . '_' . Str::lower(Str::random(8)) . '_' . $filename;
    }

    public function finalFilePath(string $filename): string
    {
        $this->assertSafeFilename($filename);

        return $this->basePath() . DIRECTORY_SEPARATOR . $filename;
    }

    public function metadataPathFor(string $backupPath): string
    {
        $this->assertInsideBasePath($backupPath);

        return $backupPath . '.json';
    }

    public function relativePath(string $path): string
    {
        $this->assertInsideBasePath($path);

        return ltrim(Str::after($this->normalizePath($path), $this->normalizePath(storage_path('app'))), '/');
    }

    public function resolveManagedPath(BackupLog $backupLog): string
    {
        if (!$backupLog->path || !$backupLog->filename) {
            throw new RuntimeException('Backup log does not reference a managed backup file.');
        }

        $path = storage_path('app/' . ltrim(str_replace('\\', '/', $backupLog->path), '/'));

        $this->assertInsideBasePath($path);
        $this->assertSafeFilename($backupLog->filename);

        if (basename($path) !== $backupLog->filename) {
            throw new RuntimeException('Backup log path does not match its filename.');
        }

        return $path;
    }

    public function listManagedBackups()
    {
        $this->ensureDirectoryExists();

        return BackupLog::query()
            ->whereIn('action', ['manual_backup', 'legacy_download_backup', 'emergency_restore_backup'])
            ->where('status', 'success')
            ->whereNotNull('path')
            ->whereNotNull('filename')
            ->latest('started_at')
            ->limit((int) config('backup.max_list_items', 100))
            ->get()
            ->filter(function (BackupLog $backupLog) {
                try {
                    return File::exists($this->resolveManagedPath($backupLog));
                } catch (RuntimeException) {
                    return false;
                }
            })
            ->values();
    }

    public function assertInsideBasePath(string $path): void
    {
        $base = $this->normalizePath($this->basePath());
        $target = $this->normalizePath($path);

        if ($target !== $base && !str_starts_with($target, $base . '/')) {
            throw new RuntimeException('Backup path is outside the managed private backup directory.');
        }
    }

    public function assertSafeFilename(string $filename): void
    {
        if ($filename !== basename($filename) || !preg_match('/^[A-Za-z0-9_.-]+\.sqlite$/', $filename)) {
            throw new RuntimeException('Invalid backup filename.');
        }
    }

    protected function normalizePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $real = realpath($normalized);

        return $real ? str_replace('\\', '/', $real) : rtrim($normalized, '/');
    }
}
