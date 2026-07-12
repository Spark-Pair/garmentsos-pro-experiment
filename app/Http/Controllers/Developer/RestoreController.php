<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\BackupLog;
use App\Services\RestoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class RestoreController extends Controller
{
    public function show(BackupLog $backupLog, RestoreService $restore)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        return view('developer.license.restore', [
            'backupLog' => $backupLog,
            'inspection' => $restore->inspect($backupLog),
            'requirements' => $restore->requirements(),
            'confirmationPhrase' => $restore->confirmationPhrase($backupLog),
        ]);
    }

    public function store(BackupLog $backupLog, Request $request, RestoreService $restore): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        $result = $restore->restore($backupLog, [
            'confirmation_phrase' => (string) $request->input('confirmation_phrase', ''),
            'staging_tested' => $request->boolean('staging_tested'),
        ]);

        return redirect()
            ->route('developer.backups.restore.show', $backupLog)
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function upload(Request $request, RestoreService $restore): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        $validated = $request->validate([
            'sqlite_file' => ['required', 'file'],
            'confirmation_phrase' => ['required', 'string'],
            'staging_tested' => ['accepted'],
        ], [
            'staging_tested.accepted' => 'Confirm that this restore was tested on a staging/copy database first.',
        ]);

        try {
            $result = $restore->restoreUploadedSqlite($request->file('sqlite_file'), [
                'confirmation_phrase' => (string) $validated['confirmation_phrase'],
                'staging_tested' => $request->boolean('staging_tested'),
            ]);
        } catch (Throwable $e) {
            Log::error('Uploaded SQLite restore failed unexpectedly.', [
                'error' => $e->getMessage(),
                'type' => $e::class,
            ]);

            $result = [
                'success' => false,
                'message' => 'Restore failed safely. License/device approval remains tied to this installation.',
            ];
        }

        return redirect()
            ->route('developer.backups')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
