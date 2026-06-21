<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\BackupLog;
use App\Services\RestoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
}
