<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class AppStorageRepairService
{
    public function requiredDirectories(): array
    {
        return [
            storage_path(),
            storage_path('app/license'),
            storage_path('app/private/backups'),
            storage_path('app/private/restore-jobs'),
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
            database_path(),
        ];
    }

    public function repair(bool $clearCacheData = true): array
    {
        $results = [];

        foreach ($this->requiredDirectories() as $path) {
            $created = false;
            $error = null;

            try {
                File::ensureDirectoryExists($path);
                $created = true;
                $this->makeWritable($path, recursive: true);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }

            $results[] = [
                'path' => $path,
                'created' => $created,
                'exists' => File::isDirectory($path),
                'writable' => is_writable($path),
                'error' => $error,
            ];
        }

        if ($clearCacheData) {
            $results[] = $this->clearCacheData();
        }

        return $results;
    }

    public function guardForBackupRestore(): void
    {
        $this->repair();

        $required = [
            storage_path('app/license'),
            storage_path('app/private/backups'),
            storage_path('app/private/restore-jobs'),
            storage_path('framework/cache/data'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
            database_path(),
        ];

        $failed = [];
        foreach ($required as $path) {
            if (!$this->canWriteProbe($path)) {
                $failed[] = $path;
            }
        }

        if ($failed !== []) {
            throw new RuntimeException(
                'Restore failed because app storage/cache is not writable. Restart app or run Repair. Paths: '
                . implode(', ', $failed)
            );
        }
    }

    protected function clearCacheData(): array
    {
        $path = storage_path('framework/cache/data');
        $deleted = 0;
        $errors = [];

        try {
            File::ensureDirectoryExists($path);
            foreach (File::allFiles($path) as $file) {
                try {
                    File::delete($file->getPathname());
                    $deleted++;
                } catch (Throwable $e) {
                    $errors[] = $file->getPathname() . ': ' . $e->getMessage();
                }
            }

            foreach (array_reverse(File::directories($path)) as $directory) {
                try {
                    if (File::isDirectory($directory) && count(File::allFiles($directory)) === 0 && File::directories($directory) === []) {
                        File::deleteDirectory($directory);
                    }
                } catch (Throwable $e) {
                    $errors[] = $directory . ': ' . $e->getMessage();
                }
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }

        return [
            'path' => $path,
            'action' => 'clear_cache_data',
            'deleted_files' => $deleted,
            'exists' => File::isDirectory($path),
            'writable' => is_writable($path),
            'error' => $errors === [] ? null : implode(' | ', array_slice($errors, 0, 5)),
        ];
    }

    protected function canWriteProbe(string $directory): bool
    {
        try {
            File::ensureDirectoryExists($directory);
            $probe = $directory . DIRECTORY_SEPARATOR . '.garmentsos-write-test-' . bin2hex(random_bytes(4));
            File::put($probe, 'ok');
            File::delete($probe);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    protected function makeWritable(string $path, bool $recursive = false): void
    {
        @chmod($path, 0775);

        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            @chown($path, 'www-data');
            @chgrp($path, 'www-data');
        }

        if (!$recursive || !File::isDirectory($path)) {
            return;
        }

        try {
            foreach (File::directories($path) as $directory) {
                $this->makeWritable($directory, recursive: true);
            }

            foreach (File::files($path) as $file) {
                @chmod($file->getPathname(), 0664);
                if (function_exists('posix_getuid') && posix_getuid() === 0) {
                    @chown($file->getPathname(), 'www-data');
                    @chgrp($file->getPathname(), 'www-data');
                }
            }
        } catch (Throwable) {
            // Writability probes report the actionable failure after best-effort repair.
        }
    }
}
