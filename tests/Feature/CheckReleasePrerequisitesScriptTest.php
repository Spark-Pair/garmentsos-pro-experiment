<?php

namespace Tests\Feature;

use FilesystemIterator;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CheckReleasePrerequisitesScriptTest extends TestCase
{
    private array $temporaryPaths = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->temporaryPaths) as $path) {
            $this->removePath($path);
        }

        parent::tearDown();
    }

    public function test_valid_fake_source_passes_without_modification(): void
    {
        $source = $this->createValidSource();
        $before = $this->snapshot($source);

        [$exitCode, $report] = $this->runChecker($source);

        $this->assertSame(0, $exitCode, json_encode($report));
        $this->assertSame('SAFE', $report['status']);
        $this->assertSame(0, $report['summary']['finding_count']);
        $this->assertSame($before, $this->snapshot($source));
    }

    /**
     * @dataProvider missingRequiredFileProvider
     */
    public function test_missing_required_preparation_file_fails(string $relativePath): void
    {
        $source = $this->createValidSource();
        unlink($source.'/'.$relativePath);

        [$exitCode, $report] = $this->runChecker($source);

        $this->assertSame(1, $exitCode);
        $this->assertFinding($report, 'missing_required_file', $relativePath);
    }

    public static function missingRequiredFileProvider(): array
    {
        return [
            'composer.json' => ['composer.json'],
            'composer.lock' => ['composer.lock'],
            'package.json' => ['package.json'],
            'package-lock.json' => ['package-lock.json'],
            'Vite config' => ['vite.config.js'],
            'CSS entry' => ['resources/css/app.css'],
            'JavaScript entry' => ['resources/js/app.js'],
        ];
    }

    public function test_validate_build_fails_when_manifest_is_missing(): void
    {
        $source = $this->createValidSource();
        unlink($source.'/public/build/manifest.json');

        [$exitCode, $report] = $this->runChecker($source, true);

        $this->assertSame(1, $exitCode);
        $this->assertFinding($report, 'missing_vite_manifest', 'public/build/manifest.json');
    }

    public function test_build_output_is_not_required_when_validation_is_disabled(): void
    {
        $source = $this->createValidSource();
        $this->removePath($source.'/public/build');

        [$exitCode, $report] = $this->runChecker($source, false);

        $this->assertSame(0, $exitCode, json_encode($report));
        $this->assertFalse($report['checks']['vite']['validation_requested']);
    }

    public function test_validate_build_fails_when_referenced_asset_is_missing(): void
    {
        $source = $this->createValidSource();
        unlink($source.'/public/build/assets/app-123.js');

        [$exitCode, $report] = $this->runChecker($source, true);

        $this->assertSame(1, $exitCode);
        $this->assertFinding($report, 'missing_vite_asset', 'public/build/assets/app-123.js');
        $this->assertSame(1, $report['summary']['vite_assets_missing']);
    }

    public function test_development_vendor_packages_are_reported_unsafe(): void
    {
        $source = $this->createValidSource();
        $installed = $this->productionInstalledMetadata();
        $installed['dev'] = true;
        $installed['packages'][] = ['name' => 'phpunit/phpunit'];
        $installed['packages'][] = ['name' => 'mockery/mockery'];
        $this->writeJson($source.'/vendor/composer/installed.json', $installed);

        [$exitCode, $report] = $this->runChecker($source);

        $this->assertSame(1, $exitCode);
        $this->assertFinding($report, 'vendor_dev_mode', 'vendor/composer/installed.json');
        $this->assertFinding($report, 'development_package', 'phpunit/phpunit');
        $this->assertFinding($report, 'development_package', 'mockery/mockery');
    }

    public function test_production_only_fake_vendor_passes(): void
    {
        $source = $this->createValidSource();

        [$exitCode, $report] = $this->runChecker($source);

        $this->assertSame(0, $exitCode, json_encode($report));
        $this->assertTrue($report['checks']['vendor']['production_mode']);
        $this->assertSame([], $report['checks']['vendor']['dev_packages']);
    }

    public function test_development_provider_in_bootstrap_cache_fails(): void
    {
        $source = $this->createValidSource();
        file_put_contents(
            $source.'/bootstrap/cache/packages.php',
            "<?php return ['laravel/sail' => ['providers' => ['Laravel\\\\Sail\\\\SailServiceProvider']]];"
        );

        [$exitCode, $report] = $this->runChecker($source);

        $this->assertSame(1, $exitCode);
        $this->assertFinding($report, 'development_provider_cache', 'bootstrap/cache/packages.php');
    }

    /**
     * @dataProvider prohibitedSourceContentProvider
     */
    public function test_prohibited_source_content_is_reported(string $relativePath, string $code): void
    {
        $source = $this->createValidSource();
        $this->writeFile($source.'/'.$relativePath, 'prohibited');

        [$exitCode, $report] = $this->runChecker($source);

        $this->assertSame(1, $exitCode, $relativePath);
        $this->assertFinding($report, $code, $relativePath);
    }

    public static function prohibitedSourceContentProvider(): array
    {
        return [
            '.env' => ['.env', 'environment_file'],
            'SQLite' => ['database/database.sqlite', 'sqlite_data'],
            'SQLite WAL' => ['database/database.sqlite-wal', 'sqlite_data'],
            'SQLite SHM' => ['database/database.sqlite-shm', 'sqlite_data'],
            'storage app' => ['storage/app/private.txt', 'storage_runtime'],
            'storage logs' => ['storage/logs/laravel.log', 'storage_runtime'],
            'storage sessions' => ['storage/framework/sessions/session', 'storage_runtime'],
            'public uploads' => ['public/uploads/image.jpg', 'uploads'],
            'root uploads' => ['uploads/image.jpg', 'uploads'],
            'logs' => ['logs/app.log', 'logs'],
            'backups' => ['backups/manual.sqlite', 'backups'],
            'public hot' => ['public/hot', 'vite_hot_file'],
            'public storage' => ['public/storage/file.txt', 'public_storage'],
            'node modules' => ['node_modules/vite/index.js', 'node_modules'],
            'tests' => ['tests/Feature/FakeTest.php', 'tests'],
            'docs' => ['docs/internal.md', 'documentation'],
            'scripts' => ['scripts/private.ps1', 'release_scripts'],
        ];
    }

    public function test_unsafe_symlink_is_reported_where_supported(): void
    {
        $source = $this->createValidSource();
        $target = $source.'/config/target.php';
        $link = $source.'/config/linked.php';
        $this->writeFile($target, "<?php return [];\n");

        if (!@symlink($target, $link)) {
            $this->markTestSkipped('Symlink creation is not supported in this environment.');
        }

        [$exitCode, $report] = $this->runChecker($source);

        $this->assertSame(1, $exitCode);
        $this->assertFinding($report, 'unsafe_link', 'config/linked.php');
    }

    public function test_empty_prohibited_directory_is_reported(): void
    {
        $source = $this->createValidSource();
        mkdir($source.'/public/storage', 0777, true);

        [$exitCode, $report] = $this->runChecker($source);

        $this->assertSame(1, $exitCode);
        $this->assertFinding($report, 'public_storage', 'public/storage');
    }

    public function test_invalid_rules_json_exits_two(): void
    {
        $source = $this->createValidSource();
        $rules = $this->newPath('invalid-rules.json');
        file_put_contents($rules, '{invalid');

        [$exitCode, $report] = $this->runChecker($source, false, $rules);

        $this->assertSame(2, $exitCode);
        $this->assertSame('ERROR', $report['status']);
    }

    public function test_missing_source_exits_two(): void
    {
        $source = $this->newPath('missing-source');

        [$exitCode, $report] = $this->runChecker($source);

        $this->assertSame(2, $exitCode);
        $this->assertSame('ERROR', $report['status']);
    }

    private function createValidSource(): string
    {
        $source = $this->temporaryDirectory('prerequisite-source');

        foreach ([
            'app',
            'bootstrap/cache',
            'config',
            'database/migrations',
            'public/build/assets',
            'resources/css',
            'resources/js',
            'routes',
            'vendor/composer',
        ] as $directory) {
            mkdir($source.'/'.$directory, 0777, true);
        }

        $composerLock = [
            'content-hash' => str_repeat('a', 32),
            'packages' => [['name' => 'laravel/framework']],
            'packages-dev' => [
                ['name' => 'phpunit/phpunit'],
                ['name' => 'mockery/mockery'],
            ],
        ];
        $packageJson = [
            'private' => true,
            'scripts' => ['build' => 'vite build'],
        ];
        $files = [
            'composer.json' => '{"require":{"laravel/framework":"^10.10"}}',
            'composer.lock' => json_encode($composerLock, JSON_THROW_ON_ERROR),
            'package.json' => json_encode($packageJson, JSON_THROW_ON_ERROR),
            'package-lock.json' => '{"lockfileVersion":3,"packages":{}}',
            'vite.config.js' => "export default {};\n",
            'resources/css/app.css' => '@import "tailwindcss";',
            'resources/js/app.js' => 'console.log("app");',
            'vendor/autoload.php' => "<?php return true;\n",
            'bootstrap/cache/packages.php' => "<?php return ['laravel/sanctum' => []];\n",
            'bootstrap/cache/services.php' => "<?php return ['providers' => []];\n",
            'public/build/manifest.json' => json_encode([
                'resources/js/app.js' => ['file' => 'assets/app-123.js'],
            ], JSON_THROW_ON_ERROR),
            'public/build/assets/app-123.js' => 'console.log("built");',
        ];

        foreach ($files as $relativePath => $contents) {
            $this->writeFile($source.'/'.$relativePath, $contents);
        }

        $this->writeJson(
            $source.'/vendor/composer/installed.json',
            $this->productionInstalledMetadata()
        );

        return $source;
    }

    private function productionInstalledMetadata(): array
    {
        return [
            'dev' => false,
            'packages' => [
                ['name' => 'laravel/framework'],
                ['name' => 'laravel/sanctum'],
            ],
        ];
    }

    private function runChecker(string $source, bool $validateBuild = false, ?string $rules = null): array
    {
        $process = new Process([
            PHP_BINARY,
            base_path('scripts/check-release-prerequisites.php'),
            '--source='.$source,
            '--rules='.($rules ?? base_path('scripts/release-rules.json')),
            '--validate-build='.($validateBuild ? 'true' : 'false'),
        ]);
        $process->run();
        $output = $process->getOutput();
        $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        return [$process->getExitCode(), $report, $output];
    }

    private function assertFinding(array $report, string $code, ?string $path = null): void
    {
        foreach ($report['findings'] ?? [] as $finding) {
            if ($finding['code'] === $code && ($path === null || ($finding['path'] ?? null) === $path)) {
                $this->addToAssertionCount(1);
                return;
            }
        }

        $this->fail("Expected finding {$code}".($path === null ? '' : " at {$path}").'.');
    }

    private function temporaryDirectory(string $prefix): string
    {
        $path = $this->newPath($prefix);
        mkdir($path, 0777, true);

        return $path;
    }

    private function newPath(string $prefix): string
    {
        $path = str_replace('\\', '/', sys_get_temp_dir().DIRECTORY_SEPARATOR.$prefix.'-'.Str::uuid());
        $this->temporaryPaths[] = $path;

        return $path;
    }

    private function writeJson(string $path, array $data): void
    {
        $this->writeFile(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );
    }

    private function writeFile(string $path, string $contents): void
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $contents);
    }

    private function snapshot(string $root): array
    {
        $snapshot = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $entry) {
            $relative = str_replace('\\', '/', substr($entry->getPathname(), strlen($root) + 1));
            $snapshot[$relative] = [
                'type' => $entry->isLink() ? 'link' : ($entry->isDir() ? 'directory' : 'file'),
                'size' => $entry->isFile() ? $entry->getSize() : null,
                'hash' => $entry->isFile() && !$entry->isLink()
                    ? hash_file('sha256', $entry->getPathname())
                    : null,
            ];
        }

        ksort($snapshot);

        return $snapshot;
    }

    private function removePath(string $path): void
    {
        if (!file_exists($path) && !is_link($path)) {
            return;
        }

        if (is_link($path) || is_file($path)) {
            unlink($path);
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $entry) {
            if ($entry->isLink() || $entry->isFile()) {
                unlink($entry->getPathname());
            } else {
                rmdir($entry->getPathname());
            }
        }

        rmdir($path);
    }
}
