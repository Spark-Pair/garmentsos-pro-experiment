<?php

declare(strict_types=1);

const PREREQUISITES_SAFE = 0;
const PREREQUISITES_UNSAFE = 1;
const PREREQUISITES_INPUT_ERROR = 2;

/**
 * @param array<string, mixed> $report
 */
function prerequisiteFinish(array $report, int $exitCode): never
{
    echo json_encode(
        $report,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    ).PHP_EOL;

    exit($exitCode);
}

function prerequisiteInputError(string $message): never
{
    prerequisiteFinish([
        'status' => 'ERROR',
        'errors' => [[
            'code' => 'invalid_input',
            'message' => $message,
        ]],
    ], PREREQUISITES_INPUT_ERROR);
}

function prerequisiteIsAbsolute(string $path): bool
{
    return preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2}|\/)/', $path) === 1;
}

function prerequisiteNormalize(string $path): string
{
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#/+#', '/', $path) ?? $path;

    return trim($path, '/');
}

function prerequisiteSafeRelativePath(string $path): bool
{
    if ($path === '' || str_contains($path, "\0") || prerequisiteIsAbsolute($path)) {
        return false;
    }

    $normalized = prerequisiteNormalize($path);

    if ($normalized === '' || $normalized !== $path) {
        return false;
    }

    foreach (explode('/', $normalized) as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            return false;
        }
    }

    return true;
}

function prerequisiteGlobRegex(string $pattern): string
{
    $pattern = prerequisiteNormalize($pattern);
    $regex = '';
    $length = strlen($pattern);

    for ($index = 0; $index < $length; $index++) {
        $character = $pattern[$index];

        if ($character === '*') {
            $double = $index + 1 < $length && $pattern[$index + 1] === '*';

            if ($double) {
                $index++;

                if ($index + 1 < $length && $pattern[$index + 1] === '/') {
                    $index++;
                    $regex .= '(?:.*/)?';
                } else {
                    $regex .= '.*';
                }
            } else {
                $regex .= '[^/]*';
            }

            continue;
        }

        if ($character === '?') {
            $regex .= '[^/]';
            continue;
        }

        $regex .= preg_quote($character, '~');
    }

    return '~^'.$regex.'$~iD';
}

/**
 * @param list<string> $patterns
 */
