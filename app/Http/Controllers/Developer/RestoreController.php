<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\BackupLog;
use App\Services\RestoreService;
use App\Services\RestoreUploadJobService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
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

    public function upload(Request $request, RestoreUploadJobService $jobs): RedirectResponse
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
            $job = $jobs->create($request->file('sqlite_file'), [
                'confirmation_phrase' => (string) $validated['confirmation_phrase'],
                'staging_tested' => $request->boolean('staging_tested'),
            ], $request->user()?->id);

            $jobs->start((string) $job['id']);
        } catch (Throwable $e) {
            Log::error('Uploaded SQLite restore job could not be started.', [
                'error' => $e->getMessage(),
                'type' => $e::class,
            ]);

            return redirect()
                ->route('developer.backups')
                ->with('error', 'Restore could not be started. Check logs for details.');
        }

        return redirect()
            ->route('developer.backups')
            ->with('success', 'Restore started. Please wait.')
            ->with('restore_job_id', $job['id']);
    }

    public function uploadStatus(string $jobId, RestoreUploadJobService $jobs): JsonResponse
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return response()->json(['status' => 'forbidden'], 403);
        }

        try {
            return response()->json($jobs->readPublicStatus($jobId));
        } catch (Throwable $e) {
            Log::warning('Restore upload job status could not be read.', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'missing',
                'message' => 'Restore job status was not found.',
            ], 404);
        }
    }
}
