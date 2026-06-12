<?php

declare(strict_types=1);

const STAGE_SUCCESS = 0;
const STAGE_FAILURE = 1;
const STAGE_INPUT_ERROR = 2;

/**
 * @param array<string, mixed> $payload
 */
function stageFinish(array $payload, int $exitCode): never
{
    echo json_encode(
        $payload,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    ).PHP_EOL;

    exit($exitCode);
}

function stageIsAbsolute(string $path): bool
{
    return preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2}|\/)/', $path) === 1;
}

function stageNormalizeRelative(string $path): string
{
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#/+#', '/', $path) ?? $path;

    return trim($path, '/');
}

function stageNormalizeAbsolute(string $path): string
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

function stageCanonicalCandidate(string $path): string
{
    $normalized = stageNormalizeAbsolute($path);
    $resolved = realpath($normalized);

    if ($resolved !== false) {
        return stageNormalizeAbsolute($resolved);
    }

    $missingSegments = [];
    $cursor = $normalized;

    while ($cursor !== '' && realpath($cursor) === false) {
        $parent = stageNormalizeAbsolute(dirname($cursor));

        if ($parent === $cursor || $parent === '') {
            break;
        }

        array_unshift($missingSegments, basename($cursor));
        $cursor = $parent;
    }

    $resolvedParent = realpath($cursor);

    if ($resolvedParent === false) {
        return $normalized;
    }

    $candidate = stageNormalizeAbsolute($resolvedParent);

    if ($missingSegments !== []) {
        $candidate .= '/'.implode('/', $missingSegments);
    }

    return stageNormalizeAbsolute($candidate);
}

function stagePathsOverlap(string $first, string $second): bool
{
    $first = strtolower(rtrim(str_replace('\\', '/', stageNormalizeAbsolute($first)), '/'));
    $second = strtolower(rtrim(str_replace('\\', '/', stageNormalizeAbsolute($second)), '/'));

    return $first === $second
        || str_starts_with($first, $second.'/')
        || str_starts_with($second, $first.'/');
}

function stageGlobRegex(string $pattern): string
{
    $pattern = stageNormalizeRelative($pattern);
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
function stageMatchesGlob(string $path, array $patterns): bool
{
    foreach ($patterns as $pattern) {
        if (preg_match(stageGlobRegex($pattern), $path) === 1) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<string> $patterns
 */
function stageRegexListIsValid(array $patterns): bool
{
    foreach ($patterns as $pattern) {
        if (@preg_match('~'.$pattern.'~', '') === false) {
            return false;
        }
    }

    return true;
}

/**
 * @param array<string, mixed> $rules
 */
function stageValidateRules(array $rules): ?string
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

    if (!isset($rules['prohibited_patterns']) || !is_array($rules['prohibited_patterns'])) {
        return "Rules section 'prohibited_patterns' must be an object.";
    }

    foreach (['path_globs', 'archive_entry_regex', 'secret_content_regex'] as $section) {
        $values = $rules['prohibited_patterns'][$section] ?? null;

        if (!is_array($values) || !array_is_list($values)) {
            return "Rules section 'prohibited_patterns.{$section}' must be a list.";
        }

        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                return "Rules section 'prohibited_patterns.{$section}' must contain non-empty strings.";
            }
        }
    }

    if (
        !stageRegexListIsValid($rules['prohibited_patterns']['archive_entry_regex'])
        || !stageRegexListIsValid($rules['prohibited_patterns']['secret_content_regex'])
    ) {
        return 'Rules contain an invalid regular expression.';
    }

    $manifestFields = $rules['manifest_fields']['embedded_release_info'] ?? null;

    if (!is_array($manifestFields) || !array_is_list($manifestFields)) {
        return "Rules section 'manifest_fields.embedded_release_info' must be a list.";
    }

    return null;
}

function stageLooksTextLike(string $path): bool
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
function stageContainsSecret(string $path, array $patterns): bool
{
    if (!stageLooksTextLike($path) || !is_readable($path)) {
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

function stageDirectoryIsEmpty(string $path): bool
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

function stageWriteFailure(string $message, int $exitCode = STAGE_FAILURE): never
{
    stageFinish([
        'status' => $exitCode === STAGE_INPUT_ERROR ? 'ERROR' : 'FAIL',
        'message' => $message,
    ], $exitCode);
}

$options = getopt('', [
    'source:',
    'staging:',
    'rules:',
    'client:',
    'client-name:',
    'version:',
    'channel:',
    'commit:',
]);

foreach (['source', 'staging', 'rules', 'client', 'client-name', 'version', 'channel', 'commit'] as $option) {
    if (
        !array_key_exists($option, $options)
        || !is_string($options[$option])
        || trim($options[$option]) === ''
    ) {
        stageWriteFailure("Missing required option --{$option}.", STAGE_INPUT_ERROR);
    }
}

$sourceInput = $options['source'];
$stagingInput = $options['staging'];
$rulesInput = $options['rules'];
$client = trim($options['client']);
$clientName = trim($options['client-name']);
$version = trim($options['version']);
$channel = trim($options['channel']);
$commit = trim($options['commit']);

if (!stageIsAbsolute($sourceInput) || !stageIsAbsolute($stagingInput)) {
    stageWriteFailure('Source and staging paths must be absolute.', STAGE_INPUT_ERROR);
}

if (preg_match('/^[a-f0-9]{40}$/i', $commit) !== 1) {
    stageWriteFailure('The source commit must be a full 40-character hexadecimal hash.', STAGE_INPUT_ERROR);
}

foreach ([$client, $version, $channel] as $identifier) {
    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $identifier) !== 1) {
        stageWriteFailure('Client, version, and channel must use safe identifier characters.', STAGE_INPUT_ERROR);
    }
}

