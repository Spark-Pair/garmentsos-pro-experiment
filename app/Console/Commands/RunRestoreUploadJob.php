<?php

namespace App\Console\Commands;

use App\Services\RestoreService;
use App\Services\RestoreUploadJobService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunRestoreUploadJob extends Command
{
    protected $signature = 'garmentsos:restore-upload-job {jobId}';

    protected $description = 'Run a previously uploaded SQLite restore job.';

    public function handle(RestoreUploadJobService $jobs, RestoreService $restore): int
    {
        $jobId = (string) $this->argument('jobId');

        try {
            $jobs->markRunning($jobId, 'Validating uploaded database and creating emergency backup.');

            $uploadPath = $jobs->uploadPath($jobId);
            $originalFilename = $jobs->originalFilename($jobId);
            $input = $jobs->input($jobId);

            $file = new UploadedFile(
                $uploadPath,
                $originalFilename,
                null,
                null,
                true,
            );

            $result = $restore->restoreUploadedSqlite($file, $input);
            $jobs->complete($jobId, $result);
            $jobs->cleanupUpload($jobId);

            return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $e) {
            Log::error('Restore upload job failed unexpectedly.', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
                'type' => $e::class,
            ]);

            try {
                $jobs->fail($jobId, 'Restore failed safely. Check logs for details.', [
                    'error' => $e->getMessage(),
                    'type' => $e::class,
                ]);
            } catch (Throwable) {
                //
            }

            return self::FAILURE;
        }
    }
}
