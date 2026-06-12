<?php

declare(strict_types=1);

const EXIT_VALID = 0;
const EXIT_INVALID_INVENTORY = 1;
const EXIT_INVALID_INPUT = 2;

/**
 * @param array<string, mixed> $report
 */
function finish(array $report, int $exitCode): never
{
    echo json_encode(
        $report,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    ).PHP_EOL;

    exit($exitCode);
}

/**
 * @return array<string, mixed>
 */
function inputFailure(string $message): array
{
    return [
        'status' => 'ERROR',
        'errors' => [[
            'code' => 'invalid_input',
            'message' => $message,
        ]],
    ];
}

function isAbsolutePath(string $path): bool
{
    return preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2}|\/)/', $path) === 1;
}

function normalizePath(string $path): string
{
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#/+#', '/', $path) ?? $path;

    return trim($path, '/');
}

function isSafeInventoryPath(string $path): bool
{
    if ($path === '' || str_contains($path, "\0") || isAbsolutePath($path)) {
        return false;
    }

    $normalized = normalizePath($path);

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

function globRegex(string $pattern): string
{
    $pattern = normalizePath($pattern);
    $regex = '';
    $length = strlen($pattern);

    for ($index = 0; $index < $length; $index++) {
        $character = $pattern[$index];

        if ($character === '*') {
            $isDouble = $index + 1 < $length && $pattern[$index + 1] === '*';

            if ($isDouble) {
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
function matchesGlobList(string $path, array $patterns): bool
{
    foreach ($patterns as $pattern) {
        if (preg_match(globRegex($pattern), $path) === 1) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<string> $patterns
 */
function validateRegexList(array $patterns): bool
{
    foreach ($patterns as $pattern) {
        if (@preg_match('~'.$pattern.'~', '') === false) {
            return false;
        }
    }

    return true;
}

/**
 * @param list<string> $patterns
 */
function matchesRegexList(string $value, array $patterns): bool
{
    foreach ($patterns as $pattern) {
        if (preg_match('~'.$pattern.'~', $value) === 1) {
            return true;
        }
    }

    return false;
}

function literalPatternPrefix(string $pattern): string
{
    $pattern = normalizePath($pattern);
    $wildcard = strcspn($pattern, '*?[');

    return rtrim(substr($pattern, 0, $wildcard), '/');
}

/**
 * @param list<string> $copyPatterns
 * @param list<string> $generatedPatterns
 * @param list<string> $emptyDirectories
 */
function directoryIsDeclared(
    string $directory,
    array $copyPatterns,
    array $generatedPatterns,
    array $emptyDirectories
): bool {
    foreach (array_merge($copyPatterns, $generatedPatterns) as $pattern) {
        $prefix = literalPatternPrefix($pattern);

        if ($prefix === '') {
            continue;
        }

        $patternHasWildcard = strpbrk($pattern, '*?[') !== false;
        $base = $patternHasWildcard ? $prefix : normalizePath(dirname($prefix));
        $base = $base === '.' ? '' : $base;

        if (
            $directory === $base
            || ($base !== '' && str_starts_with($directory, $base.'/'))
            || str_starts_with($base, $directory.'/')
        ) {
            return true;
        }
    }

    foreach ($emptyDirectories as $declared) {
        $declared = normalizePath($declared);

        if ($directory === $declared || str_starts_with($declared, $directory.'/')) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<string, mixed> $rules
 */
function validateRules(array $rules): ?string
{
    $listSections = [
        'include_paths',
        'generated_paths',
        'create_empty_directories',
        'exclude_paths',
        'required_files',
        'required_directories',
    ];

    foreach ($listSections as $section) {
        if (!isset($rules[$section]) || !is_array($rules[$section]) || !array_is_list($rules[$section])) {
            return "Rules section '{$section}' must be a list.";
        }

        foreach ($rules[$section] as $value) {
            if (!is_string($value) || $value === '') {
                return "Rules section '{$section}' must contain non-empty strings.";
            }
        }
    }

    if (!isset($rules['prohibited_patterns']) || !is_array($rules['prohibited_patterns'])) {
        return "Rules section 'prohibited_patterns' must be an object.";
    }

    foreach (['path_globs', 'archive_entry_regex', 'secret_content_regex'] as $section) {
        if (
            !isset($rules['prohibited_patterns'][$section])
            || !is_array($rules['prohibited_patterns'][$section])
            || !array_is_list($rules['prohibited_patterns'][$section])
        ) {
            return "Rules section 'prohibited_patterns.{$section}' must be a list.";
        }

        foreach ($rules['prohibited_patterns'][$section] as $value) {
            if (!is_string($value) || $value === '') {
                return "Rules section 'prohibited_patterns.{$section}' must contain non-empty strings.";
            }
        }
    }

    foreach (['archive_entry_regex', 'secret_content_regex'] as $section) {
        if (!validateRegexList($rules['prohibited_patterns'][$section])) {
            return "Rules section 'prohibited_patterns.{$section}' contains an invalid regular expression.";
        }
    }

    $manifestFields = $rules['manifest_fields']['embedded_release_info'] ?? null;

    if (!is_array($manifestFields) || !array_is_list($manifestFields)) {
        return "Rules section 'manifest_fields.embedded_release_info' must be a list.";
    }

    foreach ($manifestFields as $field) {
        if (!is_string($field) || $field === '') {
            return "Manifest required fields must contain non-empty strings.";
        }
    }

    return null;
}

/**
 * @return mixed
 */
function dottedValue(array $data, string $key)
{
    $value = $data;

    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return null;
        }

        $value = $value[$segment];
    }

    return $value;
}

function looksLikeTextFile(string $path): bool
{
    $textExtensions = [
        'blade.php', 'css', 'csv', 'env', 'html', 'htm', 'ini', 'js', 'json',
        'lock', 'md', 'php', 'svg', 'txt', 'xml', 'yaml', 'yml',
    ];
    $lower = strtolower($path);
    $basename = strtolower(basename($path));

    if (in_array($basename, ['artisan', '.htaccess'], true)) {
        return true;
    }

    foreach ($textExtensions as $extension) {
        if (str_ends_with($lower, '.'.$extension)) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<string> $patterns
 */
function containsProhibitedContent(string $absolutePath, array $patterns): bool
{
    if (!looksLikeTextFile($absolutePath) || !is_readable($absolutePath)) {
        return false;
    }

    $handle = @fopen($absolutePath, 'rb');

    if ($handle === false) {
        return false;
    }

    $sample = fread($handle, 4096);

    if ($sample === false || str_contains($sample, "\0")) {
        fclose($handle);
        return false;
    }

    rewind($handle);
    $carry = '';

    while (!feof($handle)) {
        $chunk = fread($handle, 65536);

        if ($chunk === false) {
            break;
        }

        $buffer = $carry.$chunk;

        foreach ($patterns as $pattern) {
            if (preg_match('~'.$pattern.'~', $buffer) === 1) {
                fclose($handle);
                return true;
            }
        }

        $carry = substr($buffer, -2048);
    }

    fclose($handle);

    return false;
}

/**
 * @param list<array<string, string>> $errors
 */
function addError(array &$errors, string $code, string $message, ?string $path = null): void
{
    $error = [
        'code' => $code,
        'message' => $message,
    ];

    if ($path !== null) {
        $error['path'] = $path;
    }

    $errors[] = $error;
}

$options = getopt('', ['staging:', 'rules:', 'client:', 'version:', 'channel:']);

foreach (['staging', 'rules', 'client', 'version', 'channel'] as $requiredOption) {
    if (
        !array_key_exists($requiredOption, $options)
        || !is_string($options[$requiredOption])
        || trim($options[$requiredOption]) === ''
    ) {
        finish(inputFailure("Missing required option --{$requiredOption}."), EXIT_INVALID_INPUT);
    }
}

$stagingInput = $options['staging'];
$rulesInput = $options['rules'];
$client = trim($options['client']);
$version = trim($options['version']);
$channel = trim($options['channel']);

if (!isAbsolutePath($stagingInput)) {
    finish(inputFailure('The staging directory must be an absolute path.'), EXIT_INVALID_INPUT);
}

$stagingPath = realpath($stagingInput);

if ($stagingPath === false || !is_dir($stagingPath)) {
    finish(inputFailure('The staging directory does not exist.'), EXIT_INVALID_INPUT);
}

$rulesPath = realpath($rulesInput);

if ($rulesPath === false || !is_file($rulesPath) || !is_readable($rulesPath)) {
    finish(inputFailure('The rules file is missing or unreadable.'), EXIT_INVALID_INPUT);
}

try {
    $decodedRules = json_decode((string) file_get_contents($rulesPath), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    finish(inputFailure('The rules file contains invalid JSON.'), EXIT_INVALID_INPUT);
}

if (!is_array($decodedRules)) {
    finish(inputFailure('The rules file root must be an object.'), EXIT_INVALID_INPUT);
}

$rulesError = validateRules($decodedRules);

if ($rulesError !== null) {
    finish(inputFailure($rulesError), EXIT_INVALID_INPUT);
}

/** @var list<string> $includePaths */
$includePaths = $decodedRules['include_paths'];
/** @var list<string> $generatedPaths */
$generatedPaths = $decodedRules['generated_paths'];
/** @var list<string> $emptyDirectories */
$emptyDirectories = $decodedRules['create_empty_directories'];
/** @var list<string> $excludePaths */
$excludePaths = $decodedRules['exclude_paths'];
/** @var list<string> $requiredFiles */
$requiredFiles = $decodedRules['required_files'];
/** @var list<string> $requiredDirectories */
$requiredDirectories = $decodedRules['required_directories'];
/** @var list<string> $prohibitedPathPatterns */
$prohibitedPathPatterns = $decodedRules['prohibited_patterns']['path_globs'];
/** @var list<string> $archiveEntryPatterns */
$archiveEntryPatterns = $decodedRules['prohibited_patterns']['archive_entry_regex'];
/** @var list<string> $prohibitedContentPatterns */
$prohibitedContentPatterns = $decodedRules['prohibited_patterns']['secret_content_regex'];
/** @var list<string> $manifestRequiredFields */
$manifestRequiredFields = $decodedRules['manifest_fields']['embedded_release_info'];

$errors = [];
$warnings = [];
$files = [];
$directories = [];
$totalSize = 0;
$rootLength = strlen(rtrim($stagingPath, DIRECTORY_SEPARATOR)) + 1;

$directoryIterator = new RecursiveDirectoryIterator(
    $stagingPath,
    FilesystemIterator::SKIP_DOTS
);
$iterator = new RecursiveIteratorIterator(
    $directoryIterator,
    RecursiveIteratorIterator::SELF_FIRST
);

try {
    foreach ($iterator as $entry) {
        $absolutePath = $entry->getPathname();
        $relativePath = normalizePath(substr($absolutePath, $rootLength));

        if (!isSafeInventoryPath($relativePath) || matchesRegexList($relativePath, $archiveEntryPatterns)) {
            addError($errors, 'unsafe_path', 'An unsafe or malformed inventory path was found.');
            continue;
        }

        if ($entry->isLink()) {
            addError($errors, 'link_not_allowed', 'Links and reparse-like entries are not allowed.', $relativePath);
            continue;
        }

        if ($entry->isDir()) {
            $directories[] = $relativePath;

            $declaredSkeleton = in_array($relativePath, $emptyDirectories, true);
            $excluded = matchesGlobList($relativePath, $excludePaths)
                || matchesGlobList($relativePath, $prohibitedPathPatterns);

            if ($excluded && !$declaredSkeleton) {
                addError($errors, 'excluded_directory', 'An excluded directory is present.', $relativePath);
            } elseif (!directoryIsDeclared($relativePath, $includePaths, $generatedPaths, $emptyDirectories)) {
                addError($errors, 'unallowlisted_directory', 'A directory is outside the release allowlist.', $relativePath);
            }

            continue;
        }

        if (!$entry->isFile()) {
            addError($errors, 'unsupported_entry', 'An unsupported filesystem entry is present.', $relativePath);
            continue;
        }

        $files[] = $relativePath;
        $size = $entry->getSize();
        $totalSize += $size === false ? 0 : $size;

        if (
            matchesGlobList($relativePath, $excludePaths)
            || matchesGlobList($relativePath, $prohibitedPathPatterns)
        ) {
            addError($errors, 'prohibited_file', 'A prohibited file is present.', $relativePath);
            continue;
        }

        if (!matchesGlobList($relativePath, $includePaths) && !matchesGlobList($relativePath, $generatedPaths)) {
            addError($errors, 'unallowlisted_file', 'A file is outside the release allowlist.', $relativePath);
            continue;
        }

        if (containsProhibitedContent($absolutePath, $prohibitedContentPatterns)) {
            addError($errors, 'prohibited_content', 'A text-like file matches a prohibited secret pattern.', $relativePath);
        }
    }
} catch (UnexpectedValueException) {
    addError($errors, 'inventory_walk_failed', 'The staged inventory could not be read safely.');
}

sort($files, SORT_STRING);
sort($directories, SORT_STRING);

foreach ($requiredFiles as $requiredFile) {
    $requiredFile = normalizePath($requiredFile);

    if (!in_array($requiredFile, $files, true)) {
        addError($errors, 'missing_required_file', 'A required release file is missing.', $requiredFile);
    }
}

foreach ($requiredDirectories as $requiredDirectory) {
    $requiredDirectory = normalizePath($requiredDirectory);

    if (!in_array($requiredDirectory, $directories, true)) {
        addError($errors, 'missing_required_directory', 'A required release directory is missing.', $requiredDirectory);
    }
}

$viteManifestPath = $stagingPath.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'manifest.json';
$viteStatus = [
    'manifest_present' => is_file($viteManifestPath),
    'manifest_valid' => false,
    'assets_checked' => 0,
    'assets_missing' => 0,
];

if (is_file($viteManifestPath)) {
    try {
        $viteManifest = json_decode((string) file_get_contents($viteManifestPath), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($viteManifest)) {
            throw new JsonException('Invalid Vite manifest root.');
        }

        $viteStatus['manifest_valid'] = true;

        foreach ($viteManifest as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $assetReferences = [];

            if (isset($entry['file']) && is_string($entry['file'])) {
                $assetReferences[] = $entry['file'];
            }

            foreach (['css', 'assets'] as $listKey) {
                if (isset($entry[$listKey]) && is_array($entry[$listKey])) {
                    foreach ($entry[$listKey] as $asset) {
                        if (is_string($asset)) {
                            $assetReferences[] = $asset;
                        }
                    }
                }
            }

            foreach (array_unique($assetReferences) as $assetReference) {
                $normalizedAsset = normalizePath($assetReference);
                $viteStatus['assets_checked']++;

                if (!isSafeInventoryPath($normalizedAsset)) {
                    $viteStatus['assets_missing']++;
                    addError($errors, 'unsafe_vite_asset', 'The Vite manifest references an unsafe asset path.');
                    continue;
                }

                $assetPath = $stagingPath.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build'
                    .DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $normalizedAsset);

                if (!is_file($assetPath)) {
                    $viteStatus['assets_missing']++;
                    addError($errors, 'missing_vite_asset', 'A Vite manifest asset is missing.', 'public/build/'.$normalizedAsset);
                }
            }
        }
    } catch (JsonException) {
        addError($errors, 'invalid_vite_manifest', 'The Vite build manifest is invalid JSON.', 'public/build/manifest.json');
    }
}

$migrationPrefix = 'database/migrations/';
$migrations = array_values(array_filter(
    $files,
    static fn (string $path): bool => str_starts_with($path, $migrationPrefix) && str_ends_with($path, '.php')
));
$migrations = array_map(
    static fn (string $path): string => substr($path, strlen($migrationPrefix)),
    $migrations
);
sort($migrations, SORT_STRING);

$releaseInfoPath = $stagingPath.DIRECTORY_SEPARATOR.'release-info.json';

if (is_file($releaseInfoPath)) {
    try {
        $releaseInfo = json_decode((string) file_get_contents($releaseInfoPath), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($releaseInfo)) {
            throw new JsonException('Invalid release info root.');
        }

        foreach ($manifestRequiredFields as $field) {
            if (dottedValue($releaseInfo, $field) === null) {
                addError($errors, 'missing_release_info_field', 'A required release-info field is missing.', $field);
            }
        }

        $comparisons = [
            'target.client_id' => $client,
            'app.version' => $version,
            'target.channel' => $channel,
        ];

        foreach ($comparisons as $field => $expected) {
            if (dottedValue($releaseInfo, $field) !== $expected) {
                addError($errors, 'release_info_mismatch', 'Release metadata does not match the requested build target.', $field);
            }
        }

        if (dottedValue($releaseInfo, 'database.migration_count') !== count($migrations)) {
            addError($errors, 'migration_count_mismatch', 'Release metadata migration count does not match staged migrations.');
        }

        if (dottedValue($releaseInfo, 'database.migrations') !== $migrations) {
            addError($errors, 'migration_list_mismatch', 'Release metadata migration list does not match staged migrations.');
        }
    } catch (JsonException) {
        addError($errors, 'invalid_release_info', 'release-info.json is invalid JSON.', 'release-info.json');
    }
}

$report = [
    'status' => $errors === [] ? 'PASS' : 'FAIL',
    'target' => [
        'client_id' => $client,
        'version' => $version,
        'channel' => $channel,
    ],
    'inventory' => [
        'file_count' => count($files),
        'total_size_bytes' => $totalSize,
        'migration_count' => count($migrations),
        'migrations' => $migrations,
        'vite' => $viteStatus,
    ],
    'errors' => $errors,
    'warnings' => $warnings,
];

finish($report, $errors === [] ? EXIT_VALID : EXIT_INVALID_INVENTORY);
