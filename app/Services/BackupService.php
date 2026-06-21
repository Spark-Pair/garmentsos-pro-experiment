<?php

namespace App\Services;

use App\Models\BackupLog;
use App\Services\Licensing\InstallationIdentityService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class BackupService
{
    public function __construct(
        protected BackupStorageService $storage,
        protected BackupVerifier $verifier,
        protected AuditLogService $auditLogs,
    ) {
    }

    public function createManualBackup(string $action = 'manual_backup'): array
    {
        $lock = Cache::lock('garmentsos:backup:create', (int) config('backup.lock_seconds', 300));

        $lockAcquired = $lock->get();

        if (!$lockAcquired) {
            return [
                'success' => false,
                'message' => 'Another backup is already running. Please try again shortly.',
            ];
        }

        $log = null;
        $tempPath = null;

        try {
            $driver = config('database.default');
            if (!in_array($driver, config('backup.allowed_drivers', ['sqlite']), true)) {
                return [
                    'success' => false,
                    'message' => 'Backup currently supports SQLite databases only.',
                ];
            }

            $this->storage->ensureDirectoryExists();
            $filename = $this->storage->generateFilename();
            $tempPath = $this->storage->temporaryFilePath($filename);
            $finalPath = $this->storage->finalFilePath($filename);

            $log = $this->createLog($action, 'pending', [
                'disk' => config('backup.disk', 'local'),
                'filename' => $filename,
                'path' => $this->storage->relativePath($finalPath),
                'context' => [
                    'driver' => $driver,
                    'storage' => 'private',
                ],
            ]);

            $this->auditLogs->record('backup.create_started', [
                'backup_log_id' => $log->id,
                'filename' => $filename,
                'driver' => $driver,
            ], [
                'module' => 'backup',
                'record_type' => BackupLog::class,
                'record_id' => $log->id,
            ]);

            $this->createSqliteBackup($tempPath);

            $checksum = hash_file((string) config('backup.checksum_algorithm', 'sha256'), $tempPath);
            File::move($tempPath, $finalPath);

            $metadata = $this->metadataFor($finalPath, $filename, $checksum, $action);
            if (config('backup.metadata_enabled', true)) {
                File::put(
                    $this->storage->metadataPathFor($finalPath),
                    json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                );
            }

            $verification = $this->verifier->verify($finalPath, $checksum);
            if (!$verification['valid']) {
                throw new \RuntimeException('Backup verification failed: ' . $verification['message']);
            }

            $log->update([
                'status' => 'success',
                'size_bytes' => File::size($finalPath),
                'checksum' => $checksum,
                'completed_at' => now(),
                'message' => 'Backup created and verified.',
                'context' => array_merge($log->context ?? [], [
                    'verification' => $verification['code'],
                    'metadata_enabled' => (bool) config('backup.metadata_enabled', true),
                ]),
            ]);

            $this->auditLogs->record('backup.create_succeeded', [
                'backup_log_id' => $log->id,
                'filename' => $filename,
                'size_bytes' => File::size($finalPath),
                'checksum' => $checksum,
            ], [
                'module' => 'backup',
                'record_type' => BackupLog::class,
                'record_id' => $log->id,
            ]);

            return [
                'success' => true,
                'message' => 'Backup created and verified successfully.',
                'backup_log' => $log->fresh(),
                'path' => $finalPath,
                'filename' => $filename,
                'checksum' => $checksum,
            ];
        } catch (Throwable $e) {
            if ($tempPath && File::exists($tempPath)) {
                File::delete($tempPath);
            }

            if ($log) {
                $log->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'message' => 'Backup failed safely.',
                    'context' => array_merge($log->context ?? [], [
                        'error' => Str::limit($e->getMessage(), 180),
                    ]),
                ]);

                $this->auditLogs->record('backup.create_failed', [
                    'backup_log_id' => $log->id,
                    'filename' => $log->filename,
                    'reason' => Str::limit($e->getMessage(), 180),
                ], [
                    'module' => 'backup',
                    'record_type' => BackupLog::class,
                    'record_id' => $log->id,
                ]);
            }

            return [
                'success' => false,
                'message' => 'Backup failed safely. No database files were overwritten.',
            ];
        } finally {
            if ($lockAcquired) {
                $lock->release();
            }
        }
    }

    public function createLog(string $action, string $status = 'pending', array $attributes = []): BackupLog
    {
        $installation = app(InstallationIdentityService::class)->current();

        return BackupLog::create([
            'app_installation_id' => $installation->id,
            'user_id' => Auth::id(),
            'action' => $action,
            'status' => $status,
            'disk' => $attributes['disk'] ?? null,
            'path' => $this->privatePathOnly($attributes['path'] ?? null),
            'filename' => $attributes['filename'] ?? null,
            'size_bytes' => $attributes['size_bytes'] ?? null,
            'checksum' => $attributes['checksum'] ?? null,
            'started_at' => $attributes['started_at'] ?? now(),
            'completed_at' => $attributes['completed_at'] ?? null,
            'message' => $attributes['message'] ?? null,
            'context' => $attributes['context'] ?? null,
        ]);
    }

    public function privatePathOnly(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $normalized = str_replace('\\', '/', $path);

        if (str_contains($normalized, '/public/') || str_starts_with($normalized, 'public/')) {
            return '[public-path-redacted]';
        }

        return $path;
    }

    protected function createSqliteBackup(string $targetPath): void
    {
        $this->storage->assertInsideBasePath($targetPath);

        if (File::exists($targetPath)) {
            throw new \RuntimeException('Refusing to overwrite an existing backup temp file.');
        }

        $pdo = DB::connection('sqlite')->getPdo();

        // VACUUM INTO asks SQLite to create a consistent standalone database file,
        // avoiding unsafe manual copying of database, WAL, and SHM files.
        DB::connection('sqlite')->statement('VACUUM INTO ' . $pdo->quote($targetPath));

        if (!File::exists($targetPath)) {
            throw new \RuntimeException('SQLite did not create the backup file.');
        }
    }

    protected function metadataFor(string $path, string $filename, string $checksum, string $action): array
    {
        return [
            'app' => 'garmentsos-pro',
            'type' => 'sqlite_backup',
            'action' => $action,
            'filename' => $filename,
            'size_bytes' => File::size($path),
            'checksum_algorithm' => config('backup.checksum_algorithm', 'sha256'),
            'checksum' => $checksum,
            'created_at' => now()->toIso8601String(),
            'created_by_user_id' => Auth::id(),
            'driver' => config('database.default'),
            'storage' => 'private',
            'restore_implemented' => false,
        ];
    }
}