$sourcePath = realpath($sourceInput);

if ($sourcePath === false || !is_dir($sourcePath)) {
    stageWriteFailure('The source directory does not exist.', STAGE_INPUT_ERROR);
}

$sourcePath = stageNormalizeAbsolute($sourcePath);
$stagingPath = stageCanonicalCandidate($stagingInput);

if (stagePathsOverlap($sourcePath, $stagingPath)) {
    stageWriteFailure('Source and staging directories must not overlap.', STAGE_INPUT_ERROR);
}

$rulesPath = realpath($rulesInput);

if ($rulesPath === false || !is_file($rulesPath) || !is_readable($rulesPath)) {
    stageWriteFailure('The rules file is missing or unreadable.', STAGE_INPUT_ERROR);
}

try {
    $rules = json_decode((string) file_get_contents($rulesPath), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    stageWriteFailure('The rules file contains invalid JSON.', STAGE_INPUT_ERROR);
}

if (!is_array($rules)) {
    stageWriteFailure('The rules file root must be an object.', STAGE_INPUT_ERROR);
}

$rulesError = stageValidateRules($rules);

if ($rulesError !== null) {
    stageWriteFailure($rulesError, STAGE_INPUT_ERROR);
}

/** @var list<string> $includePaths */
$includePaths = $rules['include_paths'];
/** @var list<string> $generatedPaths */
$generatedPaths = $rules['generated_paths'];
/** @var list<string> $emptyDirectories */
$emptyDirectories = $rules['create_empty_directories'];
/** @var list<string> $excludePaths */
$excludePaths = $rules['exclude_paths'];
/** @var list<string> $requiredFiles */
$requiredFiles = $rules['required_files'];
/** @var list<string> $requiredDirectories */
$requiredDirectories = $rules['required_directories'];
/** @var list<string> $prohibitedPathGlobs */
$prohibitedPathGlobs = $rules['prohibited_patterns']['path_globs'];
/** @var list<string> $secretPatterns */
$secretPatterns = $rules['prohibited_patterns']['secret_content_regex'];

foreach ($requiredFiles as $requiredFile) {
    $relativePath = stageNormalizeRelative($requiredFile);

    if ($relativePath === 'release-info.json') {
        continue;
    }

    $sourceFile = $sourcePath.'/'.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (!is_file($sourceFile) || is_link($sourceFile)) {
        stageWriteFailure("Required source file is missing or unsafe: {$relativePath}");
    }
}

foreach ($requiredDirectories as $requiredDirectory) {
    $relativePath = stageNormalizeRelative($requiredDirectory);

    if (in_array($relativePath, $emptyDirectories, true)) {
        continue;
    }

    $sourceDirectory = $sourcePath.'/'.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (!is_dir($sourceDirectory) || is_link($sourceDirectory)) {
        stageWriteFailure("Required source directory is missing or unsafe: {$relativePath}");
    }
}

if (file_exists($stagingPath) || is_link($stagingPath)) {
    if (!is_dir($stagingPath) || is_link($stagingPath)) {
        stageWriteFailure('The staging path must be a normal directory.', STAGE_INPUT_ERROR);
    }

    if (!stageDirectoryIsEmpty($stagingPath)) {
        stageWriteFailure('The staging directory must be empty.', STAGE_INPUT_ERROR);
    }
} elseif (!mkdir($stagingPath, 0777, true) && !is_dir($stagingPath)) {
    stageWriteFailure('The staging directory could not be created.');
}

$copiedFiles = 0;
$copiedBytes = 0;
$sourceRootLength = strlen(str_replace('/', DIRECTORY_SEPARATOR, $sourcePath)) + 1;
$filesystemSource = str_replace('/', DIRECTORY_SEPARATOR, $sourcePath);

try {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($filesystemSource, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $entry) {
        $relativePath = stageNormalizeRelative(substr($entry->getPathname(), $sourceRootLength));
        $excluded = stageMatchesGlob($relativePath, $excludePaths)
            || stageMatchesGlob($relativePath, $prohibitedPathGlobs);
        $included = stageMatchesGlob($relativePath, $includePaths)
            || (
                $relativePath !== 'release-info.json'
                && stageMatchesGlob($relativePath, $generatedPaths)
            );

        if ($entry->isLink()) {
            if ($included && !$excluded) {
                stageWriteFailure("Included link or reparse-like entry is not allowed: {$relativePath}");
            }

            continue;
        }

        if ($entry->isDir() || !$entry->isFile() || !$included || $excluded) {
            continue;
        }

        if (stageContainsSecret($entry->getPathname(), $secretPatterns)) {
            stageWriteFailure("An included text-like file matches a prohibited secret pattern: {$relativePath}");
        }

        $destination = $stagingPath.'/'.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $destinationDirectory = dirname($destination);

        if (!is_dir($destinationDirectory) && !mkdir($destinationDirectory, 0777, true) && !is_dir($destinationDirectory)) {
            stageWriteFailure("Could not create a staging directory for: {$relativePath}");
        }

        if (!copy($entry->getPathname(), $destination)) {
            stageWriteFailure("Could not copy an allowlisted source file: {$relativePath}");
        }

        $copiedFiles++;
        $size = $entry->getSize();
        $copiedBytes += $size === false ? 0 : $size;
    }
} catch (UnexpectedValueException) {
    stageWriteFailure('The source tree could not be walked safely.');
}

foreach ($emptyDirectories as $emptyDirectory) {
    $relativePath = stageNormalizeRelative($emptyDirectory);
    $destination = $stagingPath.'/'.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (!is_dir($destination) && !mkdir($destination, 0777, true) && !is_dir($destination)) {
        stageWriteFailure("Could not create required empty directory: {$relativePath}");
    }
}

foreach ($requiredDirectories as $requiredDirectory) {
    $relativePath = stageNormalizeRelative($requiredDirectory);
    $destination = $stagingPath.'/'.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (!is_dir($destination) && !mkdir($destination, 0777, true) && !is_dir($destination)) {
        stageWriteFailure("Could not create required release directory: {$relativePath}");
    }
}

$migrationDirectory = $stagingPath.'/database/migrations';
$migrations = [];

if (is_dir($migrationDirectory)) {
    foreach (new DirectoryIterator($migrationDirectory) as $entry) {
        if ($entry->isFile() && !$entry->isLink() && str_ends_with(strtolower($entry->getFilename()), '.php')) {
            $migrations[] = $entry->getFilename();
        }
    }
}

sort($migrations, SORT_STRING);

$releaseInfo = [
    'schema_version' => 1,
    'app' => [
        'name' => 'GarmentsOS PRO',
        'version' => $version,
    ],
    'target' => [
        'client_id' => $client,
        'client_name' => $clientName,
        'channel' => $channel,
    ],
    'build' => [
        'built_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'source_commit' => strtolower($commit),
    ],
    'database' => [
        'migrations_included' => $migrations !== [],
        'migration_count' => count($migrations),
        'migrations' => $migrations,
    ],
];

$releaseInfoPath = $stagingPath.'/release-info.json';
$releaseInfoJson = json_encode(
    $releaseInfo,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
).PHP_EOL;

if (file_put_contents($releaseInfoPath, $releaseInfoJson, LOCK_EX) === false) {
    stageWriteFailure('Could not write release-info.json.');
}

$validatorPath = __DIR__.DIRECTORY_SEPARATOR.'validate-release-inventory.php';

if (!is_file($validatorPath)) {
    stageWriteFailure('The release inventory validator is unavailable.');
}

$command = [
    PHP_BINARY,
    $validatorPath,
    '--staging='.$stagingPath,
    '--rules='.$rulesPath,
    '--client='.$client,
    '--version='.$version,
    '--channel='.$channel,
];
$escapedCommand = implode(' ', array_map('escapeshellarg', $command));
$descriptorSpec = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];
$process = proc_open($escapedCommand, $descriptorSpec, $pipes);

