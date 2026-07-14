<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class RestoreUploadJobService
{
    private const BASE_PATH = 'app/private/restore-jobs';
    private const STALE_QUEUE_SECONDS = 60;

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
            'message' => 'The database file has been uploaded. Restore has not started yet.',
            'original_filename' => $originalName,
            'upload_path' => $uploadPath,
            'log_path' => $this->logPath($jobId),
            'user_id' => $userId,
            'confirmation_phrase' => (string) ($input['confirmation_phrase'] ?? ''),
            'staging_tested' => (bool) ($input['staging_tested'] ?? false),
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'started_at' => null,
            'completed_at' => null,
            'failed_at' => null,
            'last_heartbeat' => null,
            'process_start_error' => null,
            'can_run_now' => false,
        ];

        $this->writeStatus($jobId, $status);

        return $this->publicStatus($status);
    }

    public function start(string $jobId, bool $manual = false): void
    {
        $this->assertRunnableQueuedJob($jobId);

        $lock = Cache::lock("restore-upload-job-start:{$jobId}", 30);
        if (! $lock->get()) {
            throw new RuntimeException('Restore job is already being started.');
        }

        try {
            $status = $this->readStatus($jobId);
            $status['status'] = 'queued';
            $status['progress'] = 10;
            $status['message'] = 'The database file has been uploaded. Restore has not started yet.';
            $status['process_start_error'] = null;
            $status['can_run_now'] = false;
            $status['updated_at'] = now()->toIso8601String();
            $this->writeStatus($jobId, $status);

            $php = $this->resolvePhpCliBinary();
            $workingDirectory = base_path();
            $logPath = $this->logPath($jobId);
            $this->appendLog($jobId, 'Selected PHP CLI: ' . $php);
            $this->appendLog($jobId, 'Working directory: ' . $workingDirectory);
            $this->appendLog($jobId, 'Command: php artisan garmentsos:restore-upload-job ' . $jobId);
            $this->appendLog($jobId, 'Start mode: ' . ($manual ? 'manual run-now' : 'automatic'));

            $processStart = $this->startBackgroundProcess($php, $workingDirectory, $jobId, $logPath);
            $this->appendLog($jobId, 'Process start exit code: ' . ($processStart['exit_code'] ?? 'unknown'));
            if (($processStart['output'] ?? []) !== []) {
                $this->appendLog($jobId, 'Process start output: ' . implode(' | ', $processStart['output']));
            }

            if (! ($processStart['ok'] ?? false)) {
                $message = 'The database file was uploaded, but restore could not start automatically.';
                $status = $this->readStatus($jobId);
                $status['status'] = 'queued';
                $status['message'] = $message;
                $status['process_start_error'] = 'Process start failed with exit code ' . ($processStart['exit_code'] ?? 'unknown') . '.';
                $status['can_run_now'] = true;
                $status['updated_at'] = now()->toIso8601String();
                $this->writeStatus($jobId, $status);
                $this->appendLog($jobId, 'Process start failed.');
                return;
            }

            $this->appendLog($jobId, 'Process start requested successfully.');
        } finally {
            optional($lock)->release();
        }
    }

    public function markRunning(string $jobId, string $message = 'Restore is running.'): void
    {
        $status = $this->readStatus($jobId);
        $status['status'] = 'running';
        $status['progress'] = 30;
        $status['message'] = $message;
        $status['started_at'] = $status['started_at'] ?? now()->toIso8601String();
        $status['last_heartbeat'] = now()->toIso8601String();
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
        $status['failed_at'] = ($result['success'] ?? false) ? null : now()->toIso8601String();
        $status['last_heartbeat'] = now()->toIso8601String();
        $status['updated_at'] = now()->toIso8601String();
        $this->writeStatus($jobId, $status);

        if (($result['success'] ?? false) === true) {
            $this->markOlderQueuedJobsSuperseded($jobId);
        }
    }

    public function fail(string $jobId, string $message, array $context = []): void
    {
        $status = $this->readStatus($jobId);
        $status['status'] = 'failed';
        $status['progress'] = 0;
        $status['message'] = $message;
        $status['context'] = $context;
        $status['completed_at'] = now()->toIso8601String();
        $status['failed_at'] = now()->toIso8601String();
        $status['last_heartbeat'] = now()->toIso8601String();
        $status['updated_at'] = now()->toIso8601String();
        $this->writeStatus($jobId, $status);
    }

    public function canRunNow(string $jobId): bool
    {
        $status = $this->readPublicStatus($jobId);

        return ($status['status'] ?? null) === 'queued'
            && (($status['is_stale'] ?? false) || ! empty($status['process_start_error']));
    }

    public function readPublicStatus(string $jobId): array
    {
        return $this->publicStatus($this->readStatus($jobId));
    }

    public function preferredPublicStatus(?string $sessionJobId = null): ?array
    {
        $jobs = collect($this->allPublicStatuses());
        if ($jobs->isEmpty()) {
            return null;
        }

        $active = $jobs
            ->filter(fn (array $job): bool => in_array((string) ($job['status'] ?? ''), ['running'], true))
            ->sortByDesc(fn (array $job): int => strtotime((string) ($job['updated_at'] ?? $job['created_at'] ?? '')) ?: 0)
            ->first();
        if ($active) {
            return $active;
        }

        $sessionJob = $sessionJobId
            ? $jobs->firstWhere('id', $sessionJobId)
            : null;

        $latestCompleted = $jobs
            ->filter(fn (array $job): bool => ($job['status'] ?? '') === 'completed')
            ->sortByDesc(fn (array $job): int => strtotime((string) ($job['completed_at'] ?? $job['updated_at'] ?? $job['created_at'] ?? '')) ?: 0)
            ->first();

        if ($latestCompleted && $sessionJob && in_array((string) ($sessionJob['status'] ?? ''), ['queued', 'pending', 'superseded'], true)) {
            $completedAt = strtotime((string) ($latestCompleted['completed_at'] ?? $latestCompleted['updated_at'] ?? '')) ?: 0;
            $sessionAt = strtotime((string) ($sessionJob['created_at'] ?? '')) ?: 0;
            if ($completedAt >= $sessionAt) {
                return $latestCompleted;
            }
        }

        $waiting = $jobs
            ->filter(fn (array $job): bool => in_array((string) ($job['status'] ?? ''), ['queued', 'pending'], true))
            ->sortByDesc(fn (array $job): int => strtotime((string) ($job['updated_at'] ?? $job['created_at'] ?? '')) ?: 0)
            ->first();
        if ($waiting) {
            return $waiting;
        }

        return $latestCompleted ?: $jobs
            ->sortByDesc(fn (array $job): int => strtotime((string) ($job['updated_at'] ?? $job['created_at'] ?? '')) ?: 0)
            ->first();
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
        $status['is_stale'] = $this->isStaleQueued($status);
        $status['can_run_now'] = (($status['status'] ?? null) === 'queued')
            && ($status['is_stale'] || ! empty($status['process_start_error']));
        $status['safe_error'] = $status['process_start_error'] ? 'Restore could not start automatically.' : null;

        return $status;
    }

    private function allPublicStatuses(): array
    {
        $base = $this->jobsBaseDirectory();
        if (!File::isDirectory($base)) {
            return [];
        }

        $jobs = [];
        foreach (File::directories($base) as $directory) {
            $jobId = basename($directory);
            try {
                $jobs[] = $this->readPublicStatus($jobId);
            } catch (\Throwable) {
                //
            }
        }

        return $jobs;
    }

    private function markOlderQueuedJobsSuperseded(string $completedJobId): void
    {
        $completed = $this->readStatus($completedJobId);
        $completedAt = strtotime((string) ($completed['completed_at'] ?? $completed['updated_at'] ?? '')) ?: time();

        foreach ($this->allPublicStatuses() as $job) {
            $jobId = (string) ($job['id'] ?? '');
            if ($jobId === '' || $jobId === $completedJobId) {
                continue;
            }

            if (!in_array((string) ($job['status'] ?? ''), ['pending', 'queued'], true)) {
                continue;
            }

            $createdAt = strtotime((string) ($job['created_at'] ?? '')) ?: 0;
            if ($createdAt > $completedAt) {
                continue;
            }

            try {
                $status = $this->readStatus($jobId);
                $status['status'] = 'superseded';
                $status['progress'] = 100;
                $status['message'] = 'A newer restore job completed. This older queued job was ignored.';
                $status['completed_at'] = now()->toIso8601String();
                $status['updated_at'] = now()->toIso8601String();
                $this->writeStatus($jobId, $status);
            } catch (\Throwable) {
                //
            }
        }
    }

    private function assertRunnableQueuedJob(string $jobId): void
    {
        $status = $this->readStatus($jobId);
        $state = (string) ($status['status'] ?? '');

        if (! in_array($state, ['pending', 'queued'], true)) {
            throw new RuntimeException('Only waiting restore jobs can be started.');
        }
    }

    private function resolvePhpCliBinary(): string
    {
        foreach (['/usr/local/bin/php', '/usr/bin/php'] as $candidate) {
            if (is_file($candidate) && is_executable($candidate) && ! str_contains(basename($candidate), 'php-fpm')) {
                return $candidate;
            }
        }

        $detected = trim((string) shell_exec('command -v php 2>/dev/null'));
        if ($detected !== '' && ! str_contains(basename($detected), 'php-fpm')) {
            return $detected;
        }

        return 'php';
    }

    private function startBackgroundProcess(string $php, string $workingDirectory, string $jobId, string $logPath): array
    {
        $phpArg = escapeshellarg($php);
        $workingDirectoryArg = escapeshellarg($workingDirectory);
        $jobArg = escapeshellarg($jobId);
        $logArg = escapeshellarg($logPath);

        if (PHP_OS_FAMILY === 'Windows') {
            $command = "start /B \"\" {$phpArg} artisan garmentsos:restore-upload-job {$jobArg} >> {$logArg} 2>&1";
            $handle = @popen("cd /D {$workingDirectoryArg} && {$command}", 'r');
            if (! is_resource($handle)) {
                return ['ok' => false, 'exit_code' => null, 'output' => []];
            }

            $exitCode = pclose($handle);
            return ['ok' => $exitCode === 0, 'exit_code' => $exitCode, 'output' => []];
        }

        $command = "cd {$workingDirectoryArg} && nohup {$phpArg} artisan garmentsos:restore-upload-job {$jobArg} >> {$logArg} 2>&1 & echo $!";
        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);

        return ['ok' => $exitCode === 0, 'exit_code' => $exitCode, 'output' => $output];
    }

    private function appendLog(string $jobId, string $message): void
    {
        File::append($this->logPath($jobId), '[' . now()->toIso8601String() . '] ' . $message . PHP_EOL);
    }

    private function isStaleQueued(array $status): bool
    {
        if (($status['status'] ?? null) !== 'queued') {
            return false;
        }

        $updatedAt = strtotime((string) ($status['updated_at'] ?? $status['created_at'] ?? ''));
        if (! $updatedAt) {
            return false;
        }

        return (time() - $updatedAt) > self::STALE_QUEUE_SECONDS;
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

    private function jobsBaseDirectory(): string
    {
        return storage_path(self::BASE_PATH);
    }

    private function assertSafeJobId(string $jobId): void
    {
        if (!preg_match('/^[0-9]{14}-[a-z0-9]{10}$/', $jobId)) {
            throw new RuntimeException('Invalid restore job id.');
        }
    }
}
