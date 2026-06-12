<?php

declare(strict_types=1);

const PREPARE_SOURCE_SUCCESS = 0;
const PREPARE_SOURCE_FAILURE = 1;
const PREPARE_SOURCE_INPUT_ERROR = 2;

/**
 * @param array<string, mixed> $report
 */
function prepareFinish(array $report, int $exitCode): never
{
    echo json_encode(
        $report,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    ).PHP_EOL;

    exit($exitCode);
}

function prepareInputError(string $message): never
{
    prepareFinish([
        'status' => 'ERROR',
        'copied_file_count' => 0,
        'copied_total_bytes' => 0,
        'skipped_excluded_count' => 0,
        'warnings' => [],
        'errors' => [[
            'code' => 'invalid_input',
            'message' => $message,
        ]],
    ], PREPARE_SOURCE_INPUT_ERROR);
}

function prepareIsAbsolute(string $path): bool
{
    return preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2}|\/)/', $path) === 1;
}

function prepareNormalizeRelative(string $path): string
{
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#/+#', '/', $path) ?? $path;

    return trim($path, '/');
}

function prepareNormalizeAbsolute(string $path): string
{
    $path = str_replace('\\', '/', $path);
    $prefix = '';

    if (preg_match('/^[A-Za-z]:\//', $path) === 1) {
        $prefix = strtoupper(substr($path, 0, 2)).'/';
        $path = substr($path, 3);
    } elseif (str_starts_with($path, '//')) {
        $prefix = '//';
        $path = substr($path, 2);
    } elseif (str_starts_with($path, '/')) {
        $prefix = '/';
        $path = substr($path, 1);
    }

    $segments = [];

    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }

        if ($segment === '..') {
            array_pop($segments);
            continue;
        }

        $segments[] = $segment;
    }

    return rtrim($prefix.implode('/', $segments), '/');
}

function prepareCanonicalCandidate(string $path): string
{
    $normalized = prepareNormalizeAbsolute($path);
    $resolved = realpath($normalized);

    if ($resolved !== false) {
        return prepareNormalizeAbsolute($resolved);
    }

    $missing = [];
    $cursor = $normalized;

    while ($cursor !== '' && realpath($cursor) === false) {
        $parent = prepareNormalizeAbsolute(dirname($cursor));

        if ($parent === '' || $parent === $cursor) {
            break;
        }

        array_unshift($missing, basename($cursor));
        $cursor = $parent;
    }

    $resolvedParent = realpath($cursor);

    if ($resolvedParent === false) {
        return $normalized;
    }

    $candidate = prepareNormalizeAbsolute($resolvedParent);

    if ($missing !== []) {
        $candidate .= '/'.implode('/', $missing);
    }

    return prepareNormalizeAbsolute($candidate);
}

function preparePathsOverlap(string $first, string $second): bool
{
    $first = strtolower(rtrim(str_replace('\\', '/', $first), '/'));
    $second = strtolower(rtrim(str_replace('\\', '/', $second), '/'));

    return $first === $second
        || str_starts_with($first, $second.'/')
        || str_starts_with($second, $first.'/');
}

