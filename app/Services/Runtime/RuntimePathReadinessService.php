<?php

namespace App\Services\Runtime;

use Illuminate\Contracts\Config\Repository;

class RuntimePathReadinessService
{
    public const PASS = 'PASS';
    public const WARN = 'WARN';
    public const FAIL = 'FAIL';

    public function __construct(private readonly Repository $config)
    {
    }

    /**
     * @return array{
     *     overall_status: string,
     *     checks: array<int, array{
     *         key: string,
     *         level: string,
     *         message: string,
     *         path?: string,
     *         details?: array<string, mixed>
     *     }>
     * }
     */
    public function check(): array
    {
        $checks = [];
        $externalMode = $this->config->get('runtime.external_mode') === true;
        $paths = $this->configuredPaths();

        foreach ($paths as $key => $path) {
            $this->checkDirectory($checks, $key, $path);
        }

        $this->checkDatabase($checks);
        $this->checkExternalContainment($checks, $paths, $externalMode);
        $this->checkPathConflicts($checks, $paths);
        $this->checkStaleDatabase($checks);
        $this->checkPublicStorage($checks);
        $this->checkSplitUploads($checks);
        $this->checkFreeSpace($checks, $paths);

        return [
            'overall_status' => $this->overallStatus($checks),
            'checks' => $checks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function configuredPaths(): array
    {
        return [
            'base_path' => $this->config->get('runtime.base_path'),
            'data_path' => $this->config->get('runtime.data_path'),
            'runtime_path' => $this->config->get('runtime.runtime_path'),
            'backup_path' => $this->config->get('runtime.backup_path'),
            'log_path' => $this->config->get('runtime.log_path'),
            'uploads_path' => $this->config->get('runtime.uploads_path'),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     */
    private function checkDirectory(array &$checks, string $key, mixed $path): void
    {
        if (!is_string($path) || trim($path) === '') {
            $this->addCheck($checks, $key, self::FAIL, 'Required path is not configured.');

            return;
        }

        $path = trim($path);

        if (!$this->isAbsolutePath($path)) {
            $this->addCheck($checks, $key, self::FAIL, 'Configured path must be absolute.', $path);

            return;
        }

        if (!$this->pathExists($path)) {
            $this->addCheck($checks, $key, self::FAIL, 'Configured directory does not exist.', $path);

            return;
        }

        if (!$this->isDirectory($path)) {
            $this->addCheck($checks, $key, self::FAIL, 'Configured path is not a directory.', $path);

            return;
        }

        if (!$this->isReadable($path) || !$this->isWritable($path)) {
            $this->addCheck(
                $checks,
                $key,
                self::FAIL,
                'Configured directory must be readable and writable.',
                $path
            );

            return;
        }

        $this->addCheck($checks, $key, self::PASS, 'Configured directory is ready.', $path);
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     */
    private function checkDatabase(array &$checks): void
    {
        if ($this->config->get('database.default') !== 'sqlite') {
            $this->addCheck(
                $checks,
                'database',
                self::WARN,
                'SQLite database readiness was not checked because SQLite is not the default connection.'
            );

            return;
        }

        $path = $this->config->get('database.connections.sqlite.database');

        if (!is_string($path) || trim($path) === '') {
            $this->addCheck($checks, 'database', self::FAIL, 'SQLite database path is not configured.');

            return;
        }

        $path = trim($path);

        if (!$this->isAbsolutePath($path)) {
            $this->addCheck($checks, 'database', self::FAIL, 'SQLite database path must be absolute.', $path);

            return;
        }

        if (!$this->pathExists($path) || !$this->isFile($path)) {
            $this->addCheck($checks, 'database', self::FAIL, 'SQLite database file does not exist.', $path);

            return;
        }

        if (!$this->hasSQLiteHeader($path)) {
            $this->addCheck($checks, 'database', self::FAIL, 'Configured database is not a valid SQLite file.', $path);

            return;
        }

        $parent = dirname($path);

        if (!$this->isReadable($path) || !$this->isReadable($parent) || !$this->isWritable($parent)) {
            $this->addCheck(
                $checks,
                'database',
                self::FAIL,
                'SQLite database and its parent directory are not ready.',
                $path
            );

            return;
        }

        $this->addCheck($checks, 'database', self::PASS, 'SQLite database path is ready.', $path);
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     * @param array<string, mixed> $paths
     */
    private function checkExternalContainment(array &$checks, array $paths, bool $externalMode): void
    {
        if (!$externalMode) {
            $this->addCheck(
                $checks,
                'external_mode',
                self::PASS,
                'External runtime mode is not enabled.'
            );

            return;
        }

        $basePath = $paths['base_path'] ?? null;

        if (!is_string($basePath) || !$this->isAbsolutePath($basePath)) {
            return;
        }

        $releaseRoots = [
            $this->joinPath($basePath, 'app/current'),
            $this->joinPath($basePath, 'app/releases'),
        ];

        foreach (['data_path', 'runtime_path', 'backup_path', 'log_path', 'uploads_path'] as $key) {
            $path = $paths[$key] ?? null;

            if (!is_string($path) || !$this->isAbsolutePath($path)) {
                continue;
            }

            foreach ($releaseRoots as $releaseRoot) {
                if ($this->isWithin($path, $releaseRoot)) {
                    $this->addCheck(
                        $checks,
                        'external_'.$key,
                        self::FAIL,
                        'External runtime path points inside a replaceable release directory.',
                        $path
                    );
                    continue 2;
                }
            }

            $this->addCheck(
                $checks,
                'external_'.$key,
                self::PASS,
                'External runtime path is outside release directories.',
                $path
            );
        }
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     * @param array<string, mixed> $paths
     */
    private function checkPathConflicts(array &$checks, array $paths): void
    {
        $leafKeys = ['runtime_path', 'backup_path', 'log_path', 'uploads_path'];
        $conflicts = [];

        for ($left = 0; $left < count($leafKeys); $left++) {
            for ($right = $left + 1; $right < count($leafKeys); $right++) {
                $leftKey = $leafKeys[$left];
                $rightKey = $leafKeys[$right];
                $leftPath = $paths[$leftKey] ?? null;
                $rightPath = $paths[$rightKey] ?? null;

                if (!is_string($leftPath) || !is_string($rightPath)) {
                    continue;
                }

                if (
                    $this->samePath($leftPath, $rightPath)
                    || $this->isWithin($leftPath, $rightPath)
                    || $this->isWithin($rightPath, $leftPath)
                ) {
                    $conflicts[] = $leftKey.' / '.$rightKey;
                }
            }
        }

        if ($conflicts !== []) {
            $this->addCheck(
                $checks,
                'path_conflicts',
                self::FAIL,
                'Operational runtime paths overlap.',
                null,
                ['conflicts' => $conflicts]
            );

            return;
        }

        $this->addCheck($checks, 'path_conflicts', self::PASS, 'No operational path conflicts detected.');
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     */
    private function checkStaleDatabase(array &$checks): void
    {
        $storagePath = $this->config->get('runtime.storage_path');

        if (!is_string($storagePath) || $storagePath === '') {
            return;
        }

        $stalePath = $this->joinPath($storagePath, 'app/database.sqlite');

        if ($this->pathExists($stalePath) && $this->isFile($stalePath)) {
            $this->addCheck(
                $checks,
                'stale_database',
                self::WARN,
                'A stale storage/app/database.sqlite file is present.',
                $stalePath
            );
        }
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     */
    private function checkPublicStorage(array &$checks): void
    {
        $publicPath = $this->config->get('runtime.public_path');

        if (!is_string($publicPath) || $publicPath === '') {
            return;
        }

        $storageLink = $this->joinPath($publicPath, 'storage');

        if ($this->pathExists($storageLink) && !$this->isLink($storageLink)) {
            $this->addCheck(
                $checks,
                'public_storage',
                self::WARN,
                'public/storage exists but is not a symbolic link.',
                $storageLink
            );
        }
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     */
    private function checkSplitUploads(array &$checks): void
    {
        $storagePath = $this->config->get('runtime.storage_path');
        $publicPath = $this->config->get('runtime.public_path');

        if (!is_string($storagePath) || !is_string($publicPath)) {
            return;
        }

        $storageUploads = $this->joinPath($storagePath, 'app/public/uploads');
        $publicUploads = $this->joinPath($publicPath, 'uploads');

        if ($this->isDirectory($storageUploads) && $this->isDirectory($publicUploads)) {
            $this->addCheck(
                $checks,
                'split_uploads',
                self::WARN,
                'Uploads are present in both storage and public directories.',
                null,
                ['paths' => [$storageUploads, $publicUploads]]
            );
        }
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     * @param array<string, mixed> $paths
     */
    private function checkFreeSpace(array &$checks, array $paths): void
    {
        $anchor = $paths['base_path'] ?? $paths['data_path'] ?? null;

        if (!is_string($anchor) || !$this->pathExists($anchor)) {
            return;
        }

        $bytes = $this->freeSpace($anchor);

        if ($bytes === false) {
            $this->addCheck($checks, 'free_space', self::WARN, 'Free disk space could not be determined.', $anchor);

            return;
        }

        $freeMb = (int) floor($bytes / 1024 / 1024);
        $minimum = $this->config->get('runtime.minimum_free_space_mb', 0);
        $minimum = is_int($minimum) && $minimum > 0 ? $minimum : 0;
        $level = $minimum > 0 && $freeMb < $minimum ? self::FAIL : self::PASS;

        $this->addCheck(
            $checks,
            'free_space',
            $level,
            $level === self::PASS ? 'Free disk space is sufficient.' : 'Free disk space is below the required minimum.',
            $anchor,
            ['free_mb' => $freeMb, 'minimum_mb' => $minimum]
        );
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     * @param array<string, mixed> $details
     */
    private function addCheck(
        array &$checks,
        string $key,
        string $level,
        string $message,
        ?string $path = null,
        array $details = []
    ): void {
        $check = compact('key', 'level', 'message');

        if ($path !== null) {
            $check['path'] = $path;
        }

        if ($details !== []) {
            $check['details'] = $details;
        }

        $checks[] = $check;
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     */
    private function overallStatus(array $checks): string
    {
        if (collect($checks)->contains(fn (array $check) => $check['level'] === self::FAIL)) {
            return self::FAIL;
        }

        if (collect($checks)->contains(fn (array $check) => $check['level'] === self::WARN)) {
            return self::WARN;
        }

        return self::PASS;
    }

    protected function pathExists(string $path): bool
    {
        return file_exists($path) || is_link($path);
    }

    protected function isDirectory(string $path): bool
    {
        return is_dir($path);
    }

    protected function isFile(string $path): bool
    {
        return is_file($path);
    }

    protected function isLink(string $path): bool
    {
        return is_link($path);
    }

    protected function isReadable(string $path): bool
    {
        return is_readable($path);
    }

    protected function isWritable(string $path): bool
    {
        return is_writable($path);
    }

    protected function freeSpace(string $path): int|float|false
    {
        return @disk_free_space($path);
    }

    protected function hasSQLiteHeader(string $path): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        try {
            return fread($handle, 16) === "SQLite format 3\0";
        } finally {
            fclose($handle);
        }
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1
            || str_starts_with($path, '\\\\');
    }

    private function joinPath(string $base, string $suffix): string
    {
        return rtrim($base, '\\/').DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $suffix);
    }

    private function samePath(string $left, string $right): bool
    {
        return $this->normalizePath($left) === $this->normalizePath($right);
    }

    private function isWithin(string $path, string $parent): bool
    {
        $path = $this->normalizePath($path);
        $parent = $this->normalizePath($parent);

        return $path === $parent || str_starts_with($path, $parent.DIRECTORY_SEPARATOR);
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, rtrim(trim($path), '\\/'));

        return DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
    }
}