if (!is_resource($process)) {
    stageWriteFailure('The release inventory validator could not be started.');
}

fclose($pipes[0]);
$validatorOutput = stream_get_contents($pipes[1]);
$validatorError = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$validatorExitCode = proc_close($process);

try {
    $validatorReport = json_decode((string) $validatorOutput, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    stageWriteFailure('The release inventory validator returned an invalid response.');
}

if ($validatorExitCode !== 0 || !is_array($validatorReport) || ($validatorReport['status'] ?? null) !== 'PASS') {
    stageFinish([
        'status' => 'FAIL',
        'message' => 'The staged release failed inventory validation.',
        'staging' => $stagingPath,
        'validator' => is_array($validatorReport) ? $validatorReport : ['status' => 'ERROR'],
    ], STAGE_FAILURE);
}

stageFinish([
    'status' => 'PASS',
    'staging' => $stagingPath,
    'target' => [
        'client_id' => $client,
        'client_name' => $clientName,
        'version' => $version,
        'channel' => $channel,
        'source_commit' => strtolower($commit),
    ],
    'copied' => [
        'file_count' => $copiedFiles,
        'total_size_bytes' => $copiedBytes,
    ],
    'inventory' => $validatorReport['inventory'] ?? null,
], STAGE_SUCCESS);
