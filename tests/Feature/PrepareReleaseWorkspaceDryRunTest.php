<?php

namespace Tests\Feature;

use FilesystemIterator;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PrepareReleaseWorkspaceDryRunTest extends TestCase
{
    private array $temporaryPaths = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->temporaryPaths) as $path) {
            $this->removePath($path);
        }

        parent::tearDown();
    }

    public function test_valid_absolute_temp_paths_pass_without_modification_or_creation(): void
    {
        $source = $this->createValidSource();
        $workspace = $this->newPath('dry-workspace');
        $staging = $this->newPath('dry-staging');
        $before = $this->snapshot($source);

        [$exitCode, $report] = $this->runDryRun($source, $workspace, $staging);

        $this->assertSame(0, $exitCode, json_encode($report));
        $this->assertSame('SAFE', $report['status']);
        $this->assertTrue($report['dry_run']);
        $this->assertFalse($report['plan']['execution_enabled']);
        $this->assertDirectoryDoesNotExist($workspace);
        $this->assertDirectoryDoesNotExist($staging);
        $this->assertSame($before, $this->snapshot($source));
    }

    /**
     * @dataProvider overlapProvider
     */
    public function test_overlapping_paths_fail(string $case): void
    {
        $container = $this->temporaryDirectory('dry-overlap');

        if ($case === 'identical') {
            $source = $this->createValidSource($container.'/source');
            $workspace = $source;
            $staging = $container.'/staging';
        } elseif ($case === 'workspace-inside-source') {
            $source = $this->createValidSource($container.'/source');
            $workspace = $source.'/workspace';
            $staging = $container.'/staging';
        } elseif ($case === 'staging-inside-source') {
            $source = $this->createValidSource($container.'/source');
            $workspace = $container.'/workspace';
            $staging = $source.'/staging';
        } elseif ($case === 'source-inside-workspace') {
            $workspace = $container.'/workspace';
            $source = $this->createValidSource($workspace.'/source');
            $staging = $container.'/staging';
        } else {
            $staging = $container.'/staging';
            $source = $this->createValidSource($staging.'/source');
            $workspace = $container.'/workspace';
        }

        [$exitCode, $report] = $this->runDryRun($source, $workspace, $staging);

        $this->assertSame(2, $exitCode, json_encode($report));
        $this->assertSame('ERROR', $report['status']);
        $this->assertStringContainsString('overlap', $report['errors'][0]['message']);
    }

    public static function overlapProvider(): array
    {
        return [
            'identical source and workspace' => ['identical'],
            'workspace inside source' => ['workspace-inside-source'],
            'staging inside source' => ['staging-inside-source'],
            'source inside workspace' => ['source-inside-workspace'],
            'source inside staging' => ['source-inside-staging'],
        ];
    }

    public function test_non_empty_workspace_is_refused_without_deletion(): void
    {
        $source = $this->createValidSource();
        $workspace = $this->temporaryDirectory('non-empty-workspace');
        $marker = $workspace.'/keep.txt';
        file_put_contents($marker, 'keep');
        $staging = $this->newPath('staging');

        [$exitCode] = $this->runDryRun($source, $workspace, $staging);

        $this->assertSame(2, $exitCode);
        $this->assertSame('keep', file_get_contents($marker));
    }

    public function test_non_empty_staging_is_refused_without_deletion(): void
    {
        $source = $this->createValidSource();
        $workspace = $this->newPath('workspace');
        $staging = $this->temporaryDirectory('non-empty-staging');
        $marker = $staging.'/keep.txt';
        file_put_contents($marker, 'keep');

        [$exitCode] = $this->runDryRun($source, $workspace, $staging);

        $this->assertSame(2, $exitCode);
        $this->assertSame('keep', file_get_contents($marker));
    }

    public function test_missing_rules_file_exits_two(): void
    {
        $source = $this->createValidSource();

        [$exitCode, $report] = $this->runDryRun(
            $source,
            $this->newPath('workspace'),
            $this->newPath('staging'),
            $this->newPath('missing-rules.json')
        );

        $this->assertSame(2, $exitCode);
        $this->assertSame('ERROR', $report['status']);
    }

    public function test_invalid_rules_json_exits_two(): void
    {
        $source = $this->createValidSource();
        $rules = $this->newPath('invalid-rules.json');
        file_put_contents($rules, '{invalid');

        [$exitCode, $report] = $this->runDryRun(
            $source,
            $this->newPath('workspace'),
            $this->newPath('staging'),
            $rules
        );

        $this->assertSame(2, $exitCode);
        $this->assertSame('ERROR', $report['status']);
    }

    /**
     * @dataProvider missingInputProvider
     */
    public function test_missing_lockfile_or_frontend_input_is_blocking(string $relativePath): void
    {
        $source = $this->createValidSource();
        unlink($source.'/'.$relativePath);

        [$exitCode, $report] = $this->runDryRun(
            $source,
            $this->newPath('workspace'),
            $this->newPath('staging')
        );

        $this->assertSame(1, $exitCode);
        $this->assertFinding($report, 'missing_required_input', $relativePath);
    }

    public static function missingInputProvider(): array
    {
        return [
            'composer lock' => ['composer.lock'],
            'package lock' => ['package-lock.json'],
            'Vite config' => ['vite.config.js'],
            'CSS input' => ['resources/css/app.css'],
            'JavaScript input' => ['resources/js/app.js'],
        ];
    }

    public function test_planned_commands_use_safe_locked_build_commands_only(): void
    {
        $source = $this->createValidSource();

        [$exitCode, $report, $output] = $this->runDryRun(
            $source,
            $this->newPath('workspace'),
            $this->newPath('staging')
        );

        $this->assertSame(0, $exitCode);
        $composer = implode("\n", $report['plan']['composer_commands']);
        $allCommands = strtolower($composer."\n".implode("\n", $report['plan']['npm_commands']));

        $this->assertStringContainsString('--no-dev', $composer);
        $this->assertStringContainsString('--no-scripts', $composer);
        $this->assertStringContainsString('npm ci', $allCommands);
        $this->assertStringContainsString('npm run build', $allCommands);

        foreach ([
            'composer update',
            'npm install',
            'artisan migrate',
            'artisan serve',
            'queue:',
            'schedule:',
            'backup',
            'restore',
            'storage:link',
            'updater',
            'installer',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $allCommands);
        }

        $this->assertStringNotContainsString('base64:', $output);
    }

    public function test_planned_environment_uses_external_isolated_build_runtime(): void
    {
        $source = $this->createValidSource();
        $workspace = $this->newPath('planned-workspace');
        $staging = $this->newPath('planned-staging');

        [$exitCode, $report] = $this->runDryRun($source, $workspace, $staging);

        $this->assertSame(0, $exitCode);
        $database = str_replace('\\', '/', $report['plan']['environment']['DB_DATABASE']);
        $this->assertStringEndsWith('-build-runtime/build.sqlite', $database);
        $this->assertFalse(str_starts_with(strtolower($database), strtolower($source.'/')));
        $this->assertFalse(str_starts_with(strtolower($database), strtolower($staging.'/')));
        $this->assertSame('<ephemeral-build-key>', $report['plan']['environment']['APP_KEY']);
    }

    public function test_preparation_allowlist_and_exclusions_keep_runtime_and_development_content_out(): void
    {
        $source = $this->createValidSource();

        [$exitCode, $report] = $this->runDryRun(
            $source,
            $this->newPath('workspace'),
            $this->newPath('staging')
        );

        $this->assertSame(0, $exitCode);
        $allowlist = implode("\n", $report['plan']['preparation_allowlist']);
        $exclusions = strtolower(implode("\n", $report['plan']['exclusions']));
        $this->assertStringContainsString('app/**', $allowlist);
        $this->assertStringContainsString('composer.lock', $allowlist);
        $this->assertStringContainsString('package-lock.json', $allowlist);

        foreach ([
            '.env',
            'database/*.sqlite',
            'storage/app',
            'uploads',
            'logs',
            'backups',
            'public/storage',
            'public/uploads',
            'public/hot',
            'node_modules',
            'tests',
            'docs',
            'scripts',
        ] as $excluded) {
            $this->assertStringContainsString($excluded, $exclusions);
        }
    }

    public function test_expected_development_tree_content_is_advisory(): void
    {
        $source = $this->createValidSource();
        foreach (['tests/FakeTest.php', 'docs/internal.md', 'scripts/tool.ps1', 'node_modules/pkg/index.js'] as $path) {
            $this->writeFile($source.'/'.$path, 'development only');
        }

        [$exitCode, $report] = $this->runDryRun(
            $source,
            $this->newPath('workspace'),
            $this->newPath('staging')
        );

        $this->assertSame(0, $exitCode, json_encode($report));
        $this->assertNotEmpty($report['findings']['advisory']);
        $this->assertSame([], $report['findings']['blocking']);
    }

    public function test_realistic_lockfile_v3_content_passes_php_json_validation(): void
    {
        $source = $this->createValidSource();
        $lock = [
            'name' => 'garmentsos-pro',
            'lockfileVersion' => 3,
            'requires' => true,
            'packages' => [
                '' => ['name' => 'garmentsos-pro', 'dependencies' => ['Example' => '1.0.0', 'example' => '1.0.0']],
                'node_modules/example' => ['version' => '1.0.0', 'resolved' => 'https://registry.npmjs.org/example/-/example-1.0.0.tgz'],
            ],
        ];
        $this->writeFile(
            $source.'/package-lock.json',
            json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );

        [$exitCode, $report] = $this->runDryRun(
            $source,
            $this->newPath('workspace'),
            $this->newPath('staging')
        );

        $this->assertSame(0, $exitCode, json_encode($report));
        $this->assertSame([], $report['findings']['blocking']);
    }

    public function test_sanctum_style_access_token_php_names_do_not_block(): void
    {
        $source = $this->createValidSource();
        $this->writeFile(
            $source.'/database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php',
            "<?php\n"
        );
        $this->writeFile($source.'/app/PersonalAccessToken.php', "<?php\n");

        [$exitCode, $report] = $this->runDryRun(
            $source,
            $this->newPath('workspace'),
            $this->newPath('staging')
        );

        $this->assertSame(0, $exitCode, json_encode($report));
        $this->assertSame([], $report['findings']['blocking']);
    }

    /**
     * @dataProvider advisoryEnvironmentFileProvider
     */
    public function test_environment_file_in_source_is_safe_and_reported_as_excluded(string $relativePath): void
    {
        $source = $this->createValidSource();
        $this->writeFile($source.'/'.$relativePath, 'APP_KEY=secret-value-that-must-not-be-printed');

        [$exitCode, $report, $output] = $this->runDryRun(
            $source,
            $this->newPath('workspace'),
            $this->newPath('staging')
        );

        $this->assertSame(0, $exitCode, json_encode($report));
        $this->assertSame('SAFE', $report['status']);
        $this->assertSame([], $report['findings']['blocking']);
        $finding = $this->findAdvisoryFinding($report, 'environment_file_excluded', $relativePath);
        $this->assertSame(
            '.env file exists in source and will be excluded from workspace/release.',
            $finding['message']
        );
        $this->assertStringContainsString('.env', implode("\n", $report['plan']['exclusions']));
        $this->assertStringNotContainsString('secret-value-that-must-not-be-printed', $output);
    }

    public static function advisoryEnvironmentFileProvider(): array
    {
        return [
            '.env' => ['.env'],
            '.env.example' => ['.env.example'],
        ];
    }

    /**
     * @dataProvider riskyCredentialFileProvider
     */
    public function test_risky_credential_filename_is_blocking(string $relativePath): void
    {
        $source = $this->createValidSource();
        $this->writeFile($source.'/'.$relativePath, '{}');

        [$exitCode, $report] = $this->runDryRun(
            $source,
            $this->newPath('workspace'),
            $this->newPath('staging')
        );

        $this->assertSame(1, $exitCode, $relativePath);
        $this->assertFinding($report, 'secret_file', $relativePath);
    }

    public static function riskyCredentialFileProvider(): array
    {
        return [
            'auth JSON' => ['auth.json'],
            'RSA key' => ['id_rsa'],
            'DSA key' => ['id_dsa'],
            'private PEM' => ['config/private.pem'],
            'private key' => ['config/client.key'],
            'service account JSON' => ['config/service-account-prod.json'],
            'token JSON' => ['config/access-token.json'],
            'secret JSON' => ['config/client-secret.json'],
        ];
    }

    public function test_invalid_package_lock_json_is_blocking_when_php_is_available(): void
    {
        $source = $this->createValidSource();
        $this->writeFile($source.'/package-lock.json', '{invalid');

        [$exitCode, $report] = $this->runDryRun(
            $source,
            $this->newPath('workspace'),
            $this->newPath('staging')
        );

        $this->assertSame(1, $exitCode);
        $this->assertFinding($report, 'malformed_metadata', 'package-lock.json');
    }

    public function test_secret_content_is_blocking_without_exposing_value(): void
    {
        $source = $this->createValidSource();
        $secret = 'ghp_'.str_repeat('A', 36);
        $this->writeFile($source.'/config/leaked.php', "<?php return '{$secret}';");

        [$exitCode, $report, $output] = $this->runDryRun(
            $source,
            $this->newPath('workspace'),
            $this->newPath('staging')
        );

        $this->assertSame(1, $exitCode);
        $this->assertFinding($report, 'secret_content', 'config/leaked.php');
        $this->assertStringNotContainsString($secret, $output);
    }

    public function test_unsafe_link_is_blocking_where_supported(): void
    {
        $source = $this->createValidSource();
        $target = $source.'/config/target.php';
        $link = $source.'/config/linked.php';
        $this->writeFile($target, "<?php return [];\n");

        if (!@symlink($target, $link)) {
            $this->markTestSkipped('Symlink creation is not supported in this environment.');
        }

        [$exitCode, $report] = $this->runDryRun(
            $source,
            $this->newPath('workspace'),
            $this->newPath('staging')
        );

        $this->assertSame(1, $exitCode);
        $this->assertFinding($report, 'unsafe_link', 'config/linked.php');
    }

    private function createValidSource(?string $source = null): string
    {
        $source ??= $this->temporaryDirectory('dry-source');

        if (!is_dir($source)) {
            mkdir($source, 0777, true);
            $this->temporaryPaths[] = $source;
        }

        foreach (['app', 'bootstrap', 'config', 'resources/css', 'resources/js', 'routes'] as $directory) {
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
            'resources/css/app.css' => '@import "tailwindcss";',
            'resources/js/app.js' => 'console.log("app");',
            'config/app.php' => "<?php return [];\n",
            'routes/web.php' => "<?php\n",
        ];

        foreach ($files as $relativePath => $contents) {
            $this->writeFile($source.'/'.$relativePath, $contents);
        }

        return str_replace('\\', '/', $source);
    }

    private function runDryRun(
        string $source,
        string $workspace,
        string $staging,
        ?string $rules = null
    ): array {
        $process = new Process([
            'powershell.exe',
            '-NoProfile',
            '-NonInteractive',
            '-ExecutionPolicy',
            'Bypass',
            '-File',
            base_path('scripts/prepare-release-workspace-dry-run.ps1'),
            '-Source',
            $source,
            '-Workspace',
            $workspace,
            '-Staging',
            $staging,
            '-Rules',
            $rules ?? base_path('scripts/release-rules.json'),
            '-Client',
            'default',
            '-ClientName',
            'GarmentsOS PRO',
            '-Version',
            '1.2.3',
            '-Channel',
            'stable',
            '-Commit',
            str_repeat('a', 40),
        ]);
        $process->run();
        $output = trim($process->getOutput());
        $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        return [$process->getExitCode(), $report, $output];
    }

    private function assertFinding(array $report, string $code, string $path): void
    {
        foreach ($report['findings']['blocking'] ?? [] as $finding) {
            if ($finding['code'] === $code && ($finding['path'] ?? null) === $path) {
                $this->addToAssertionCount(1);
                return;
            }
        }

        $this->fail("Expected blocking finding {$code} at {$path}.");
    }

    private function findAdvisoryFinding(array $report, string $code, string $path): array
    {
        foreach ($report['findings']['advisory'] ?? [] as $finding) {
            if ($finding['code'] === $code && ($finding['path'] ?? null) === $path) {
                $this->addToAssertionCount(1);
                return $finding;
            }
        }

        $this->fail("Expected advisory finding {$code} at {$path}.");
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
