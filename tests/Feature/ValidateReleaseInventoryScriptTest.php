<?php

namespace Tests\Feature;

use FilesystemIterator;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ValidateReleaseInventoryScriptTest extends TestCase
{
    private array $temporaryPaths = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->temporaryPaths) as $path) {
            $this->removePath($path);
        }

        parent::tearDown();
    }

    public function test_complete_valid_staged_directory_passes_and_reports_inventory(): void
    {
        $staging = $this->createValidStagingDirectory();
        $before = $this->snapshot($staging);

        [$exitCode, $report] = $this->runValidator($staging);

        $this->assertSame(0, $exitCode);
        $this->assertSame('PASS', $report['status']);
        $this->assertGreaterThan(0, $report['inventory']['file_count']);
        $this->assertGreaterThan(0, $report['inventory']['total_size_bytes']);
        $this->assertSame(1, $report['inventory']['migration_count']);
        $this->assertSame(['2026_01_01_000000_create_example_table.php'], $report['inventory']['migrations']);
        $this->assertTrue($report['inventory']['vite']['manifest_valid']);
        $this->assertSame(1, $report['inventory']['vite']['assets_checked']);
        $this->assertSame(0, $report['inventory']['vite']['assets_missing']);
        $this->assertSame($before, $this->snapshot($staging));
    }

    public function test_missing_required_file_fails(): void
    {
        $staging = $this->createValidStagingDirectory();
        unlink($staging.'/artisan');

        [$exitCode, $report] = $this->runValidator($staging);

        $this->assertSame(1, $exitCode);
        $this->assertReportHasError($report, 'missing_required_file', 'artisan');
    }

    public function test_missing_required_directory_fails(): void
    {
        $staging = $this->createValidStagingDirectory();
        rmdir($staging.'/public/images');

        [$exitCode, $report] = $this->runValidator($staging);

        $this->assertSame(1, $exitCode);
        $this->assertReportHasError($report, 'missing_required_directory', 'public/images');
    }

    public function test_unallowlisted_file_fails(): void
    {
        $staging = $this->createValidStagingDirectory();
        $this->writeFile($staging.'/unexpected.txt', 'not part of the release');

        [$exitCode, $report] = $this->runValidator($staging);

        $this->assertSame(1, $exitCode);
        $this->assertReportHasError($report, 'unallowlisted_file', 'unexpected.txt');
    }

    /**
     * @dataProvider prohibitedFileProvider
     */
    public function test_prohibited_release_content_fails(string $relativePath): void
    {
        $staging = $this->createValidStagingDirectory();
        $this->writeFile($staging.'/'.$relativePath, 'prohibited test file');

        [$exitCode, $report] = $this->runValidator($staging);

        $this->assertSame(1, $exitCode, $relativePath);
        $this->assertReportHasPath($report, $relativePath);
    }

    public static function prohibitedFileProvider(): array
    {
        return [
            '.env' => ['.env'],
            'SQLite database' => ['database/database.sqlite'],
            'SQLite WAL' => ['database/database.sqlite-wal'],
            'SQLite SHM' => ['database/database.sqlite-shm'],
            'Vite hot marker' => ['public/hot'],
            'public storage' => ['public/storage/private.txt'],
            'public uploads' => ['public/uploads/client-file.txt'],
            'storage app runtime' => ['storage/app/runtime.txt'],
            'runtime file in empty log skeleton' => ['storage/logs/laravel.log'],
            'node modules' => ['node_modules/package/index.js'],
            'tests' => ['tests/Feature/ReleaseTest.php'],
            'docs' => ['docs/internal.md'],
            'scripts' => ['scripts/build.ps1'],
        ];
    }

    public function test_symlink_fails_where_supported(): void
    {
        $staging = $this->createValidStagingDirectory();
        $target = $staging.'/app/Target.php';
        $link = $staging.'/app/Linked.php';
        $this->writeFile($target, '<?php return true;');

        if (!@symlink($target, $link)) {
            $this->markTestSkipped('Symlink creation is not supported in this environment.');
        }

        [$exitCode, $report] = $this->runValidator($staging);

        $this->assertSame(1, $exitCode);
        $this->assertReportHasError($report, 'link_not_allowed', 'app/Linked.php');
    }

    public function test_invalid_rules_json_exits_with_input_error(): void
    {
        $staging = $this->createValidStagingDirectory();
        $rules = $this->temporaryDirectory('invalid-rules').'/rules.json';
        $this->writeFile($rules, '{not-json');

        [$exitCode, $report] = $this->runValidator($staging, $rules);

        $this->assertSame(2, $exitCode);
        $this->assertSame('ERROR', $report['status']);
    }

    public function test_missing_staging_directory_exits_with_input_error(): void
    {
        $missing = $this->temporaryDirectory('missing-staging').'/does-not-exist';

        [$exitCode, $report] = $this->runValidator($missing);

        $this->assertSame(2, $exitCode);
        $this->assertSame('ERROR', $report['status']);
    }

    public function test_invalid_release_info_json_fails(): void
    {
        $staging = $this->createValidStagingDirectory();
        file_put_contents($staging.'/release-info.json', '{invalid');

        [$exitCode, $report] = $this->runValidator($staging);

        $this->assertSame(1, $exitCode);
        $this->assertReportHasError($report, 'invalid_release_info', 'release-info.json');
    }

    /**
     * @dataProvider releaseInfoMismatchProvider
     */
    public function test_release_info_target_mismatch_fails(string $field, string $value): void
    {
        $staging = $this->createValidStagingDirectory();
        $releaseInfo = json_decode(file_get_contents($staging.'/release-info.json'), true, 512, JSON_THROW_ON_ERROR);
        data_set($releaseInfo, $field, $value);
        file_put_contents(
            $staging.'/release-info.json',
            json_encode($releaseInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );

        [$exitCode, $report] = $this->runValidator($staging);

        $this->assertSame(1, $exitCode);
        $this->assertReportHasError($report, 'release_info_mismatch', $field);
    }

    public static function releaseInfoMismatchProvider(): array
    {
        return [
            'client ID' => ['target.client_id', 'another-client'],
            'version' => ['app.version', '9.9.9'],
            'channel' => ['target.channel', 'beta'],
        ];
    }

    public function test_migration_inventory_mismatch_fails(): void
    {
        $staging = $this->createValidStagingDirectory();
        $releaseInfo = json_decode(file_get_contents($staging.'/release-info.json'), true, 512, JSON_THROW_ON_ERROR);
        $releaseInfo['database']['migration_count'] = 0;
        $releaseInfo['database']['migrations'] = [];
        file_put_contents(
            $staging.'/release-info.json',
            json_encode($releaseInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );

        [$exitCode, $report] = $this->runValidator($staging);

        $this->assertSame(1, $exitCode);
        $this->assertReportHasError($report, 'migration_count_mismatch');
        $this->assertReportHasError($report, 'migration_list_mismatch');
    }

    public function test_missing_vite_asset_fails(): void
    {
        $staging = $this->createValidStagingDirectory();
        unlink($staging.'/public/build/assets/app-123.js');

        [$exitCode, $report] = $this->runValidator($staging);

        $this->assertSame(1, $exitCode);
        $this->assertReportHasError($report, 'missing_vite_asset', 'public/build/assets/app-123.js');
        $this->assertSame(1, $report['inventory']['vite']['assets_missing']);
    }

    public function test_prohibited_secret_pattern_is_reported_without_exposing_value(): void
    {
        $staging = $this->createValidStagingDirectory();
        $secret = 'ghp_'.str_repeat('A', 36);
        $this->writeFile($staging.'/config/leaked.php', "<?php return '{$secret}';");

        [$exitCode, $report, $output] = $this->runValidator($staging);

        $this->assertSame(1, $exitCode);
        $this->assertReportHasError($report, 'prohibited_content', 'config/leaked.php');
        $this->assertStringNotContainsString($secret, $output);
    }

    private function createValidStagingDirectory(): string
    {
        $staging = $this->temporaryDirectory('valid-release');
        $directories = [
            'app',
            'bootstrap/cache',
            'config',
            'database/migrations',
            'public/build/assets',
            'public/images',
            'public/js',
            'resources/views',
            'routes',
            'storage/framework/cache/data',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/logs',
            'vendor',
        ];

        foreach ($directories as $directory) {
            mkdir($staging.'/'.$directory, 0777, true);
        }

        $migration = '2026_01_01_000000_create_example_table.php';
        $files = [
            'artisan' => "#!/usr/bin/env php\n<?php\n",
            'composer.json' => '{"name":"sparkpair/garmentsos-pro"}',
            'composer.lock' => '{"packages":[]}',
            'app/ReleaseFixture.php' => "<?php\nreturn true;\n",
            'bootstrap/app.php' => "<?php\nreturn null;\n",
            'bootstrap/cache/packages.php' => "<?php\nreturn [];\n",
            'bootstrap/cache/services.php' => "<?php\nreturn [];\n",
            'config/app.php' => "<?php\nreturn [];\n",
            'config/client.php' => "<?php\nreturn [];\n",
            'database/migrations/'.$migration => "<?php\nreturn true;\n",
            'public/index.php' => "<?php\n",
            'public/build/manifest.json' => json_encode([
                'resources/js/app.js' => ['file' => 'assets/app-123.js'],
            ], JSON_THROW_ON_ERROR),
            'public/build/assets/app-123.js' => 'console.log("release");',
            'public/js/app.js' => 'console.log("public");',
            'public/manifest.json' => '{"name":"GarmentsOS PRO"}',
            'public/service-worker.js' => 'self.addEventListener("install", () => {});',
            'public/offline.html' => '<!doctype html><title>Offline</title>',
            'resources/views/app.blade.php' => '<main>GarmentsOS PRO</main>',
            'routes/web.php' => "<?php\n",
            'vendor/autoload.php' => "<?php\nreturn true;\n",
        ];

        foreach ($files as $relativePath => $contents) {
            $this->writeFile($staging.'/'.$relativePath, $contents);
        }

        $releaseInfo = [
            'schema_version' => 1,
            'app' => [
                'name' => 'GarmentsOS PRO',
                'version' => '1.2.3',
            ],
            'target' => [
                'client_id' => 'default',
                'client_name' => 'GarmentsOS PRO',
                'channel' => 'stable',
            ],
            'build' => [
                'built_at' => '2026-06-12T00:00:00+00:00',
                'source_commit' => str_repeat('a', 40),
            ],
            'database' => [
                'migrations_included' => true,
                'migration_count' => 1,
                'migrations' => [$migration],
            ],
        ];
        $this->writeFile(
            $staging.'/release-info.json',
            json_encode($releaseInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );

        return $staging;
    }

    private function runValidator(string $staging, ?string $rules = null): array
    {
        $process = new Process([
            PHP_BINARY,
            base_path('scripts/validate-release-inventory.php'),
            '--staging='.$staging,
            '--rules='.($rules ?? base_path('scripts/release-rules.json')),
            '--client=default',
            '--version=1.2.3',
            '--channel=stable',
        ]);
        $process->run();
        $output = $process->getOutput();
        $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        return [$process->getExitCode(), $report, $output];
    }

    private function temporaryDirectory(string $prefix): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.$prefix.'-'.Str::uuid();
        mkdir($path, 0777, true);
        $this->temporaryPaths[] = $path;

        return str_replace('\\', '/', $path);
    }

    private function writeFile(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
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
                'hash' => $entry->isFile() && !$entry->isLink() ? hash_file('sha256', $entry->getPathname()) : null,
            ];
        }

        ksort($snapshot);

        return $snapshot;
    }

    private function assertReportHasError(array $report, string $code, ?string $path = null): void
    {
        foreach ($report['errors'] as $error) {
            if ($error['code'] === $code && ($path === null || ($error['path'] ?? null) === $path)) {
                $this->addToAssertionCount(1);

                return;
            }
        }

        $this->fail("Expected report error {$code}".($path === null ? '' : " at {$path}").'.');
    }

    private function assertReportHasPath(array $report, string $path): void
    {
        foreach ($report['errors'] as $error) {
            if (($error['path'] ?? null) === $path) {
                $this->addToAssertionCount(1);

                return;
            }
        }

        $this->fail("Expected a validation error for {$path}.");
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
