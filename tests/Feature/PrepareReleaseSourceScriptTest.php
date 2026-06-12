<?php

namespace Tests\Feature;

use FilesystemIterator;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PrepareReleaseSourceScriptTest extends TestCase
{
    private array $temporaryPaths = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->temporaryPaths) as $path) {
            $this->removePath($path);
        }

        parent::tearDown();
    }

    public function test_valid_source_copies_only_preparation_allowlist_and_preserves_source(): void
    {
        $source = $this->createValidSource();
        $workspace = $this->newPath('prepared-workspace');
        $before = $this->snapshot($source);

        [$exitCode, $report] = $this->runHelper($source, $workspace);

        $this->assertSame(0, $exitCode, json_encode($report));
        $this->assertSame('PASS', $report['status']);
        $this->assertFileExists($workspace.'/app/Fixture.php');
        $this->assertFileExists($workspace.'/resources/views/app.blade.php');
        $this->assertFileExists($workspace.'/public/images/logo.txt');
        $this->assertFileExists($workspace.'/public/js/app.js');
        $this->assertFileDoesNotExist($workspace.'/public/tailwind.js');
        $this->assertSame($before, $this->snapshot($source));
    }

    public function test_workspace_is_created_when_absent(): void
    {
        $source = $this->createValidSource();
        $workspace = $this->newPath('absent-workspace');

        [$exitCode] = $this->runHelper($source, $workspace);

        $this->assertSame(0, $exitCode);
        $this->assertDirectoryExists($workspace);
    }

    public function test_empty_existing_workspace_is_allowed(): void
    {
        $source = $this->createValidSource();
        $workspace = $this->temporaryDirectory('empty-workspace');

        [$exitCode, $report] = $this->runHelper($source, $workspace);

        $this->assertSame(0, $exitCode, json_encode($report));
        $this->assertFileExists($workspace.'/composer.lock');
    }

    public function test_non_empty_workspace_fails_without_deletion(): void
    {
        $source = $this->createValidSource();
        $workspace = $this->temporaryDirectory('non-empty-workspace');
        $marker = $workspace.'/keep.txt';
        file_put_contents($marker, 'keep');

        [$exitCode, $report] = $this->runHelper($source, $workspace);

        $this->assertSame(2, $exitCode);
        $this->assertSame('keep', file_get_contents($marker));
        $this->assertSame('ERROR', $report['status']);
    }

    /**
     * @dataProvider overlapProvider
     */
    public function test_source_and_workspace_overlap_is_refused(string $case): void
    {
        $container = $this->temporaryDirectory('copy-overlap');

        if ($case === 'identical') {
            $source = $this->createValidSource($container.'/source');
            $workspace = $source;
        } elseif ($case === 'workspace-inside-source') {
            $source = $this->createValidSource($container.'/source');
            $workspace = $source.'/workspace';
        } else {
            $workspace = $container.'/workspace';
            $source = $this->createValidSource($workspace.'/source');
        }

        [$exitCode, $report] = $this->runHelper($source, $workspace);

        $this->assertSame(2, $exitCode, json_encode($report));
        $this->assertStringContainsString('overlap', $report['errors'][0]['message']);
    }

    public static function overlapProvider(): array
    {
        return [
            'identical' => ['identical'],
            'workspace inside source' => ['workspace-inside-source'],
            'source inside workspace' => ['source-inside-workspace'],
        ];
    }

    public function test_missing_source_exits_two(): void
    {
        [$exitCode, $report] = $this->runHelper(
            $this->newPath('missing-source'),
            $this->newPath('workspace')
        );

        $this->assertSame(2, $exitCode);
        $this->assertSame('ERROR', $report['status']);
    }

    public function test_invalid_rules_json_exits_two(): void
    {
        $source = $this->createValidSource();
        $rules = $this->newPath('invalid-rules.json');
        file_put_contents($rules, '{invalid');

        [$exitCode, $report] = $this->runHelper(
            $source,
            $this->newPath('workspace'),
            $rules
        );

        $this->assertSame(2, $exitCode);
        $this->assertSame('ERROR', $report['status']);
    }

    /**
     * @dataProvider prohibitedPathProvider
     */
    public function test_prohibited_paths_are_not_copied(string $relativePath): void
    {
        $source = $this->createValidSource();
        $workspace = $this->newPath('excluded-workspace');
        $this->writeFile($source.'/'.$relativePath, 'excluded');

        [$exitCode, $report] = $this->runHelper($source, $workspace);

        $this->assertSame(0, $exitCode, json_encode($report));
        $this->assertFileDoesNotExist($workspace.'/'.$relativePath);
        $this->assertGreaterThan(0, $report['skipped_excluded_count']);
    }

    public static function prohibitedPathProvider(): array
    {
        return [
            '.env' => ['.env'],
            'SQLite' => ['database/database.sqlite'],
            'SQLite WAL' => ['database/database.sqlite-wal'],
            'SQLite SHM' => ['database/database.sqlite-shm'],
            'storage runtime' => ['storage/app/private.txt'],
            'uploads' => ['uploads/image.jpg'],
            'logs' => ['logs/app.log'],
            'backups' => ['backups/manual.sqlite'],
            'public storage' => ['public/storage/file.txt'],
            'public uploads' => ['public/uploads/image.jpg'],
            'public hot' => ['public/hot'],
            'vendor' => ['vendor/autoload.php'],
            'bootstrap packages cache' => ['bootstrap/cache/packages.php'],
            'bootstrap services cache' => ['bootstrap/cache/services.php'],
            'public build' => ['public/build/manifest.json'],
            'node modules' => ['node_modules/vite/index.js'],
            'tests' => ['tests/Feature/FakeTest.php'],
            'docs' => ['docs/internal.md'],
            'scripts' => ['scripts/private.ps1'],
        ];
    }

    public function test_optional_config_files_are_copied_only_when_present(): void
    {
        $source = $this->createValidSource();
        $workspace = $this->newPath('optional-workspace');
        $this->writeFile($source.'/postcss.config.js', 'export default {};');

        [$exitCode] = $this->runHelper($source, $workspace);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($workspace.'/postcss.config.js');
        $this->assertFileDoesNotExist($workspace.'/tailwind.config.js');
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

        $workspace = $this->newPath('link-workspace');
        [$exitCode, $report] = $this->runHelper($source, $workspace);

        $this->assertSame(1, $exitCode);
        $this->assertFinding($report, 'included_link', 'app/Linked.php');
        $this->assertDirectoryDoesNotExist($workspace);
    }

    public function test_secret_content_in_included_file_fails_without_exposure(): void
    {
        $source = $this->createValidSource();
        $secret = 'ghp_'.str_repeat('A', 36);
        $this->writeFile($source.'/config/leaked.php', "<?php return '{$secret}';");
        $workspace = $this->newPath('secret-workspace');

        [$exitCode, $report, $output] = $this->runHelper($source, $workspace);

        $this->assertSame(1, $exitCode);
        $this->assertFinding($report, 'prohibited_secret_content', 'config/leaked.php');
        $this->assertStringNotContainsString($secret, $output);
        $this->assertDirectoryDoesNotExist($workspace);
    }

    public function test_json_report_contains_copy_counts_and_total_bytes(): void
    {
        $source = $this->createValidSource();

        [$exitCode, $report] = $this->runHelper($source, $this->newPath('report-workspace'));

        $this->assertSame(0, $exitCode);
        $this->assertGreaterThan(0, $report['copied_file_count']);
        $this->assertGreaterThan(0, $report['copied_total_bytes']);
        $this->assertIsInt($report['skipped_excluded_count']);
        $this->assertSame([], $report['errors']);
    }

    private function createValidSource(?string $source = null): string
    {
        $source ??= $this->temporaryDirectory('prepare-source');

        if (!is_dir($source)) {
            mkdir($source, 0777, true);
            $this->temporaryPaths[] = $source;
        }

        foreach ([
            'app',
            'bootstrap',
            'config',
            'database/migrations',
            'resources/css',
            'resources/js',
            'resources/views',
            'routes',
            'public/images',
            'public/js',
        ] as $directory) {
            mkdir($source.'/'.$directory, 0777, true);
        }

        $files = [
            'artisan' => "#!/usr/bin/env php\n<?php\n",
            'bootstrap/app.php' => "<?php return null;\n",
            'composer.json' => '{"require":{"laravel/framework":"^10.10"}}',
            'composer.lock' => '{"content-hash":"abc","packages":[],"packages-dev":[]}',
            'package.json' => '{"private":true,"scripts":{"build":"vite build"}}',
            'package-lock.json' => '{"lockfileVersion":3,"packages":{}}',
            'vite.config.js' => "export default {};\n",
            'app/Fixture.php' => "<?php return true;\n",
            'config/app.php' => "<?php return [];\n",
            'database/migrations/2026_01_01_000000_example.php' => "<?php\n",
            'resources/css/app.css' => '@import "tailwindcss";',
            'resources/js/app.js' => 'console.log("app");',
            'resources/views/app.blade.php' => '<main>App</main>',
            'routes/web.php' => "<?php\n",
            'public/.htaccess' => 'RewriteEngine On',
            'public/index.php' => "<?php\n",
            'public/favicon.ico' => 'icon',
            'public/manifest.json' => '{"name":"App"}',
            'public/service-worker.js' => 'self.addEventListener("install", () => {});',
            'public/offline.html' => '<title>Offline</title>',
            'public/robots.txt' => "User-agent: *\n",
            'public/jquery.js' => 'window.jQuery = {};',
            'public/calibri-regular.ttf' => 'font',
            'public/images/logo.txt' => 'logo',
            'public/js/app.js' => 'console.log("public");',
            'public/tailwind.js' => 'not allowlisted',
        ];

        foreach ($files as $relativePath => $contents) {
            $this->writeFile($source.'/'.$relativePath, $contents);
        }

        return str_replace('\\', '/', $source);
    }

    private function runHelper(string $source, string $workspace, ?string $rules = null): array
    {
        $process = new Process([
            PHP_BINARY,
            base_path('scripts/prepare-release-source.php'),
            '--source='.$source,
            '--workspace='.$workspace,
            '--rules='.($rules ?? base_path('scripts/release-rules.json')),
        ]);
        $process->run();
        $output = trim($process->getOutput());
        $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        return [$process->getExitCode(), $report, $output];
    }

    private function assertFinding(array $report, string $code, string $path): void
    {
        foreach ($report['errors'] ?? [] as $error) {
            if ($error['code'] === $code && ($error['path'] ?? null) === $path) {
                $this->addToAssertionCount(1);
                return;
            }
        }

        $this->fail("Expected error {$code} at {$path}.");
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