function prepareSafeRelativePath(string $path): bool
{
    if ($path === '' || str_contains($path, "\0") || prepareIsAbsolute($path)) {
        return false;
    }

    $normalized = prepareNormalizeRelative($path);

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

function prepareGlobRegex(string $pattern): string
{
    $pattern = prepareNormalizeRelative($pattern);
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
function prepareMatches(string $path, array $patterns): bool
{
    foreach ($patterns as $pattern) {
        if (preg_match(prepareGlobRegex($pattern), $path) === 1) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<string, mixed> $rules
 */
function prepareValidateRules(array $rules): ?string
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

function prepareDirectoryIsEmpty(string $path): bool
{
    $handle = opendir($path);

    if ($handle === false) {
        return false;
    }

    while (($entry = readdir($handle)) !== false) {
        if ($entry !== '.' && $entry !== '..') {
            closedir($handle);
            return false;
        }
    }

    closedir($handle);

    return true;
}

function prepareLooksTextLike(string $path): bool
{
    $basename = strtolower(basename($path));
    $lower = strtolower($path);

    if (in_array($basename, ['artisan', '.htaccess'], true)) {
        return true;
    }

    foreach ([
        'blade.php', 'css', 'csv', 'env', 'html', 'htm', 'ini', 'js', 'json',
        'lock', 'md', 'php', 'svg', 'txt', 'xml', 'yaml', 'yml',
    ] as $extension) {
        if (str_ends_with($lower, '.'.$extension)) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<string> $patterns
 */
function prepareContainsSecret(string $path, array $patterns): bool
{
    if (!prepareLooksTextLike($path) || !is_readable($path)) {
        return false;
    }

    $handle = @fopen($path, 'rb');

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
function prepareAddError(array &$errors, string $code, string $message, ?string $path = null): void
{
    $error = [
        'code' => $code,
        'message' => $message,
    ];

    if ($path !== null) {
        $error['path'] = prepareNormalizeRelative($path);
    }

    $errors[] = $error;
}

$options = getopt('', ['source:', 'workspace:', 'rules:']);

foreach (['source', 'workspace', 'rules'] as $required) {
    if (
        !isset($options[$required])
        || !is_string($options[$required])
        || trim($options[$required]) === ''
    ) {
        prepareInputError("Missing required option --{$required}.");
    }
}

$sourceInput = $options['source'];
$workspaceInput = $options['workspace'];
$rulesInput = $options['rules'];

if (!prepareIsAbsolute($sourceInput) || !prepareIsAbsolute($workspaceInput)) {
    prepareInputError('Source and workspace paths must be absolute.');
}

if (
    str_contains($sourceInput, "\0")
    || str_contains($workspaceInput, "\0")
    || preg_match('#(^|[\\\\/])\.\.([\\\\/]|$)#', $sourceInput) === 1
    || preg_match('#(^|[\\\\/])\.\.([\\\\/]|$)#', $workspaceInput) === 1
) {
    prepareInputError('Source and workspace paths must be safe and normalized.');
}

if (is_link($sourceInput)) {
    prepareInputError('Source must not be a link or reparse-like entry.');
}

$sourcePath = realpath($sourceInput);

if ($sourcePath === false || !is_dir($sourcePath)) {
    prepareInputError('The source directory does not exist.');
}

$sourcePath = prepareNormalizeAbsolute($sourcePath);
$workspacePath = prepareCanonicalCandidate($workspaceInput);

if (preparePathsOverlap($sourcePath, $workspacePath)) {
    prepareInputError('Source and workspace paths must not overlap.');
}

$rulesPath = realpath($rulesInput);

if ($rulesPath === false || !is_file($rulesPath) || !is_readable($rulesPath)) {
    prepareInputError('The rules file is missing or unreadable.');
}

try {
    $rules = json_decode((string) file_get_contents($rulesPath), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    prepareInputError('The rules file contains invalid JSON.');
}

if (!is_array($rules)) {
    prepareInputError('The rules file root must be an object.');
}

$rulesError = prepareValidateRules($rules);

if ($rulesError !== null) {
    prepareInputError($rulesError);
}

if (file_exists($workspacePath) || is_link($workspacePath)) {
    if (!is_dir($workspacePath) || is_link($workspacePath)) {
        prepareInputError('Workspace must be absent or a normal directory.');
    }

    if (!prepareDirectoryIsEmpty($workspacePath)) {
        prepareInputError('Workspace must be absent or empty.');
    }
}

$allowlist = [
    'app/**',
    'bootstrap/app.php',
    'config/**',
    'database/migrations/**',
    'resources/**',
    'routes/**',
    'artisan',
    'composer.json',
    'composer.lock',
    'package.json',
    'package-lock.json',
    'vite.config.js',
    'postcss.config.js',
    'tailwind.config.js',
    'public/.htaccess',
    'public/index.php',
    'public/favicon.ico',
    'public/manifest.json',
    'public/service-worker.js',
    'public/offline.html',
    'public/robots.txt',
    'public/jquery.js',
    'public/calibri-regular.ttf',
    'public/images/**',
    'public/js/**',
];

$exclusions = [
    '.env',
    '.env.*',
    '**/.env',
    '**/.env.*',
    '.git',
    '.git/**',
    '.github',
    '.github/**',
    'auth.json',
    '**/auth.json',
    'vendor',
    'vendor/**',
    'bootstrap/cache/packages.php',
    'bootstrap/cache/services.php',
    'public/build',
    'public/build/**',
    'node_modules',
    'node_modules/**',
    'database/*.sqlite',
    'database/*.sqlite-*',
    'database/**/*.sqlite',
    'database/**/*.sqlite-*',
    'storage',
    'storage/**',
    'uploads',
    'uploads/**',
    '**/uploads',
    '**/uploads/**',
    'backups',
    'backups/**',
    '**/backups',
    '**/backups/**',
    'logs',
    'logs/**',
    '**/logs',
    '**/logs/**',
    'public/storage',
    'public/storage/**',
    'public/uploads',
    'public/uploads/**',
    'public/hot',
    'tests',
    'tests/**',
    'docs',
    'docs/**',
    'scripts',
    'scripts/**',
    '**/*.pem',
    '**/*.key',
    '**/*.pfx',
    '**/*.p12',
    '**/id_rsa',
    '**/id_ed25519',
    '**/*private-key*',
    '**/*private_key*',
    '**/*client-secret*',
    '**/*client_secret*',
    '**/*access-token*',
    '**/*access_token*',
];

$requiredSourceFiles = [
    'artisan',
    'bootstrap/app.php',
    'composer.json',
    'composer.lock',
    'package.json',
    'package-lock.json',
    'vite.config.js',
    'resources/css/app.css',
    'resources/js/app.js',
];
$errors = [];
$warnings = [];
$candidates = [];
$skippedExcluded = 0;

foreach ($requiredSourceFiles as $requiredFile) {
    $absolutePath = $sourcePath.'/'.str_replace('/', DIRECTORY_SEPARATOR, $requiredFile);

    if (!is_file($absolutePath) || is_link($absolutePath)) {
        prepareAddError(
            $errors,
            'missing_required_source_file',
            'A required preparation source file is missing or unsafe.',
            $requiredFile
        );
    }
}

$sourceFilesystemPath = str_replace('/', DIRECTORY_SEPARATOR, $sourcePath);
$sourceRootLength = strlen($sourceFilesystemPath) + 1;

try {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceFilesystemPath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $entry) {
        $relativePath = prepareNormalizeRelative(substr($entry->getPathname(), $sourceRootLength));

        if (!prepareSafeRelativePath($relativePath)) {
            prepareAddError($errors, 'unsafe_path', 'The source contains an unsafe path.');
            continue;
        }

        $included = prepareMatches($relativePath, $allowlist);
        $excluded = prepareMatches($relativePath, $exclusions)
            || prepareMatches($relativePath, $rules['prohibited_patterns']['path_globs']);

        if ($entry->isLink()) {
            if ($included && !$excluded) {
                prepareAddError(
                    $errors,
                    'included_link',
                    'An included link or reparse-like entry is not allowed.',
                    $relativePath
                );
            } else {
                $skippedExcluded++;
            }

            continue;
        }

        if ($entry->isDir()) {
            continue;
        }

        if (!$entry->isFile() || !$included || $excluded) {
            $skippedExcluded++;
            continue;
        }

        if (prepareContainsSecret($entry->getPathname(), $rules['prohibited_patterns']['secret_content_regex'])) {
            prepareAddError(
                $errors,
                'prohibited_secret_content',
                'An included text-like file matches a prohibited secret pattern.',
                $relativePath
            );
            continue;
        }

        $candidates[] = [
            'source' => $entry->getPathname(),
            'relative' => $relativePath,
            'size' => max(0, (int) $entry->getSize()),
        ];
    }
} catch (UnexpectedValueException) {
    prepareAddError($errors, 'source_walk_failed', 'The source tree could not be inspected safely.');
}

if ($errors !== []) {
    prepareFinish([
        'status' => 'FAIL',
        'copied_file_count' => 0,
        'copied_total_bytes' => 0,
        'skipped_excluded_count' => $skippedExcluded,
        'warnings' => $warnings,
        'errors' => $errors,
    ], PREPARE_SOURCE_FAILURE);
}

if (!is_dir($workspacePath) && !mkdir($workspacePath, 0777, true) && !is_dir($workspacePath)) {
    prepareFinish([
        'status' => 'FAIL',
        'copied_file_count' => 0,
        'copied_total_bytes' => 0,
        'skipped_excluded_count' => $skippedExcluded,
        'warnings' => $warnings,
        'errors' => [[
            'code' => 'workspace_create_failed',
            'message' => 'The workspace could not be created.',
        ]],
    ], PREPARE_SOURCE_FAILURE);
}

$copiedFiles = 0;
$copiedBytes = 0;

foreach ($candidates as $candidate) {
    $destination = $workspacePath.'/'.str_replace('/', DIRECTORY_SEPARATOR, $candidate['relative']);
    $destinationDirectory = dirname($destination);

    if (
        !is_dir($destinationDirectory)
        && !mkdir($destinationDirectory, 0777, true)
        && !is_dir($destinationDirectory)
    ) {
        prepareAddError(
            $errors,
            'destination_directory_failed',
            'A workspace destination directory could not be created.',
            $candidate['relative']
        );
        break;
    }

    if (!copy($candidate['source'], $destination)) {
        prepareAddError(
            $errors,
            'copy_failed',
            'An allowlisted source file could not be copied.',
            $candidate['relative']
        );
        break;
    }

    $copiedFiles++;
    $copiedBytes += $candidate['size'];
}

prepareFinish([
    'status' => $errors === [] ? 'PASS' : 'FAIL',
    'copied_file_count' => $copiedFiles,
    'copied_total_bytes' => $copiedBytes,
    'skipped_excluded_count' => $skippedExcluded,
    'warnings' => $warnings,
    'errors' => $errors,
], $errors === [] ? PREPARE_SOURCE_SUCCESS : PREPARE_SOURCE_FAILURE);
