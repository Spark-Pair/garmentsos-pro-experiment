<?php

namespace App\Services;

use App\Models\BackupLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RestoreService
{
    public function __construct(
        protected BackupService $backups,
        protected BackupStorageService $storage,
        protected BackupVerifier $verifier,
        protected AuditLogService $auditLogs,
    ) {
    }

    public function enabled(): bool
    {
        return (bool) config('backup.restore.enabled', false);
    }

    public function requirements(): array
    {
        return [
            'restore_enabled' => $this->enabled(),
            'confirmation_required' => true,
            'confirmation_prefix' => config('backup.restore.confirmation_prefix', 'RESTORE BACKUP'),
            'staging_tested_required' => (bool) config('backup.restore.require_staging_tested', true),
            'emergency_backup_before_restore' => true,
            'public_backups_allowed' => false,
        ];
    }

    public function confirmationPhrase(BackupLog $backupLog): string
    {
        return trim((string) config('backup.restore.confirmation_prefix', 'RESTORE BACKUP')) . ' ' . $backupLog->id;
    }

    public function inspect(BackupLog $backupLog): array
    {
        try {
            $path = $this->storage->resolveManagedPath($backupLog);
            $verification = $this->verifier->verify($path, $backupLog->checksum);

            return [
                'valid' => $verification['valid'],
                'verification' => $verification,
                'message' => $verification['message'],
            ];
        } catch (RuntimeException $e) {
            return [
                'valid' => false,
                'verification' => [
                    'valid' => false,
                    'code' => 'unsafe_path',
                    'message' => $e->getMessage(),
                    'checksum' => null,
                ],
                'message' => $e->getMessage(),
            ];
        }
    }

    public function restore(BackupLog $backupLog, array $input): array
    {
        if (!$this->enabled()) {
            return [
                'success' => false,
                'code' => 'blocked_disabled',
                'message' => 'Restore is disabled by configuration.',
                'restore_log' => null,
            ];
        }

        $restoreLog = $this->backups->createLog('restore', 'pending', [
            'filename' => $backupLog->filename,
            'context' => [
                'selected_backup_log_id' => $backupLog->id,
                'restore_enabled' => $this->enabled(),
            ],
        ]);

        $expectedPhrase = $this->confirmationPhrase($backupLog);
        if (($input['confirmation_phrase'] ?? '') !== $expectedPhrase) {
            return $this->fail($restoreLog, 'restore.confirmation_failed', 'Restore confirmation phrase did not match.', [
                'selected_backup_log_id' => $backupLog->id,
            ]);
        }

        if ((bool) config('backup.restore.require_staging_tested', true) && empty($input['staging_tested'])) {
            return $this->fail($restoreLog, 'restore.staging_confirmation_failed', 'Confirm that this restore was tested on a staging/copy database first.', [
                'selected_backup_log_id' => $backupLog->id,
            ]);
        }

        try {
            $selectedPath = $this->storage->resolveManagedPath($backupLog);
        } catch (RuntimeException $e) {
            return $this->fail($restoreLog, 'restore.path_rejected', 'Restore rejected the selected backup path.', [
                'selected_backup_log_id' => $backupLog->id,
                'reason' => $e->getMessage(),
            ]);
        }

        $selectedVerification = $this->verifier->verify($selectedPath, $backupLog->checksum);
        if (!$selectedVerification['valid']) {
            return $this->fail($restoreLog, 'restore.backup_verification_failed', 'Selected backup verification failed.', [
                'selected_backup_log_id' => $backupLog->id,
                'verification' => $selectedVerification['code'],
            ]);
        }

        $lock = Cache::lock((string) config('backup.lock_key', 'garmentsos:backup-restore'), (int) config('backup.lock_seconds', 300));
        if (!$lock->get()) {
            return $this->fail($restoreLog, 'restore.lock_blocked', 'Another backup or restore is already running.', [
                'selected_backup_log_id' => $backupLog->id,
            ]);
        }

        $dbPath = null;
        $rollbackPath = null;
        $replaced = false;
        $emergency = null;

        try {
            $this->auditLogs->record('restore.started', [
                'restore_log_id' => $restoreLog->id,
                'selected_backup_log_id' => $backupLog->id,
                'selected_backup_filename' => $backupLog->filename,
            ], [
                'module' => 'backup',
                'record_type' => BackupLog::class,
                'record_id' => $restoreLog->id,
            ]);

            $emergency = $this->backups->createManualBackup('emergency_restore_backup', false);
            if (!$emergency['success']) {
                throw new RuntimeException('Emergency backup failed. Restore was not started.');
            }

            $emergencyLog = $emergency['backup_log'];
            $emergencyVerification = $this->verifier->verify($emergency['path'], $emergencyLog->checksum);
            if (!$emergencyVerification['valid']) {
                throw new RuntimeException('Emergency backup verification failed. Restore was not started.');
            }

            [$dbPath, $rollbackPath] = $this->replaceSqliteDatabase($selectedPath, $backupLog);
            $replaced = true;

            $validation = $this->validateCurrentDatabase();
            if (!$validation['valid']) {
                throw new RuntimeException('Restored database validation failed: ' . $validation['message']);
            }

            $postEmergencyLog = $this->backups->createLog('emergency_restore_backup', 'success', [
                'disk' => config('backup.disk', 'local'),
                'path' => $emergencyLog->path,
                'filename' => $emergencyLog->filename,
                'size_bytes' => $emergencyLog->size_bytes,
                'checksum' => $emergencyLog->checksum,
                'started_at' => $emergencyLog->started_at,
                'completed_at' => $emergencyLog->completed_at,
                'message' => 'Emergency backup created before restore.',
                'context' => [
                    'restored_database_log_copy' => true,
                ],
            ]);

            $postRestoreLog = $this->backups->createLog('restore', 'success', [
                'filename' => $backupLog->filename,
                'completed_at' => now(),
                'message' => 'Restore completed successfully.',
                'context' => [
                    'selected_backup_log_id' => $backupLog->id,
                    'emergency_backup_log_id' => $postEmergencyLog->id,
                    'validation' => $validation['code'],
                ],
            ]);

            $this->auditLogs->record('restore.succeeded', [
                'restore_log_id' => $postRestoreLog->id,
                'selected_backup_log_id' => $backupLog->id,
                'emergency_backup_log_id' => $postEmergencyLog->id,
                'validation' => $validation['code'],
            ], [
                'module' => 'backup',
                'record_type' => BackupLog::class,
                'record_id' => $postRestoreLog->id,
            ]);

            if ($rollbackPath && File::exists($rollbackPath)) {
                File::delete($rollbackPath);
            }

            return [
                'success' => true,
                'code' => 'restored',
                'message' => 'Restore completed successfully.',
                'restore_log' => $postRestoreLog,
                'emergency_backup_log' => $postEmergencyLog,
            ];
        } catch (Throwable $e) {
            $rollback = null;
            if ($replaced && $dbPath && $rollbackPath) {
                $rollback = $this->rollbackSqliteDatabase($dbPath, $rollbackPath);
            }

            $context = [
                'selected_backup_log_id' => $backupLog->id,
                'reason' => Str::limit($e->getMessage(), 180),
                'rollback' => $rollback['code'] ?? null,
            ];

            if (isset($emergency['backup_log'])) {
                $context['emergency_backup_log_id'] = $emergency['backup_log']->id;
            }

            return $this->fail($restoreLog, 'restore.failed', 'Restore failed safely. ' . ($rollback['message'] ?? 'Original database was preserved where possible.'), $context);
        } finally {
            $lock->release();
        }
    }

    protected function replaceSqliteDatabase(string $selectedPath, BackupLog $backupLog): array
    {
        $dbPath = $this->sqliteDatabasePath();
        $this->storage->ensureDirectoryExists();

        $this->checkpointSqlite();
        DB::disconnect('sqlite');
        DB::purge('sqlite');

        $this->removeSqliteSidecars($dbPath);

        $stagingPath = $this->storage->restoreStagingFilePath($backupLog->filename);
        $rollbackPath = $this->storage->restoreRollbackFilePath(basename($dbPath));

        File::copy($selectedPath, $stagingPath);

        if (!File::exists($stagingPath)) {
            throw new RuntimeException('Restore staging copy failed.');
        }

        File::move($dbPath, $rollbackPath);

        try {
            File::move($stagingPath, $dbPath);
        } catch (Throwable $e) {
            if (File::exists($rollbackPath) && !File::exists($dbPath)) {
                File::move($rollbackPath, $dbPath);
            }

            throw $e;
        }

        return [$dbPath, $rollbackPath];
    }

    protected function rollbackSqliteDatabase(string $dbPath, string $rollbackPath): array
    {
        $this->auditLogs->record('restore.rollback_attempted', [], [
            'module' => 'backup',
        ]);

        try {
            DB::disconnect('sqlite');
            DB::purge('sqlite');
            $this->removeSqliteSidecars($dbPath);

            if (File::exists($dbPath)) {
                File::delete($dbPath);
            }

            if (!File::exists($rollbackPath)) {
                return ['code' => 'rollback_missing', 'message' => 'Rollback file was missing. Manual recovery is required.'];
            }

            File::move($rollbackPath, $dbPath);

            $this->auditLogs->record('restore.rollback_succeeded', [], [
                'module' => 'backup',
            ]);

            return ['code' => 'rollback_succeeded', 'message' => 'Rollback from emergency staging file succeeded.'];
        } catch (Throwable) {
            $this->auditLogs->record('restore.rollback_failed', [], [
                'module' => 'backup',
            ]);

            return ['code' => 'rollback_failed', 'message' => 'Rollback failed. Manual recovery from emergency backup is required.'];
        }
    }

    protected function validateCurrentDatabase(): array
    {
        try {
            DB::purge('sqlite');
            $tables = DB::connection('sqlite')
                ->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name IN ('migrations', 'users')");
            $names = collect($tables)->pluck('name')->all();
            $missing = array_values(array_diff(['migrations', 'users'], $names));

            if ($missing !== []) {
                return ['valid' => false, 'code' => 'missing_tables', 'message' => 'Restored database is missing required tables.'];
            }

            return ['valid' => true, 'code' => 'schema_valid', 'message' => 'Restored database validated.'];
        } catch (Throwable) {
            return ['valid' => false, 'code' => 'unreadable', 'message' => 'Restored database could not be opened.'];
        }
    }

    protected function sqliteDatabasePath(): string
    {
        if (config('database.default') !== 'sqlite') {
            throw new RuntimeException('Restore currently supports SQLite databases only.');
        }

        $path = (string) config('database.connections.sqlite.database');
        if ($path === '' || $path === ':memory:') {
            throw new RuntimeException('Restore requires a file-backed SQLite database.');
        }

        $isWindowsAbsolute = preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
        $isUnixAbsolute = str_starts_with($path, '/');
        if (!$isWindowsAbsolute && !$isUnixAbsolute) {
            $path = base_path($path);
        }

        if (!File::exists($path)) {
            throw new RuntimeException('Current SQLite database file was not found.');
        }

        return $path;
    }

    protected function checkpointSqlite(): void
    {
        try {
            DB::connection('sqlite')->statement('PRAGMA wal_checkpoint(TRUNCATE)');
        } catch (Throwable) {
            // Restore still proceeds with an emergency backup and closed connection;
            // the sidecar files are removed only after the SQLite connection is purged.
        }
    }

    protected function removeSqliteSidecars(string $dbPath): void
    {
        foreach ([$dbPath . '-wal', $dbPath . '-shm'] as $sidecar) {
            if (File::exists($sidecar)) {
                File::delete($sidecar);
            }
        }
    }

    protected function fail(BackupLog $restoreLog, string $eventType, string $message, array $context = []): array
    {
        $restoreLog->update([
            'status' => 'failed',
            'completed_at' => now(),
            'message' => $message,
            'context' => array_merge($restoreLog->context ?? [], $context),
        ]);

        $this->auditLogs->record($eventType, array_merge([
            'restore_log_id' => $restoreLog->id,
        ], $context), [
            'module' => 'backup',
            'record_type' => BackupLog::class,
            'record_id' => $restoreLog->id,
        ]);

        return [
            'success' => false,
            'code' => Str::after($eventType, 'restore.'),
            'message' => $message,
            'restore_log' => $restoreLog->fresh(),
        ];
    }
}
