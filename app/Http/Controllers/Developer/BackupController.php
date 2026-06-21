<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\BackupLog;
use App\Services\BackupService;
use App\Services\BackupStorageService;
use App\Services\BackupVerifier;
use App\Services\RestoreService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function index(BackupStorageService $storage, RestoreService $restore)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        return view('developer.license.backups', [
            'logs' => $storage->listManagedBackups(),
            'restoreRequirements' => $restore->requirements(),
        ]);
    }

    public function store(BackupService $backups): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        $result = $backups->createManualBackup('manual_backup');

        return redirect()
            ->route('developer.backups')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
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

        $result = $backups->createManualBackup('legacy_download_backup');

        if (!$result['success']) {
            abort(500, $result['message']);
        }

        return response()->download($result['path'], $result['filename'], [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}
