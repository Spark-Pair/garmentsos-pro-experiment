<?php

namespace Tests\Feature;

use FilesystemIterator;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class StageReleaseScriptTest extends TestCase
{
    private array $temporaryPaths = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->temporaryPaths) as $path) {
            $this->removePath($path);
        }

        parent::tearDown();
    }

    public function test_valid_fake_source_stages_and_validates_without_modifying_source(): void
    {
        $source = $this->createValidSource();
        $staging = $this->newPath('staging');
        $before = $this->snapshot($source);

        [$exitCode, $report] = $this->runBuilder($source, $staging);

        $this->assertSame(0, $exitCode, json_encode($report));
        $this->assertSame('PASS', $report['status']);
        $this->assertSame('PASS', $report['inventory']['vite']['manifest_valid'] ? 'PASS' : 'FAIL');
        $this->assertFileExists($staging.'/artisan');
        $this->assertFileExists($staging.'/vendor/autoload.php');
        $this->assertFileExists($staging.'/public/build/assets/app-123.js');
        $this->assertSame($before, $this->snapshot($source));
    }

    /**
     * @dataProvider overlapProvider
     */
    public function test_overlapping_source_and_staging_paths_are_refused(string $case): void
    {
        $container = $this->temporaryDirectory('overlap');

        if ($case === 'identical') {
            $source = $this->createValidSource($container.'/source');
            $staging = $source;
        } elseif ($case === 'staging-inside-source') {
            $source = $this->createValidSource($container.'/source');
            $staging = $source.'/build/staging';
        } else {
            $staging = $container.'/outer';
            $source = $this->createValidSource($staging.'/source');
        }

        [$exitCode, $report] = $this->runBuilder($source, $staging);

        $this->assertSame(2, $exitCode, json_encode($report));
        $this->assertSame('ERROR', $report['status']);
        $this->assertStringContainsString('overlap', $report['message']);
    }

    public static function overlapProvider(): array
    {
        return [
            'identical paths' => ['identical'],
            'staging inside source' => ['staging-inside-source'],
            'source inside staging' => ['source-inside-staging'],
        ];
    }

    public function test_non_empty_staging_is_refused_without_deleting_content(): void
    {
        $source = $this->createValidSource();
        $staging = $this->temporaryDirectory('non-empty-staging');
        $marker = $staging.'/keep.txt';
        file_put_contents($marker, 'keep me');

        [$exitCode, $report] = $this->runBuilder($source, $staging);

        $this->assertSame(2, $exitCode);
        $this->assertSame('keep me', file_get_contents($marker));
        $this->assertStringContainsString('empty', $report['message']);
    }

    public function test_missing_required_input_fails(): void
    {
        $source = $this->createValidSource();
        unlink($source.'/vendor/autoload.php');
        $staging = $this->newPath('missing-required-staging');

        [$exitCode, $report] = $this->runBuilder($source, $staging);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('vendor/autoload.php', $report['message']);
        $this->assertDirectoryDoesNotExist($staging);
    }

    public function test_excluded_and_prohibited_content_is_not_copied(): void
    {
        $source = $this->createValidSource();
        $staging = $this->newPath('excluded-staging');
        $prohibited = [
            '.env',
            'database/database.sqlite',
            'database/database.sqlite-wal',
            'database/database.sqlite-shm',
            'storage/app/private.txt',
            'public/uploads/image.jpg',
            'public/hot',
            'tests/Feature/FakeTest.php',
            'docs/internal.md',
            'scripts/private.ps1',
            'node_modules/package/index.js',
        ];

        foreach ($prohibited as $path) {
            $this->writeFile($source.'/'.$path, 'excluded');
        }

        [$exitCode, $report] = $this->runBuilder($source, $staging);

        $this->assertSame(0, $exitCode, json_encode($report));

        foreach ($prohibited as $path) {
            $this->assertFileDoesNotExist($staging.'/'.$path, $path);
        }
    }

    public function test_declared_skeleton_directories_are_created_empty(): void
    {
        $source = $this->createValidSource();
        $staging = $this->newPath('skeleton-staging');

        [$exitCode] = $this->runBuilder($source, $staging);

        $this->assertSame(0, $exitCode);

        foreach ([
            'storage/framework/cache/data',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/logs',
        ] as $directory) {
            $this->assertDirectoryExists($staging.'/'.$directory);
            $this->assertSame([], array_values(array_diff(scandir($staging.'/'.$directory), ['.', '..'])));
        }
    }

    public function test_release_info_contains_requested_metadata_and_sorted_migrations(): void
    {
        $source = $this->createValidSource();
        $this->writeFile($source.'/database/migrations/2025_01_01_000000_first.php', "<?php\n");
        $staging = $this->newPath('metadata-staging');

        [$exitCode] = $this->runBuilder($source, $staging);
        $releaseInfo = json_decode(file_get_contents($staging.'/release-info.json'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('default', $releaseInfo['target']['client_id']);
        $this->assertSame('GarmentsOS PRO', $releaseInfo['target']['client_name']);
        $this->assertSame('1.2.3', $releaseInfo['app']['version']);
        $this->assertSame('stable', $releaseInfo['target']['channel']);
        $this->assertSame(str_repeat('a', 40), $releaseInfo['build']['source_commit']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $releaseInfo['build']['built_at']);
        $this->assertSame([
            '2025_01_01_000000_first.php',
            '2026_01_01_000000_create_example_table.php',
        ], $releaseInfo['database']['migrations']);
        $this->assertSame(2, $releaseInfo['database']['migration_count']);
    }

    public function test_missing_vite_asset_causes_validator_failure_and_leaves_staging(): void
    {
        $source = $this->createValidSource();
        unlink($source.'/public/build/assets/app-123.js');
        $staging = $this->newPath('vite-failure-staging');

        [$exitCode, $report] = $this->runBuilder($source, $staging);

        $this->assertSame(1, $exitCode);
        $this->assertSame('FAIL', $report['status']);
        $this->assertDirectoryExists($staging);
        $this->assertSame('FAIL', $report['validator']['status']);
        $this->assertContains(
            'missing_vite_asset',
            array_column($report['validator']['errors'], 'code')
        );
    }

    public function test_vendor_autoload_is_required_before_staging_creation(): void
    {
        $source = $this->createValidSource();
        $this->removePath($source.'/vendor');
        $staging = $this->newPath('vendor-failure-staging');

        [$exitCode] = $this->runBuilder($source, $staging);

        $this->assertSame(1, $exitCode);
        $this->assertDirectoryDoesNotExist($staging);
    }

    public function test_secret_content_fails_before_affected_file_is_copied(): void
    {
        $source = $this->createValidSource();
        $secret = 'ghp_'.str_repeat('A', 36);
        $this->writeFile($source.'/config/leaked.php', "<?php return '{$secret}';");
        $staging = $this->newPath('secret-staging');

        [$exitCode, $report, $output] = $this->runBuilder($source, $staging);

        $this->assertSame(1, $exitCode);
        $this->assertFileDoesNotExist($staging.'/config/leaked.php');
        $this->assertStringContainsString('secret pattern', $report['message']);
        $this->assertStringNotContainsString($secret, $output);
    }

    public function test_included_symlink_fails_where_supported(): void
    {
        $source = $this->createValidSource();
        $target = $source.'/app/Target.php';
        $link = $source.'/app/Linked.php';
        $this->writeFile($target, "<?php\n");

        if (!@symlink($target, $link)) {
            $this->markTestSkipped('Symlink creation is not supported in this environment.');
        }

        $staging = $this->newPath('symlink-staging');
        [$exitCode, $report] = $this->runBuilder($source, $staging);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('link', strtolower($report['message']));
    }

    public function test_invalid_rules_fail_before_staging_creation(): void
    {
        $source = $this->createValidSource();
        $staging = $this->newPath('invalid-rules-staging');
        $rules = $this->newPath('invalid-rules.json');
        file_put_contents($rules, '{invalid');

        [$exitCode, $report] = $this->runBuilder($source, $staging, $rules);

        $this->assertSame(2, $exitCode);
        $this->assertSame('ERROR', $report['status']);
        $this->assertDirectoryDoesNotExist($staging);
    }

    private function createValidSource(?string $source = null): string
    {
        $source ??= $this->temporaryDirectory('release-source');

        if (!is_dir($source)) {
            mkdir($source, 0777, true);
            $this->temporaryPaths[] = $source;
        }

        foreach ([
            'app',
            'bootstrap/cache',
            'config',
            'database/migrations',
            'public/build/assets',
            'public/images',
            'public/js',
            'resources/views',
            'routes',
            'vendor',
        ] as $directory) {
            mkdir($source.'/'.$directory, 0777, true);
        }

        $files = [
            'artisan' => "#!/usr/bin/env php\n<?php\n",
            'composer.json' => '{"name":"sparkpair/garmentsos-pro"}',
            'composer.lock' => '{"packages":[]}',
            'app/Fixture.php' => "<?php\nreturn true;\n",
            'bootstrap/app.php' => "<?php\nreturn null;\n",
            'bootstrap/cache/packages.php' => "<?php\nreturn [];\n",
            'bootstrap/cache/services.php' => "<?php\nreturn [];\n",
            'config/app.php' => "<?php\nreturn [];\n",
            'config/client.php' => "<?php\nreturn [];\n",
            'database/migrations/2026_01_01_000000_create_example_table.php' => "<?php\n",
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
            $this->writeFile($source.'/'.$relativePath, $contents);
        }

        return str_replace('\\', '/', $source);
    }

    private function runBuilder(string $source, string $staging, ?string $rules = null): array
    {
        $process = new Process([
            PHP_BINARY,
            base_path('scripts/stage-release.php'),
            '--source='.$source,
            '--staging='.$staging,
            '--rules='.($rules ?? base_path('scripts/release-rules.json')),
            '--client=default',
            '--client-name=GarmentsOS PRO',
            '--version=1.2.3',
            '--channel=stable',
            '--commit='.str_repeat('a', 40),
        ]);
        $process->run();
        $output = $process->getOutput();
        $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        return [$process->getExitCode(), $report, $output];
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
                'hash' => $entry->isFile() && !$entry->isLink() ? hash_file('sha256', $entry->getPathname()) : null,
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
