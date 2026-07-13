<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\BackupLog;
use App\Services\BackupService;
use App\Services\BackupStorageService;
use App\Services\BackupVerifier;
use App\Services\RestoreService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BackupController extends Controller
{
    public function index(BackupStorageService $storage, RestoreService $restore)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        $foundationReady = $this->backupTablesReady();

        return view('developer.license.backups', [
            'logs' => $foundationReady ? $storage->listManagedBackups() : collect(),
            'restoreRequirements' => $restore->requirements(),
            'foundationReady' => $foundationReady,
            'missingTables' => $this->missingBackupTables(),
            'diagnostics' => $this->diagnostics($storage),
        ]);
    }

    public function store(BackupService $backups): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        if (!$this->backupTablesReady()) {
            return redirect()
                ->route('developer.backups')
                ->with('error', 'Backup tables are not available yet. Run migrations on a verified staging/client-copy database before creating backups.');
        }

        try {
            $result = $backups->createManualBackup('manual_backup');
        } catch (Throwable $e) {
            Log::error('Developer backup action failed unexpectedly.', [
                'error' => $e->getMessage(),
                'type' => $e::class,
            ]);

            $result = [
                'success' => false,
                'message' => 'Backup failed safely. Check logs for details.',
            ];
        }

        return redirect()
            ->route('developer.backups')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function runMigrations(Request $request): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        $request->validate([
            'confirm_migrations' => ['accepted'],
        ], [
            'confirm_migrations.accepted' => 'Please confirm before running database migrations.',
        ]);

        try {
            $exitCode = Artisan::call('migrate', ['--force' => true]);
            $output = trim(Artisan::output());

            Log::info('Developer database migrations executed from backup page.', [
                'exit_code' => $exitCode,
                'output' => substr($output, 0, 2000),
            ]);

            return redirect()
                ->route('developer.backups')
                ->with($exitCode === 0 ? 'success' : 'error', $exitCode === 0
                    ? 'Database migrations completed.'
                    : 'Database migrations finished with errors. Check logs for details.');
        } catch (Throwable $e) {
            Log::error('Developer database migration action failed from backup page.', [
                'error' => $e->getMessage(),
                'type' => $e::class,
            ]);

            return redirect()
                ->route('developer.backups')
                ->with('error', 'Database migrations could not be run. Check logs for details.');
        }
    }

    public function verify(BackupLog $backupLog, BackupStorageService $storage, BackupVerifier $verifier): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        try {
            $path = $storage->resolveManagedPath($backupLog);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('developer.backups')
                ->with('error', 'Backup verification blocked: ' . $e->getMessage());
        }

        $result = $verifier->verify($path, $backupLog->checksum);

        app(\App\Services\AuditLogService::class)->record('backup.verify_' . ($result['valid'] ? 'succeeded' : 'failed'), [
            'backup_log_id' => $backupLog->id,
            'filename' => $backupLog->filename,
            'result' => $result['code'],
        ], [
            'module' => 'backup',
            'record_type' => BackupLog::class,
            'record_id' => $backupLog->id,
        ]);

        return redirect()
            ->route('developer.backups')
            ->with($result['valid'] ? 'success' : 'error', $result['message']);
    }

    public function download(
        BackupLog $backupLog,
        BackupStorageService $storage,
        BackupVerifier $verifier,
    ): BinaryFileResponse|RedirectResponse {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        try {
            $path = $storage->resolveManagedPath($backupLog);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('developer.backups')
                ->with('error', 'Backup download blocked: ' . $e->getMessage());
        }

        $result = $verifier->verify($path, $backupLog->checksum);

        app(\App\Services\AuditLogService::class)->record('backup.download_' . ($result['valid'] ? 'started' : 'blocked'), [
            'backup_log_id' => $backupLog->id,
            'filename' => $backupLog->filename,
            'verification' => $result['code'],
        ], [
            'module' => 'backup',
            'record_type' => BackupLog::class,
            'record_id' => $backupLog->id,
        ]);

        if (!$result['valid']) {
            return redirect()
                ->route('developer.backups')
                ->with('error', 'Backup download blocked: ' . $result['message']);
        }

        return response()->download($path, $backupLog->filename, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function legacyDownload(BackupService $backups): BinaryFileResponse
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            abort(403, 'You do not have permission to download database backup.');
        }

        if (!$this->backupTablesReady()) {
            abort(503, 'Backup tables are not available yet. Run migrations before creating backups.');
        }

        $result = $backups->createManualBackup('legacy_download_backup');

        if (!$result['success']) {
            abort(500, $result['message']);
        }

        return response()->download($result['path'], $result['filename'], [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    protected function backupTablesReady(): bool
    {
        return $this->missingBackupTables() === [];
    }

    protected function missingBackupTables(): array
    {
        return array_values(array_filter([
            'app_installations',
            'backup_logs',
            'audit_logs',
        ], fn (string $table) => !Schema::hasTable($table)));
    }

    protected function diagnostics(BackupStorageService $storage): array
    {
        $dbPath = (string) config('database.connections.sqlite.database');
        if ($dbPath !== '' && $dbPath !== ':memory:' && !preg_match('/^[A-Za-z]:[\\\\\\/]/', $dbPath) && !str_starts_with($dbPath, '/')) {
            $dbPath = base_path($dbPath);
        }

        return [
            'database_path' => $dbPath,
            'database_exists' => $dbPath !== '' && File::exists($dbPath),
            'database_readable' => $dbPath !== '' && is_readable($dbPath),
            'database_writable' => $dbPath !== '' && is_writable($dbPath),
            'storage_writable' => is_writable(storage_path()),
            'backup_path' => $storage->basePath(),
            'backup_path_writable' => is_writable($storage->basePath()) || is_writable(dirname($storage->basePath())),
            'restore_temp_path' => $storage->tempPath(),
            'restore_temp_writable' => is_writable($storage->tempPath()) || is_writable(dirname($storage->tempPath())),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time'),
            'app_user' => get_current_user(),
        ];
    }
}
