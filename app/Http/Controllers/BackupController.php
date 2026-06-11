<?php

namespace App\Http\Controllers;

use App\Services\Backup\SQLiteBackupService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class BackupController extends Controller
{
    public function downloadDatabase(SQLiteBackupService $service): Response
    {
        if (!in_array(auth()->user()?->role, ['developer', 'admin'], true)) {
            return response('You do not have permission to download database backup.', 403);
        }

        try {
            $backupPath = $service->createBackup(storage_path('app/backups/tmp'));

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
