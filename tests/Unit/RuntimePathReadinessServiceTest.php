<?php

namespace Tests\Unit;

use App\Services\Runtime\RuntimePathReadinessService;
use Illuminate\Config\Repository;
use Tests\TestCase;

class RuntimePathReadinessServiceTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR
            .'garmentsos-runtime-test-'.bin2hex(random_bytes(8));

        mkdir($this->temporaryDirectory, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_runtime_config_defaults_preserve_current_layout(): void
    {
        $this->assertFalse(config('runtime.external_mode'));
        $this->assertSame(base_path(), config('runtime.base_path'));
        $this->assertSame(storage_path('app'), config('runtime.data_path'));
        $this->assertSame(storage_path(), config('runtime.runtime_path'));
        $this->assertSame(storage_path('app/backups'), config('runtime.backup_path'));
        $this->assertSame(storage_path('logs'), config('runtime.log_path'));
        $this->assertSame(storage_path('app/public/uploads'), config('runtime.uploads_path'));
        $this->assertSame(1024, config('runtime.minimum_free_space_mb'));
    }

    public function test_complete_temporary_external_layout_passes(): void
    {
        $layout = $this->createLayout();

        $result = $this->serviceFor($layout)->check();

        $this->assertSame(RuntimePathReadinessService::PASS, $result['overall_status']);
        $this->assertNotEmpty($result['checks']);
        $this->assertSame(
            RuntimePathReadinessService::PASS,
            $this->checkByKey($result, 'database')['level']
        );
    }

    public function test_missing_required_path_fails(): void
    {
        $layout = $this->createLayout();
        $layout['uploads_path'] = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'missing-uploads';

        $result = $this->serviceFor($layout)->check();

        $this->assertSame(RuntimePathReadinessService::FAIL, $result['overall_status']);
        $this->assertSame(RuntimePathReadinessService::FAIL, $this->checkByKey($result, 'uploads_path')['level']);
    }

    public function test_relative_path_fails(): void
    {
        $layout = $this->createLayout();
        $layout['backup_path'] = 'relative/backups';

        $result = $this->serviceFor($layout)->check();

        $this->assertSame(RuntimePathReadinessService::FAIL, $this->checkByKey($result, 'backup_path')['level']);
    }

    public function test_path_inside_release_directory_fails_in_external_mode(): void
    {
        $layout = $this->createLayout();
        $unsafePath = $layout['base_path'].DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'current'
            .DIRECTORY_SEPARATOR.'runtime';
        mkdir($unsafePath, 0775, true);
        $layout['runtime_path'] = $unsafePath;

        $result = $this->serviceFor($layout)->check();

        $this->assertSame(
            RuntimePathReadinessService::FAIL,
            $this->checkByKey($result, 'external_runtime_path')['level']
        );
    }

    public function test_stale_storage_database_produces_warning(): void
    {
        $layout = $this->createLayout();
        $stalePath = $layout['storage_path'].DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'database.sqlite';
        mkdir(dirname($stalePath), 0775, true);
        file_put_contents($stalePath, "SQLite format 3\0".str_repeat("\0", 128));

        $result = $this->serviceFor($layout)->check();

        $this->assertSame(RuntimePathReadinessService::WARN, $result['overall_status']);
        $this->assertSame(RuntimePathReadinessService::WARN, $this->checkByKey($result, 'stale_database')['level']);
    }

    public function test_invalid_public_storage_produces_warning(): void
    {
        $layout = $this->createLayout();
        file_put_contents($layout['public_path'].DIRECTORY_SEPARATOR.'storage', 'not a link');

        $result = $this->serviceFor($layout)->check();

        $this->assertSame(RuntimePathReadinessService::WARN, $this->checkByKey($result, 'public_storage')['level']);
    }

    public function test_overlapping_operational_paths_are_detected(): void
    {
        $layout = $this->createLayout();
        $layout['log_path'] = $layout['backup_path'];

        $result = $this->serviceFor($layout)->check();
        $check = $this->checkByKey($result, 'path_conflicts');

        $this->assertSame(RuntimePathReadinessService::FAIL, $check['level']);
        $this->assertContains('backup_path / log_path', $check['details']['conflicts']);
    }

    public function test_fake_sqlite_file_is_validated_without_production_database_access(): void
    {
        $layout = $this->createLayout();
        file_put_contents($layout['database_path'], 'not sqlite');

        $result = $this->serviceFor($layout)->check();

        $this->assertSame(RuntimePathReadinessService::FAIL, $this->checkByKey($result, 'database')['level']);
    }

    public function test_checker_does_not_modify_the_filesystem(): void
    {
        $layout = $this->createLayout();
        $before = $this->snapshot($this->temporaryDirectory);

        $this->serviceFor($layout)->check();

        $this->assertSame($before, $this->snapshot($this->temporaryDirectory));
    }

    /**
     * @return array<string, string>
     */
    private function createLayout(): array
    {
        $base = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'GarmentsOSPro';
        $paths = [
            'base_path' => $base,
            'data_path' => $base.DIRECTORY_SEPARATOR.'data',
            'runtime_path' => $base.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'runtime',
            'backup_path' => $base.DIRECTORY_SEPARATOR.'backups',
            'log_path' => $base.DIRECTORY_SEPARATOR.'logs',
            'uploads_path' => $base.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'uploads',
            'application_path' => $base.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'current',
            'storage_path' => $base.DIRECTORY_SEPARATOR.'test-current-storage',
            'public_path' => $base.DIRECTORY_SEPARATOR.'test-current-public',
            'database_path' => $base.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'database.sqlite',
        ];

        foreach ([
            'base_path',
            'data_path',
            'runtime_path',
            'backup_path',
            'log_path',
            'uploads_path',
            'application_path',
            'storage_path',
            'public_path',
        ] as $key) {
            if (!is_dir($paths[$key])) {
                mkdir($paths[$key], 0775, true);
            }
        }

        file_put_contents($paths['database_path'], "SQLite format 3\0".str_repeat("\0", 128));

        return $paths;
    }

    /**
     * @param array<string, string> $layout
     */
    private function serviceFor(array $layout): RuntimePathReadinessService
    {
        $config = new Repository([
            'runtime' => [
                'external_mode' => true,
                'base_path' => $layout['base_path'],
                'data_path' => $layout['data_path'],
                'runtime_path' => $layout['runtime_path'],
                'backup_path' => $layout['backup_path'],
                'log_path' => $layout['log_path'],
                'uploads_path' => $layout['uploads_path'],
                'minimum_free_space_mb' => 1,
                'application_path' => $layout['application_path'],
                'storage_path' => $layout['storage_path'],
                'public_path' => $layout['public_path'],
            ],
            'database' => [
                'default' => 'sqlite',
                'connections' => [
                    'sqlite' => [
                        'driver' => 'sqlite',
                        'database' => $layout['database_path'],
                    ],
                ],
            ],
        ]);

        return new class($config) extends RuntimePathReadinessService {
            protected function freeSpace(string $path): int|float|false
            {
                return 10 * 1024 * 1024 * 1024;
            }
        };
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function checkByKey(array $result, string $key): array
    {
        foreach ($result['checks'] as $check) {
            if ($check['key'] === $key) {
                return $check;
            }
        }

        $this->fail("Check [{$key}] was not returned.");
    }

    /**
     * @return array<string, array{size: int, modified: int}>
     */
    private function snapshot(string $directory): array
    {
        $snapshot = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($directory) + 1);
            $snapshot[$relative] = [
                'size' => $item->isFile() ? $item->getSize() : 0,
                'modified' => $item->getMTime(),
            ];
        }

        ksort($snapshot);

        return $snapshot;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
