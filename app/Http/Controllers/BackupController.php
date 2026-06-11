<?php

namespace App\Http\Controllers;

use App\Services\Backup\BackupCleanupService;
use App\Services\Backup\SQLiteBackupService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class BackupController extends Controller
{
    public function downloadDatabase(
        SQLiteBackupService $service,
        BackupCleanupService $cleanup
    ): Response
    {
        if (!in_array(auth()->user()?->role, ['developer', 'admin'], true)) {
            return response('You do not have permission to download database backup.', 403);
        }

        try {
            $summary = $cleanup->cleanTemporary();

            if ($summary['failed'] > 0) {
                Log::warning('Temporary backup cleanup completed with failures', [
                    'summary' => $summary,
                    'user_id' => auth()->id(),
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('Temporary backup cleanup failed', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);
        }

        try {
            $backupPath = $service->createBackup(
                (string) config('backup.temporary_backup_path')
            );

            return response()->download($backupPath, basename($backupPath), [
                'Content-Type' => 'application/octet-stream',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ])->deleteFileAfterSend(true);
        } catch (Throwable $exception) {
            Log::error('DB backup failed', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return response('Backup generation failed.', 500);
        }
    }
}
