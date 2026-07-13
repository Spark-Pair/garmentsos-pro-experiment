<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class RestoreUploadJobService
{
    private const BASE_PATH = 'app/private/restore-jobs';

    public function create(UploadedFile $file, array $input, ?int $userId): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['sqlite', 'db'], true)) {
            throw new RuntimeException('Restore file must be a SQLite .sqlite or .db file.');
        }

        $jobId = now()->format('YmdHis') . '-' . Str::lower(Str::random(10));
        $jobDir = $this->jobDirectory($jobId);
        File::ensureDirectoryExists($jobDir);

        $originalName = basename($file->getClientOriginalName() ?: 'uploaded-database.' . $extension);
        $uploadName = 'uploaded.sqlite';
        $uploadPath = $jobDir . DIRECTORY_SEPARATOR . $uploadName;
        $file->move($jobDir, $uploadName);

        $status = [
            'id' => $jobId,
            'status' => 'pending',
            'progress' => 5,
            'message' => 'Restore started. Please wait.',
            'original_filename' => $originalName,
            'upload_path' => $uploadPath,
            'log_path' => $this->logPath($jobId),
            'user_id' => $userId,
            'confirmation_phrase' => (string) ($input['confirmation_phrase'] ?? ''),
            'staging_tested' => (bool) ($input['staging_tested'] ?? false),
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        $this->writeStatus($jobId, $status);

        return $this->publicStatus($status);
    }

    public function start(string $jobId): void
    {
        $status = $this->readStatus($jobId);
        $status['status'] = 'queued';
        $status['progress'] = 10;
        $status['message'] = 'Restore job queued.';
        $status['updated_at'] = now()->toIso8601String();
        $this->writeStatus($jobId, $status);

        $php = escapeshellarg(PHP_BINARY);
        $artisan = escapeshellarg(base_path('artisan'));
        $job = escapeshellarg($jobId);
        $log = escapeshellarg($this->logPath($jobId));

        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen("start /B \"\" {$php} {$artisan} garmentsos:restore-upload-job {$job} >> {$log} 2>&1", 'r'));
            return;
        }

        exec("{$php} {$artisan} garmentsos:restore-upload-job {$job} >> {$log} 2>&1 &");
    }

    public function markRunning(string $jobId, string $message = 'Restore is running.'): void
    {
        $status = $this->readStatus($jobId);
        $status['status'] = 'running';
        $status['progress'] = 30;
        $status['message'] = $message;
        $status['started_at'] = $status['started_at'] ?? now()->toIso8601String();
        $status['updated_at'] = now()->toIso8601String();
        $this->writeStatus($jobId, $status);
    }

    public function complete(string $jobId, array $result): void
    {
        $status = $this->readStatus($jobId);
        $status['status'] = ($result['success'] ?? false) ? 'completed' : 'failed';
        $status['progress'] = ($result['success'] ?? false) ? 100 : 0;
        $status['message'] = (string) ($result['message'] ?? (($result['success'] ?? false) ? 'Restore completed.' : 'Restore failed safely.'));
        $status['code'] = $result['code'] ?? null;
        $status['restore_log_id'] = $result['restore_log']->id ?? null;
        $status['emergency_backup_log_id'] = $result['emergency_backup_log']->id ?? null;
        $status['completed_at'] = now()->toIso8601String();
        $status['updated_at'] = now()->toIso8601String();
        $this->writeStatus($jobId, $status);
    }

    public function fail(string $jobId, string $message, array $context = []): void
    {
        $status = $this->readStatus($jobId);
        $status['status'] = 'failed';
        $status['progress'] = 0;
        $status['message'] = $message;
        $status['context'] = $context;
        $status['completed_at'] = now()->toIso8601String();
        $status['updated_at'] = now()->toIso8601String();
        $this->writeStatus($jobId, $status);
    }

    public function readPublicStatus(string $jobId): array
    {
        return $this->publicStatus($this->readStatus($jobId));
    }

    public function readStatus(string $jobId): array
    {
        $this->assertSafeJobId($jobId);
        $path = $this->statusPath($jobId);
        if (!File::exists($path)) {
            throw new RuntimeException('Restore job was not found.');
        }

        $decoded = json_decode((string) File::get($path), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Restore job status could not be read.');
        }

        return $decoded;
    }

    public function uploadPath(string $jobId): string
    {
        $status = $this->readStatus($jobId);
        $path = (string) ($status['upload_path'] ?? '');
        if ($path === '' || !File::exists($path)) {
            throw new RuntimeException('Uploaded restore file was not found.');
        }

        return $path;
    }

    public function originalFilename(string $jobId): string
    {
        $status = $this->readStatus($jobId);

        return basename((string) ($status['original_filename'] ?? 'uploaded.sqlite'));
    }

    public function input(string $jobId): array
    {
        $status = $this->readStatus($jobId);

        return [
            'confirmation_phrase' => (string) ($status['confirmation_phrase'] ?? ''),
            'staging_tested' => (bool) ($status['staging_tested'] ?? false),
        ];
    }

    public function cleanupUpload(string $jobId): void
    {
        try {
            $path = $this->uploadPath($jobId);
            if (File::exists($path)) {
                File::delete($path);
            }
        } catch (RuntimeException) {
            //
        }
    }

    private function publicStatus(array $status): array
    {
        unset($status['upload_path'], $status['confirmation_phrase']);

        return $status;
    }

    private function writeStatus(string $jobId, array $status): void
    {
        $this->assertSafeJobId($jobId);
        File::ensureDirectoryExists($this->jobDirectory($jobId));
        File::put($this->statusPath($jobId), json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function statusPath(string $jobId): string
    {
        return $this->jobDirectory($jobId) . DIRECTORY_SEPARATOR . 'status.json';
    }

    public function logPath(string $jobId): string
    {
        $this->assertSafeJobId($jobId);

        return $this->jobDirectory($jobId) . DIRECTORY_SEPARATOR . 'restore.log';
    }

    private function jobDirectory(string $jobId): string
    {
        $this->assertSafeJobId($jobId);

        return storage_path(self::BASE_PATH . '/' . $jobId);
    }

    private function assertSafeJobId(string $jobId): void
    {
        if (!preg_match('/^[0-9]{14}-[a-z0-9]{10}$/', $jobId)) {
            throw new RuntimeException('Invalid restore job id.');
        }
    }
}