function prerequisiteMatchesGlob(string $path, array $patterns): bool
{
    foreach ($patterns as $pattern) {
        if (preg_match(prerequisiteGlobRegex($pattern), $path) === 1) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<string, mixed> $rules
 */
function prerequisiteValidateRules(array $rules): ?string
{
    foreach ([
        'include_paths',
        'generated_paths',
        'create_empty_directories',
        'exclude_paths',
        'required_files',
        'required_directories',
    ] as $section) {
        if (!isset($rules[$section]) || !is_array($rules[$section]) || !array_is_list($rules[$section])) {
            return "Rules section '{$section}' must be a list.";
        }

        foreach ($rules[$section] as $value) {
            if (!is_string($value) || $value === '') {
                return "Rules section '{$section}' must contain non-empty strings.";
            }
        }
    }

    $prohibited = $rules['prohibited_patterns'] ?? null;

    if (!is_array($prohibited)) {
        return "Rules section 'prohibited_patterns' must be an object.";
    }

    foreach (['path_globs', 'archive_entry_regex', 'secret_content_regex'] as $section) {
        if (
            !isset($prohibited[$section])
            || !is_array($prohibited[$section])
            || !array_is_list($prohibited[$section])
        ) {
            return "Rules section 'prohibited_patterns.{$section}' must be a list.";
        }
    }

    return null;
}

/**
 * @param list<array<string, string>> $findings
 */
function prerequisiteAddFinding(
    array &$findings,
    string $code,
    string $message,
    ?string $path = null
): void {
    $finding = [
        'code' => $code,
        'message' => $message,
    ];

    if ($path !== null) {
        $finding['path'] = prerequisiteNormalize($path);
    }

    $findings[] = $finding;
}

/**
 * @return array<string, mixed>|null
 */
function prerequisiteReadJson(string $path): ?array
{
    try {
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }

    return is_array($decoded) ? $decoded : null;
}

/**
 * @return list<string>
 */
function prerequisiteInstalledPackages(array $installed): array
{
    $packages = $installed['packages'] ?? $installed;

    if (!is_array($packages)) {
        return [];
    }

    $names = [];

    foreach ($packages as $package) {
        if (is_array($package) && isset($package['name']) && is_string($package['name'])) {
            $names[] = strtolower($package['name']);
        }
    }

    sort($names, SORT_STRING);

    return array_values(array_unique($names));
}

/**
 * @return list<string>
 */
function prerequisiteLockDevPackages(array $lock): array
{
    $names = [];

    foreach ($lock['packages-dev'] ?? [] as $package) {
        if (is_array($package) && isset($package['name']) && is_string($package['name'])) {
            $names[] = strtolower($package['name']);
        }
    }

    sort($names, SORT_STRING);

    return array_values(array_unique($names));
}

/**
 * @return list<string>
 */
function prerequisiteDetectedDevPackages(array $installedNames, array $lockDevNames): array
{
    $known = [
        'fakerphp/faker',
        'laravel/pint',
        'laravel/sail',
        'mockery/mockery',
        'nunomaduro/collision',
        'phpunit/phpunit',
        'spatie/ignition',
        'spatie/laravel-ignition',
    ];
    $detected = array_intersect($installedNames, array_unique(array_merge($known, $lockDevNames)));
    sort($detected, SORT_STRING);

    return array_values($detected);
}

/**
 * @return list<string>
 */
function prerequisiteDevProviderMarkers(): array
{
    return [
        'Laravel\\\\Sail\\\\SailServiceProvider',
        'NunoMaduro\\\\Collision',
        'Spatie\\\\LaravelIgnition',
        'Spatie\\\\Ignition',
        'PHPUnit\\\\',
        'Mockery\\\\',
        'Faker\\\\',
    ];
}

function prerequisiteRiskCode(string $path): ?string
{
    $path = strtolower(prerequisiteNormalize($path));

    $patterns = [
        'environment_file' => ['.env', '.env.*', '**/.env', '**/.env.*'],
        'source_control' => ['.git', '.git/**', '.github', '.github/**'],
        'backups' => ['backups', 'backups/**', '**/backups', '**/backups/**'],
        'sqlite_data' => [
            '*.sqlite', '*.sqlite-wal', '*.sqlite-shm', '*.sqlite-*',
            '**/*.sqlite', '**/*.sqlite-wal', '**/*.sqlite-shm', '**/*.sqlite-*',
        ],
        'storage_runtime' => [
            'storage/app',
            'storage/app/**',
            'storage/logs',
            'storage/logs/**',
            'storage/framework/cache',
            'storage/framework/cache/**',
            'storage/framework/sessions',
            'storage/framework/sessions/**',
            'storage/framework/views',
            'storage/framework/views/**',
        ],
        'uploads' => ['uploads', 'uploads/**', '**/uploads', '**/uploads/**', 'public/uploads', 'public/uploads/**'],
        'logs' => ['logs', 'logs/**', '**/logs', '**/logs/**'],
        'vite_hot_file' => ['public/hot'],
        'public_storage' => ['public/storage', 'public/storage/**'],
        'node_modules' => ['node_modules', 'node_modules/**'],
        'tests' => ['tests', 'tests/**'],
        'documentation' => ['docs', 'docs/**'],
        'release_scripts' => ['scripts', 'scripts/**'],
        'credential_file' => [
            'auth.json', '**/auth.json', '**/*.pem', '**/*.key', '**/*.pfx',
            '**/*.p12', '**/id_rsa', '**/id_ed25519',
        ],
    ];

    foreach ($patterns as $code => $globs) {
        if (prerequisiteMatchesGlob($path, $globs)) {
            return $code;
        }
    }

    return null;
}

$options = getopt('', ['source:', 'rules:', 'validate-build::']);

foreach (['source', 'rules'] as $required) {
    if (
        !isset($options[$required])
        || !is_string($options[$required])
        || trim($options[$required]) === ''
    ) {
        prerequisiteInputError("Missing required option --{$required}.");
    }
}

$validateBuildInput = $options['validate-build'] ?? 'false';

if (!is_string($validateBuildInput) || !in_array(strtolower($validateBuildInput), ['true', 'false'], true)) {
    prerequisiteInputError('--validate-build must be true or false.');
}

$validateBuild = strtolower($validateBuildInput) === 'true';
$sourceInput = $options['source'];

if (
    !prerequisiteIsAbsolute($sourceInput)
    || str_contains($sourceInput, "\0")
    || preg_match('#(^|[\\\\/])\.\.([\\\\/]|$)#', $sourceInput) === 1
) {
    prerequisiteInputError('The source path must be a safe absolute path.');
}

if (is_link($sourceInput)) {
    prerequisiteInputError('The source path must not be a link or reparse-like entry.');
}

$source = realpath($sourceInput);

if ($source === false || !is_dir($source)) {
    prerequisiteInputError('The source directory does not exist.');
}

$rulesPath = realpath($options['rules']);

if ($rulesPath === false || !is_file($rulesPath) || !is_readable($rulesPath)) {
    prerequisiteInputError('The rules file is missing or unreadable.');
}

try {
    $rules = json_decode((string) file_get_contents($rulesPath), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    prerequisiteInputError('The rules file contains invalid JSON.');
}

if (!is_array($rules)) {
    prerequisiteInputError('The rules file root must be an object.');
}

$rulesError = prerequisiteValidateRules($rules);

if ($rulesError !== null) {
    prerequisiteInputError($rulesError);
}

$findings = [];
$checks = [];
$requiredPreparationFiles = [
    'composer.json',
    'composer.lock',
    'package.json',
    'package-lock.json',
    'vite.config.js',
    'resources/css/app.css',
    'resources/js/app.js',
];

foreach ($requiredPreparationFiles as $relativePath) {
    $absolutePath = $source.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $exists = is_file($absolutePath) && !is_link($absolutePath);
    $checks['files'][$relativePath] = $exists;

    if (!$exists) {
        prerequisiteAddFinding(
            $findings,
            'missing_required_file',
            'A required dependency or frontend preparation file is missing.',
            $relativePath
        );
    }
}

$composerJson = is_file($source.DIRECTORY_SEPARATOR.'composer.json')
    ? prerequisiteReadJson($source.DIRECTORY_SEPARATOR.'composer.json')
    : null;
$composerLock = is_file($source.DIRECTORY_SEPARATOR.'composer.lock')
    ? prerequisiteReadJson($source.DIRECTORY_SEPARATOR.'composer.lock')
    : null;
$packageJson = is_file($source.DIRECTORY_SEPARATOR.'package.json')
    ? prerequisiteReadJson($source.DIRECTORY_SEPARATOR.'package.json')
    : null;
$packageLock = is_file($source.DIRECTORY_SEPARATOR.'package-lock.json')
    ? prerequisiteReadJson($source.DIRECTORY_SEPARATOR.'package-lock.json')
    : null;

foreach ([
    'composer.json' => $composerJson,
    'composer.lock' => $composerLock,
    'package.json' => $packageJson,
    'package-lock.json' => $packageLock,
] as $relativePath => $decoded) {
    if (is_file($source.DIRECTORY_SEPARATOR.$relativePath) && $decoded === null) {
        prerequisiteAddFinding(
            $findings,
            'invalid_json',
            'A required package metadata file contains invalid JSON.',
            $relativePath
        );
    }
}

if (is_array($packageJson)) {
    $buildScript = $packageJson['scripts']['build'] ?? null;

    if (!is_string($buildScript) || trim($buildScript) === '') {
        prerequisiteAddFinding(
            $findings,
            'missing_build_script',
            'package.json does not define a build script.',
            'package.json'
        );
    }
}

$vendorRequired = in_array('vendor/autoload.php', $rules['required_files'], true)
    || prerequisiteMatchesGlob('vendor/autoload.php', $rules['include_paths']);
$vendorExists = is_dir($source.DIRECTORY_SEPARATOR.'vendor');
$vendorAutoload = $source.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

$checks['vendor'] = [
    'required' => $vendorRequired,
    'present' => $vendorExists,
    'autoload_present' => is_file($vendorAutoload) && !is_link($vendorAutoload),
    'installed_metadata_present' => false,
    'production_mode' => null,
    'dev_packages' => [],
];

if ($vendorRequired && (!is_file($vendorAutoload) || is_link($vendorAutoload))) {
    prerequisiteAddFinding(
        $findings,
        'missing_vendor_autoload',
        'Prepared production vendor/autoload.php is required.',
        'vendor/autoload.php'
    );
}

$installedPath = $source.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'composer'.DIRECTORY_SEPARATOR.'installed.json';

if (is_file($installedPath)) {
    $checks['vendor']['installed_metadata_present'] = true;
    $installed = prerequisiteReadJson($installedPath);

    if ($installed === null) {
        prerequisiteAddFinding(
            $findings,
            'invalid_vendor_metadata',
            'Composer installed package metadata is invalid.',
            'vendor/composer/installed.json'
        );
    } else {
        $installedNames = prerequisiteInstalledPackages($installed);
        $lockDevNames = is_array($composerLock) ? prerequisiteLockDevPackages($composerLock) : [];
        $devPackages = prerequisiteDetectedDevPackages($installedNames, $lockDevNames);
        $productionMode = ($installed['dev'] ?? null) === false;
        $checks['vendor']['production_mode'] = $productionMode;
        $checks['vendor']['dev_packages'] = $devPackages;

        if (!$productionMode) {
            prerequisiteAddFinding(
                $findings,
                'vendor_dev_mode',
                'Composer vendor metadata is not marked as production-only.',
                'vendor/composer/installed.json'
            );
        }

        foreach ($devPackages as $package) {
            prerequisiteAddFinding(
                $findings,
                'development_package',
                'A development Composer package is installed.',
                $package
            );
        }
    }
} elseif ($vendorExists) {
    prerequisiteAddFinding(
        $findings,
        'missing_vendor_metadata',
        'Composer installed package metadata is required to verify production dependencies.',
        'vendor/composer/installed.json'
    );
}

$checks['bootstrap_cache'] = [
    'packages_present' => false,
    'services_present' => false,
    'dev_provider_contamination' => [],
];

foreach (['packages.php', 'services.php'] as $cacheFile) {
    $relativePath = 'bootstrap/cache/'.$cacheFile;
    $absolutePath = $source.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $checks['bootstrap_cache'][str_replace('.php', '_present', $cacheFile)] = is_file($absolutePath);

    if (!is_file($absolutePath)) {
        prerequisiteAddFinding(
            $findings,
            'missing_bootstrap_cache',
            'Prepared package discovery cache is missing.',
            $relativePath
        );
        continue;
    }

    $contents = (string) file_get_contents($absolutePath);

    foreach (prerequisiteDevProviderMarkers() as $marker) {
        if (stripos($contents, $marker) !== false) {
            $checks['bootstrap_cache']['dev_provider_contamination'][] = $marker;
            prerequisiteAddFinding(
                $findings,
                'development_provider_cache',
                'Bootstrap package discovery cache references a development provider.',
                $relativePath
            );
            break;
        }
    }
}

$vite = [
    'validation_requested' => $validateBuild,
    'manifest_present' => false,
    'manifest_valid' => null,
    'assets_checked' => 0,
    'assets_missing' => 0,
];

if ($validateBuild) {
    $manifestPath = $source.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'manifest.json';
    $vite['manifest_present'] = is_file($manifestPath) && !is_link($manifestPath);

    if (!$vite['manifest_present']) {
        prerequisiteAddFinding(
            $findings,
            'missing_vite_manifest',
            'The prepared Vite build manifest is missing.',
            'public/build/manifest.json'
        );
    } else {
        $manifest = prerequisiteReadJson($manifestPath);
        $vite['manifest_valid'] = $manifest !== null;

        if ($manifest === null) {
            prerequisiteAddFinding(
                $findings,
                'invalid_vite_manifest',
                'The prepared Vite build manifest contains invalid JSON.',
                'public/build/manifest.json'
            );
        } else {
            foreach ($manifest as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $assets = [];

                if (isset($entry['file']) && is_string($entry['file'])) {
                    $assets[] = $entry['file'];
                }

                foreach (['css', 'assets'] as $key) {
                    if (isset($entry[$key]) && is_array($entry[$key])) {
                        foreach ($entry[$key] as $asset) {
                            if (is_string($asset)) {
                                $assets[] = $asset;
                            }
                        }
                    }
                }

                foreach (array_unique($assets) as $asset) {
                    $asset = prerequisiteNormalize($asset);
                    $vite['assets_checked']++;

                    if (!prerequisiteSafeRelativePath($asset)) {
                        $vite['assets_missing']++;
                        prerequisiteAddFinding(
                            $findings,
                            'unsafe_vite_asset',
                            'The Vite manifest references an unsafe asset path.'
                        );
                        continue;
                    }

                    $assetPath = $source.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build'
                        .DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $asset);

                    if (!is_file($assetPath) || is_link($assetPath)) {
                        $vite['assets_missing']++;
                        prerequisiteAddFinding(
                            $findings,
                            'missing_vite_asset',
                            'A Vite manifest asset is missing or unsafe.',
                            'public/build/'.$asset
                        );
                    }
                }
            }
        }
    }
}

$checks['vite'] = $vite;
$sourceRootLength = strlen(rtrim($source, DIRECTORY_SEPARATOR)) + 1;

try {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $entry) {
        $relativePath = prerequisiteNormalize(substr($entry->getPathname(), $sourceRootLength));

        if (!prerequisiteSafeRelativePath($relativePath)) {
            prerequisiteAddFinding(
                $findings,
                'unsafe_path',
                'The source contains an unsafe or malformed path.'
            );
            continue;
        }

        if ($entry->isLink()) {
            prerequisiteAddFinding(
                $findings,
                'unsafe_link',
                'The source contains a link or reparse-like entry.',
                $relativePath
            );
            continue;
        }

        if ($entry->isDir()) {
            $riskCode = prerequisiteRiskCode($relativePath);

            if ($riskCode !== null) {
                prerequisiteAddFinding(
                    $findings,
                    $riskCode,
                    'The source contains a directory prohibited from a prepared release workspace.',
                    $relativePath
                );
            }

            continue;
        }

        if (!$entry->isFile()) {
            continue;
        }

        $riskCode = prerequisiteRiskCode($relativePath);

        if ($riskCode !== null) {
            prerequisiteAddFinding(
                $findings,
                $riskCode,
                'The source contains content prohibited from a prepared release workspace.',
                $relativePath
            );
        }
    }
} catch (UnexpectedValueException) {
    prerequisiteAddFinding(
        $findings,
        'source_walk_failed',
        'The source tree could not be inspected safely.'
    );
}

$uniqueFindings = [];

foreach ($findings as $finding) {
    $key = ($finding['code'] ?? '').'|'.($finding['path'] ?? '').'|'.($finding['message'] ?? '');
    $uniqueFindings[$key] = $finding;
}

$findings = array_values($uniqueFindings);
$report = [
    'status' => $findings === [] ? 'SAFE' : 'UNSAFE',
    'validate_build' => $validateBuild,
    'summary' => [
        'finding_count' => count($findings),
        'dev_package_count' => count($checks['vendor']['dev_packages']),
        'vite_assets_checked' => $checks['vite']['assets_checked'],
        'vite_assets_missing' => $checks['vite']['assets_missing'],
    ],
    'checks' => $checks,
    'findings' => $findings,
];

prerequisiteFinish(
    $report,
    $findings === [] ? PREREQUISITES_SAFE : PREREQUISITES_UNSAFE
);
