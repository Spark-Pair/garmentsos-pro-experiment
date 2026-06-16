<?php

namespace Tests\Feature;

use FilesystemIterator;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PrepareReleaseWorkspaceScriptTest extends TestCase
{
    private static ?string $shimDirectory = null;
    private static ?string $shimLog = null;
    private array $temporaryPaths = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $directory = str_replace('\\', '/', sys_get_temp_dir().'/release-shims-'.Str::uuid());
        mkdir($directory, 0777, true);
        self::$shimDirectory = $directory;
        self::$shimLog = $directory.'/commands.jsonl';
        $source = $directory.'/shim.cs';
        file_put_contents($source, self::shimSource());
        $compiler = 'C:\\Windows\\Microsoft.NET\\Framework64\\v4.0.30319\\csc.exe';
        $baseExecutable = $directory.'/shim.exe';
        $process = new Process([
            $compiler,
            '/nologo',
            '/out:'.str_replace('/', '\\', $baseExecutable),
            str_replace('/', '\\', $source),
        ]);
        $process->mustRun();
        copy($baseExecutable, $directory.'/php.exe');
        copy($baseExecutable, $directory.'/composer.exe');
        copy($baseExecutable, $directory.'/npm.exe');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$shimDirectory !== null) {
            self::removeStaticPath(self::$shimDirectory);
        }

        parent::tearDownAfterClass();
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->temporaryPaths) as $path) {
            $this->removePath($path);
        }

        if (self::$shimLog !== null && is_file(self::$shimLog)) {
            unlink(self::$shimLog);
        }

        parent::tearDown();
    }

    public function test_successful_orchestration_uses_exact_order_and_isolated_environment(): void
    {
        $source = $this->createValidSource();
        $workspace = $this->newPath('real-workspace');
        $staging = $this->newPath('real-staging');
        $outside = dirname($workspace).'/node_modules-similar';
        mkdir($outside, 0777, true);
        file_put_contents($outside.'/keep.txt', 'keep');
        $this->temporaryPaths[] = $outside;
        $before = $this->snapshot($source);

        [$exitCode, $report, $output] = $this->runOrchestrator($source, $workspace, $staging);
        $records = $this->shimRecords();

        $this->assertSame(0, $exitCode, $output);
        $this->assertSame('PASS', $report['status']);
        $this->assertSame([
            'prepare_source',
            'composer_validate',
            'composer_install',
            'package_discover',
            'npm_ci',
            'npm_build',
            'post_build_check',
            'stage_release',
        ], array_column($records, 'label'));
        $this->assertDirectoryExists($workspace);
        $this->assertDirectoryExists($staging);
        $this->assertDirectoryDoesNotExist($workspace.'/node_modules');
        $this->assertSame('keep', file_get_contents($outside.'/keep.txt'));
        $this->assertFileDoesNotExist($workspace.'/.env');
        $this->assertSame($before, $this->snapshot($source));
        $this->assertStringNotContainsString('base64:', $output);

        foreach ($records as $record) {
            $this->assertFalse($record['app_key_value_recorded']);
            $this->assertFalse($record['sensitive_environment_present']);

            if (in_array($record['label'], ['composer_validate', 'composer_install', 'package_discover', 'npm_ci', 'npm_build'], true)) {
                $this->assertStringEndsWith(
                    strtolower('\\'.basename($workspace)),
                    strtolower($record['cwd'])
                );
            }
        }

        $packageDiscover = collect($records)->firstWhere('label', 'package_discover');
        $this->assertStringEndsWith('-build-runtime\\build.sqlite', strtolower($packageDiscover['db_database']));
        $this->assertTrue($packageDiscover['app_key_present']);
    }

    public function test_dry_run_failure_prevents_workspace_and_commands(): void
    {
        $source = $this->createValidSource();
        unlink($source.'/composer.lock');
        $workspace = $this->newPath('blocked-workspace');
        $staging = $this->newPath('blocked-staging');

        [$exitCode, $report] = $this->runOrchestrator($source, $workspace, $staging);

        $this->assertSame(1, $exitCode);
        $this->assertSame('dry_run', $report['phase']);
        $this->assertDirectoryDoesNotExist($workspace);
        $this->assertDirectoryDoesNotExist($workspace.'-build-runtime');
        $this->assertDirectoryDoesNotExist($staging);
        $this->assertSame([], $this->shimRecords());
    }

    public function test_dry_run_failure_does_not_create_runtime_or_logs(): void
    {
        $source = $this->createValidSource();
        unlink($source.'/composer.lock');
        $workspace = $this->newPath('blocked-runtime-workspace');
        $staging = $this->newPath('blocked-runtime-staging');

        [$exitCode, $report] = $this->runOrchestrator($source, $workspace, $staging);

        $this->assertSame(1, $exitCode);
        $this->assertSame('dry_run', $report['phase']);
        $this->assertDirectoryDoesNotExist($workspace);
        $this->assertDirectoryDoesNotExist($workspace.'-build-runtime');
        $this->assertDirectoryDoesNotExist($workspace.'-build-runtime/logs');
        $this->assertSame([], $this->shimRecords());
    }

    public function test_command_failure_stops_later_commands_and_preserves_outputs(): void
    {
        $source = $this->createValidSource();
        $workspace = $this->newPath('failed-workspace');
        $staging = $this->newPath('failed-staging');

        [$exitCode, $report] = $this->runOrchestrator($source, $workspace, $staging, 'composer_install');
        $labels = array_column($this->shimRecords(), 'label');

        $this->assertSame(1, $exitCode);
        $this->assertSame('composer_install', $report['phase']);
        $this->assertSame(['prepare_source', 'composer_validate', 'composer_install'], $labels);
        $this->assertDirectoryExists($workspace);
        $this->assertDirectoryExists($workspace.'-build-runtime');
        $this->assertDirectoryDoesNotExist($staging);
        $this->assertFileExists($workspace.'-build-runtime/logs/03-composer-install.log');
    }

    public function test_composer_validate_success_is_followed_by_composer_install(): void
    {
        $source = $this->createValidSource();
        $workspace = $this->newPath('composer-order-workspace');
        $staging = $this->newPath('composer-order-staging');

        [$exitCode] = $this->runOrchestrator($source, $workspace, $staging, 'composer_install');
        $labels = array_column($this->shimRecords(), 'label');

        $this->assertSame(1, $exitCode);
        $this->assertSame(['prepare_source', 'composer_validate', 'composer_install'], $labels);
        $this->assertFileExists($workspace.'-build-runtime/logs/03-composer-install.log');
    }

    public function test_exception_after_composer_validate_reports_current_phase(): void
    {
        $source = $this->createValidSource();
        $workspace = $this->newPath('composer-exception-workspace');
        $staging = $this->newPath('composer-exception-staging');

        [$exitCode, $report] = $this->runOrchestrator(
            $source,
            $workspace,
            $staging,
            null,
            ['GARMENTSOS_TEST_BLOCK_COMPOSER_INSTALL_LOG' => '1']
        );
        $labels = array_column($this->shimRecords(), 'label');

        $this->assertSame(1, $exitCode);
        $this->assertSame('ERROR', $report['status']);
        $this->assertSame('composer_install', $report['phase']);
        $this->assertSame('composer_install', $report['command_label']);
        $this->assertArrayHasKey('exception_class', $report);
        $this->assertStringEndsWith('03-composer-install.log', str_replace('\\', '/', $report['log_path']));
        $this->assertSame(['prepare_source', 'composer_validate'], $labels);
    }

    public function test_failed_npm_build_preserves_node_modules_and_stops_checker(): void
    {
        $source = $this->createValidSource();
        $workspace = $this->newPath('npm-failed-workspace');
        $staging = $this->newPath('npm-failed-staging');

        [$exitCode] = $this->runOrchestrator($source, $workspace, $staging, 'npm_build');
        $labels = array_column($this->shimRecords(), 'label');

        $this->assertSame(1, $exitCode);
        $this->assertDirectoryExists($workspace.'/node_modules');
        $this->assertNotContains('post_build_check', $labels);
        $this->assertNotContains('stage_release', $labels);
    }

    public function test_post_build_check_failure_prevents_staging(): void
    {
        $source = $this->createValidSource();
        $workspace = $this->newPath('check-failed-workspace');
        $staging = $this->newPath('check-failed-staging');

        [$exitCode] = $this->runOrchestrator($source, $workspace, $staging, 'post_build_check');
        $labels = array_column($this->shimRecords(), 'label');

        $this->assertSame(1, $exitCode);
        $this->assertContains('post_build_check', $labels);
        $this->assertNotContains('stage_release', $labels);
        $this->assertDirectoryDoesNotExist($staging);
    }

    public function test_no_forbidden_command_is_invoked_or_logged(): void
    {
        $source = $this->createValidSource();

        [$exitCode] = $this->runOrchestrator(
            $source,
            $this->newPath('safe-workspace'),
            $this->newPath('safe-staging')
        );
        $commands = strtolower(implode("\n", array_column($this->shimRecords(), 'arguments')));

        $this->assertSame(0, $exitCode);

        foreach ([
            'composer update', 'npm install', 'migrate', ' db:', ' schema:', 'serve',
            'queue:', 'schedule:', 'backup', 'restore', 'storage:link', 'updater', 'installer',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $commands);
        }
    }

    private function runOrchestrator(
        string $source,
        string $workspace,
        string $staging,
        ?string $failLabel = null,
        array $extraEnvironment = []
    ): array {
        $environment = [
            'PATH' => str_replace('/', '\\', self::$shimDirectory).PATH_SEPARATOR.getenv('PATH'),
            'GARMENTSOS_TEST_REAL_PHP' => PHP_BINARY,
            'GARMENTSOS_TEST_SHIM_LOG' => self::$shimLog,
        ];

        if ($failLabel !== null) {
            $environment['GARMENTSOS_TEST_FAIL_LABEL'] = $failLabel;
        }

        $environment = array_merge($environment, $extraEnvironment);

        $process = new Process([
            'powershell.exe', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass',
            '-File', base_path('scripts/prepare-release-workspace.ps1'),
            '-Source', $source,
            '-Workspace', $workspace,
            '-Staging', $staging,
            '-Rules', base_path('scripts/release-rules.json'),
            '-Client', 'default',
            '-ClientName', 'GarmentsOS PRO',
            '-Version', '1.2.3',
            '-Channel', 'stable',
            '-Commit', str_repeat('a', 40),
        ], null, $environment);
        $process->run();
        $output = trim($process->getOutput());
        $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        return [$process->getExitCode(), $report, $output];
    }

    private function createValidSource(): string
    {
        $source = $this->temporaryDirectory('orchestrator-source');

        foreach ([
            'app', 'bootstrap', 'config', 'database/migrations', 'resources/css',
            'resources/js', 'resources/views', 'routes', 'public/images', 'public/js', 'scripts',
        ] as $directory) {
            mkdir($source.'/'.$directory, 0777, true);
        }

        $files = [
            'artisan' => "#!/usr/bin/env php\n<?php\n",
            'bootstrap/app.php' => "<?php return null;\n",
            'composer.json' => '{"require":{"laravel/framework":"^10.10"}}',
            'composer.lock' => '{"content-hash":"abc","packages":[{"name":"laravel/framework"}],"packages-dev":[{"name":"phpunit/phpunit"}]}',
            'package.json' => '{"private":true,"scripts":{"build":"vite build"}}',
            'package-lock.json' => '{"lockfileVersion":3,"packages":{}}',
            'vite.config.js' => "export default {};\n",
            'app/Fixture.php' => "<?php return true;\n",
            'config/app.php' => "<?php return [];\n",
            'config/client.php' => "<?php return [];\n",
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
        ];

        foreach ($files as $relative => $contents) {
            $this->writeFile($source.'/'.$relative, $contents);
        }

        foreach ([
            'prepare-release-workspace-dry-run.ps1',
            'prepare-release-source.php',
            'check-release-prerequisites.php',
            'stage-release.php',
            'validate-release-inventory.php',
        ] as $script) {
            copy(base_path('scripts/'.$script), $source.'/scripts/'.$script);
        }

        return $source;
    }

    private function shimRecords(): array
    {
        if (self::$shimLog === null || !is_file(self::$shimLog)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
            file(self::$shimLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
        )));
    }

    private static function shimSource(): string
    {
        return <<<'CS'
using System;
using System.Diagnostics;
using System.IO;
using System.Linq;
using System.Text;

class Shim {
    static string E(string value) {
        if (value == null) return "";
        return value.Replace("\\", "\\\\").Replace("\"", "\\\"").Replace("\r", "\\r").Replace("\n", "\\n");
    }

    static void Record(string[] args, string label) {
        string log = Environment.GetEnvironmentVariable("GARMENTSOS_TEST_SHIM_LOG");
        if (String.IsNullOrEmpty(log)) return;
        string joined = String.Join(" ", args);
        string json = "{\"label\":\"" + E(label) + "\",\"arguments\":\"" + E(joined) +
            "\",\"cwd\":\"" + E(Environment.CurrentDirectory) +
            "\",\"db_database\":\"" + E(Environment.GetEnvironmentVariable("DB_DATABASE")) +
            "\",\"app_key_present\":" + (!String.IsNullOrEmpty(Environment.GetEnvironmentVariable("APP_KEY")) ? "true" : "false") +
            ",\"app_key_value_recorded\":false" +
            ",\"sensitive_environment_present\":" +
            ((!String.IsNullOrEmpty(Environment.GetEnvironmentVariable("DATABASE_URL")) ||
              !String.IsNullOrEmpty(Environment.GetEnvironmentVariable("DB_PASSWORD")) ||
              !String.IsNullOrEmpty(Environment.GetEnvironmentVariable("AWS_SECRET_ACCESS_KEY")) ||
              !String.IsNullOrEmpty(Environment.GetEnvironmentVariable("PUSHER_APP_SECRET"))) ? "true" : "false") +
            "}";
        File.AppendAllText(log, json + Environment.NewLine);
    }

    static int DelegatePhp(string[] args) {
        string realPhp = Environment.GetEnvironmentVariable("GARMENTSOS_TEST_REAL_PHP");
        var info = new ProcessStartInfo(realPhp);
        info.UseShellExecute = false;
        info.WorkingDirectory = Environment.CurrentDirectory;
        info.Arguments = String.Join(" ", args.Select(Quote));
        var process = Process.Start(info);
        process.WaitForExit();
        return process.ExitCode;
    }

    static string Quote(string value) {
        return "\"" + value.Replace("\\", "\\\\").Replace("\"", "\\\"") + "\"";
    }

    static void EnsureDirectory(string path) {
        if (!Directory.Exists(path)) Directory.CreateDirectory(path);
    }

    static int Main(string[] args) {
        string exe = Path.GetFileNameWithoutExtension(Environment.GetCommandLineArgs()[0]).ToLowerInvariant();
        string label = Environment.GetEnvironmentVariable("GARMENTSOS_COMMAND_LABEL") ?? "";
        Record(args, label);
        string fail = Environment.GetEnvironmentVariable("GARMENTSOS_TEST_FAIL_LABEL") ?? "";
        if (String.Equals(fail, label, StringComparison.OrdinalIgnoreCase)) return 9;

        if (exe == "composer" && label == "composer_validate" &&
            Environment.GetEnvironmentVariable("GARMENTSOS_TEST_BLOCK_COMPOSER_INSTALL_LOG") == "1") {
            string db = Environment.GetEnvironmentVariable("DB_DATABASE") ?? "";
            string runtime = Path.GetDirectoryName(db) ?? "";
            EnsureDirectory(Path.Combine(runtime, "logs", "03-composer-install.log"));
        }

        if (exe == "php") {
            if (args.Length > 1 && Path.GetFileName(args[0]).Equals("artisan", StringComparison.OrdinalIgnoreCase)) {
                EnsureDirectory(Path.Combine(Environment.CurrentDirectory, "bootstrap", "cache"));
                File.WriteAllText(Path.Combine(Environment.CurrentDirectory, "bootstrap", "cache", "packages.php"), "<?php return ['laravel/sanctum' => []];");
                File.WriteAllText(Path.Combine(Environment.CurrentDirectory, "bootstrap", "cache", "services.php"), "<?php return ['providers' => []];");
                return 0;
            }
            return DelegatePhp(args);
        }

        if (exe == "composer" && label == "composer_install") {
            EnsureDirectory(Path.Combine(Environment.CurrentDirectory, "vendor", "composer"));
            File.WriteAllText(Path.Combine(Environment.CurrentDirectory, "vendor", "autoload.php"), "<?php return true;");
            File.WriteAllText(Path.Combine(Environment.CurrentDirectory, "vendor", "composer", "installed.json"),
                "{\"dev\":false,\"packages\":[{\"name\":\"laravel/framework\"}]}");
        }

        if (exe == "npm" && label == "npm_ci") {
            EnsureDirectory(Path.Combine(Environment.CurrentDirectory, "node_modules"));
            File.WriteAllText(Path.Combine(Environment.CurrentDirectory, "node_modules", "marker.txt"), "generated");
        }

        if (exe == "npm" && label == "npm_build") {
            string assets = Path.Combine(Environment.CurrentDirectory, "public", "build", "assets");
            EnsureDirectory(assets);
            File.WriteAllText(Path.Combine(Environment.CurrentDirectory, "public", "build", "manifest.json"),
                "{\"resources/js/app.js\":{\"file\":\"assets/app-123.js\"}}");
            File.WriteAllText(Path.Combine(assets, "app-123.js"), "console.log('built');");
        }

        return 0;
    }
}
CS;
    }

    private function temporaryDirectory(string $prefix): string
    {
        $path = $this->newPath($prefix);
        mkdir($path, 0777, true);
        return $path;
    }

    private function newPath(string $prefix): string
    {
        $path = str_replace('\\', '/', sys_get_temp_dir().'/'.$prefix.'-'.Str::uuid());
        $this->temporaryPaths[] = $path;
        return $path;
    }

    private function writeFile(string $path, string $contents): void
    {
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0777, true);
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
                'type' => $entry->isDir() ? 'directory' : 'file',
                'hash' => $entry->isFile() ? hash_file('sha256', $entry->getPathname()) : null,
            ];
        }
        ksort($snapshot);
        return $snapshot;
    }

    private function removePath(string $path): void
    {
        self::removeStaticPath($path);
    }

    private static function removeStaticPath(string $path): void
    {
        if (!file_exists($path) && !is_link($path)) return;
        if (is_file($path) || is_link($path)) {
            unlink($path);
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) rmdir($entry->getPathname());
            else unlink($entry->getPathname());
        }
        rmdir($path);
    }
}
