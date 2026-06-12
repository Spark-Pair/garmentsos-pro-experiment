[CmdletBinding()]
param(
    [string]$Source,
    [string]$Workspace,
    [string]$Staging,
    [string]$Rules,
    [string]$Client,
    [string]$ClientName,
    [string]$Version,
    [string]$Channel,
    [string]$Commit
)

$ErrorActionPreference = 'Stop'

function Write-ReportAndExit {
    param(
        [Parameter(Mandatory = $true)]
        [hashtable]$Report,

        [Parameter(Mandatory = $true)]
        [int]$ExitCode
    )

    $Report | ConvertTo-Json -Depth 12
    exit $ExitCode
}

function Write-InputError {
    param([string]$Message)

    Write-ReportAndExit -ExitCode 2 -Report @{
        status = 'ERROR'
        errors = @(
            @{
                code = 'invalid_input'
                message = $Message
            }
        )
    }
}

function Test-AbsolutePath {
    param([string]$Path)

    if ([string]::IsNullOrWhiteSpace($Path)) {
        return $false
    }

    return [System.IO.Path]::IsPathRooted($Path)
}

function Get-CanonicalCandidate {
    param([string]$Path)

    $fullPath = [System.IO.Path]::GetFullPath($Path)

    if (Test-Path -LiteralPath $fullPath) {
        return (Get-Item -LiteralPath $fullPath -Force).FullName.TrimEnd('\', '/')
    }

    $missing = New-Object System.Collections.Generic.List[string]
    $cursor = $fullPath

    while (-not (Test-Path -LiteralPath $cursor)) {
        $leaf = Split-Path -Path $cursor -Leaf

        if (-not [string]::IsNullOrEmpty($leaf)) {
            $missing.Insert(0, $leaf)
        }

        $parent = Split-Path -Path $cursor -Parent

        if ([string]::IsNullOrEmpty($parent) -or $parent -eq $cursor) {
            break
        }

        $cursor = $parent
    }

    if (Test-Path -LiteralPath $cursor) {
        $candidate = (Get-Item -LiteralPath $cursor -Force).FullName

        foreach ($segment in $missing) {
            $candidate = Join-Path -Path $candidate -ChildPath $segment
        }

        return [System.IO.Path]::GetFullPath($candidate).TrimEnd('\', '/')
    }

    return $fullPath.TrimEnd('\', '/')
}

function Test-PathsOverlap {
    param(
        [string]$First,
        [string]$Second
    )

    $firstPath = $First.TrimEnd('\', '/')
    $secondPath = $Second.TrimEnd('\', '/')
    $comparison = [System.StringComparison]::OrdinalIgnoreCase

    if ([string]::Equals($firstPath, $secondPath, $comparison)) {
        return $true
    }

    return $firstPath.StartsWith($secondPath + [System.IO.Path]::DirectorySeparatorChar, $comparison) -or
        $secondPath.StartsWith($firstPath + [System.IO.Path]::DirectorySeparatorChar, $comparison)
}

function Test-DirectoryEmpty {
    param([string]$Path)

    return $null -eq (Get-ChildItem -LiteralPath $Path -Force | Select-Object -First 1)
}

function Add-Finding {
    param(
        [System.Collections.Generic.List[object]]$Target,
        [string]$Code,
        [string]$Message,
        [string]$Path = ''
    )

    $finding = [ordered]@{
        code = $Code
        message = $Message
    }

    if (-not [string]::IsNullOrEmpty($Path)) {
        $finding.path = $Path.Replace('\', '/')
    }

    $Target.Add($finding)
}

function Get-RelativePath {
    param(
        [string]$Root,
        [string]$Path
    )

    $rootUri = New-Object System.Uri(($Root.TrimEnd('\', '/') + [System.IO.Path]::DirectorySeparatorChar))
    $pathUri = New-Object System.Uri($Path)

    return [System.Uri]::UnescapeDataString(
        $rootUri.MakeRelativeUri($pathUri).ToString()
    ).Replace('\', '/')
}

$requiredArguments = [ordered]@{
    Source = $Source
    Workspace = $Workspace
    Staging = $Staging
    Rules = $Rules
    Client = $Client
    ClientName = $ClientName
    Version = $Version
    Channel = $Channel
    Commit = $Commit
}

foreach ($argument in $requiredArguments.GetEnumerator()) {
    if ([string]::IsNullOrWhiteSpace([string]$argument.Value)) {
        Write-InputError "Missing required argument -$($argument.Key)."
    }
}

foreach ($pathArgument in @(
    @{ Name = 'Source'; Value = $Source },
    @{ Name = 'Workspace'; Value = $Workspace },
    @{ Name = 'Staging'; Value = $Staging },
    @{ Name = 'Rules'; Value = $Rules }
)) {
    if (-not (Test-AbsolutePath $pathArgument.Value)) {
        Write-InputError "$($pathArgument.Name) must be an absolute path."
    }
}

if ($Source.IndexOf([char]0) -ge 0 -or
    $Workspace.IndexOf([char]0) -ge 0 -or
    $Staging.IndexOf([char]0) -ge 0 -or
    $Rules.IndexOf([char]0) -ge 0) {
    Write-InputError 'Paths must not contain null characters.'
}

if ($Client -notmatch '^[A-Za-z0-9][A-Za-z0-9._-]*$' -or
    $Version -notmatch '^[A-Za-z0-9][A-Za-z0-9._-]*$' -or
    $Channel -notmatch '^[A-Za-z0-9][A-Za-z0-9._-]*$') {
    Write-InputError 'Client, version, and channel must use safe identifier characters.'
}

if ($Commit -notmatch '^[A-Fa-f0-9]{40}$') {
    Write-InputError 'Commit must be a full 40-character hexadecimal hash.'
}

try {
    $sourcePath = Get-CanonicalCandidate $Source
    $workspacePath = Get-CanonicalCandidate $Workspace
    $stagingPath = Get-CanonicalCandidate $Staging
    $rulesPath = Get-CanonicalCandidate $Rules
} catch {
    Write-InputError 'One or more paths could not be canonicalized safely.'
}

if (-not (Test-Path -LiteralPath $sourcePath -PathType Container)) {
    Write-InputError 'Source must be an existing directory.'
}

$sourceItem = Get-Item -LiteralPath $sourcePath -Force

if (($sourceItem.Attributes -band [System.IO.FileAttributes]::ReparsePoint) -ne 0) {
    Write-InputError 'Source must not be a link or reparse point.'
}

if (-not (Test-Path -LiteralPath $rulesPath -PathType Leaf)) {
    Write-InputError 'Rules must be an existing JSON file.'
}

if ((Test-PathsOverlap -First $sourcePath -Second $workspacePath) -or
    (Test-PathsOverlap -First $sourcePath -Second $stagingPath) -or
    (Test-PathsOverlap -First $workspacePath -Second $stagingPath)) {
    Write-InputError 'Source, workspace, and staging paths must not overlap.'
}

foreach ($plannedDirectory in @(
    @{ Name = 'Workspace'; Path = $workspacePath },
    @{ Name = 'Staging'; Path = $stagingPath }
)) {
    if (Test-Path -LiteralPath $plannedDirectory.Path) {
        $item = Get-Item -LiteralPath $plannedDirectory.Path -Force

        if (-not $item.PSIsContainer -or
            ($item.Attributes -band [System.IO.FileAttributes]::ReparsePoint) -ne 0) {
            Write-InputError "$($plannedDirectory.Name) must be absent or a normal directory."
        }

        if (-not (Test-DirectoryEmpty $plannedDirectory.Path)) {
            Write-InputError "$($plannedDirectory.Name) must be absent or empty."
        }
    }
}

try {
    $rulesObject = Get-Content -LiteralPath $rulesPath -Raw | ConvertFrom-Json
} catch {
    Write-InputError 'Rules contains invalid JSON.'
}

foreach ($section in @(
    'include_paths',
    'generated_paths',
    'create_empty_directories',
    'exclude_paths',
    'required_files',
    'required_directories'
)) {
    if ($null -eq $rulesObject.PSObject.Properties[$section] -or
        $null -eq $rulesObject.$section) {
        Write-InputError "Rules section '$section' is missing or invalid."
    }
}

$blocking = New-Object System.Collections.Generic.List[object]
$advisory = New-Object System.Collections.Generic.List[object]

$requiredInputs = @(
    'composer.json',
    'composer.lock',
    'package.json',
    'package-lock.json',
    'vite.config.js',
    'resources/css/app.css',
    'resources/js/app.js',
    'artisan',
    'bootstrap/app.php'
)

foreach ($relativePath in $requiredInputs) {
    $absolutePath = Join-Path -Path $sourcePath -ChildPath $relativePath

    if (-not (Test-Path -LiteralPath $absolutePath -PathType Leaf)) {
        Add-Finding $blocking 'missing_required_input' 'A required preparation input is missing.' $relativePath
    }
}

foreach ($jsonFile in @('composer.json', 'composer.lock', 'package.json', 'package-lock.json')) {
    $absolutePath = Join-Path -Path $sourcePath -ChildPath $jsonFile

    if (Test-Path -LiteralPath $absolutePath -PathType Leaf) {
        try {
            $null = Get-Content -LiteralPath $absolutePath -Raw | ConvertFrom-Json
        } catch {
            Add-Finding $blocking 'malformed_metadata' 'A required package metadata file contains invalid JSON.' $jsonFile
        }
    }
}

$advisoryRoots = [ordered]@{
    '.env' = 'environment_file_present'
    '.git' = 'source_control_present'
    '.github' = 'source_control_present'
    'vendor' = 'existing_vendor_ignored'
    'node_modules' = 'node_modules_ignored'
    'tests' = 'development_content_ignored'
    'docs' = 'development_content_ignored'
    'scripts' = 'development_content_ignored'
    'public/build' = 'existing_build_ignored'
}

foreach ($entry in $advisoryRoots.GetEnumerator()) {
    $path = Join-Path -Path $sourcePath -ChildPath $entry.Key

    if (Test-Path -LiteralPath $path) {
        Add-Finding $advisory $entry.Value 'Expected development-tree content will not be copied into the preparation workspace.' $entry.Key
    }
}

$unsafePathPatterns = @(
    '(?i)(^|/)(auth\.json|id_rsa|id_ed25519)$',
    '(?i)\.(pem|key|pfx|p12)$',
    '(?i)(private[-_]?key|client[-_]?secret|access[-_]?token)'
)
$secretContentPatterns = @(
    '(?i)ghp_[a-z0-9]{20,}',
    '(?i)github[_-]?token\s*[:=]',
    '(?i)(api[_-]?key|client[_-]?secret|access[_-]?token)\s*[:=]',
    '-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----'
)
$secretScanRoots = @('app', 'config', 'resources', 'routes')
$secretScanFiles = @('composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'vite.config.js', 'bootstrap/app.php')

try {
    $allEntries = @(Get-ChildItem -LiteralPath $sourcePath -Recurse -Force)

    foreach ($entry in $allEntries) {
        $relativePath = Get-RelativePath $sourcePath $entry.FullName

        if (($entry.Attributes -band [System.IO.FileAttributes]::ReparsePoint) -ne 0) {
            Add-Finding $blocking 'unsafe_link' 'The source contains a link or reparse point.' $relativePath
            continue
        }

        if (-not $entry.PSIsContainer) {
            foreach ($pattern in $unsafePathPatterns) {
                if ($relativePath -match $pattern) {
                    Add-Finding $blocking 'secret_file' 'The source contains a secret or credential-like file.' $relativePath
                    break
                }
            }
        }
    }
} catch {
    Add-Finding $blocking 'source_scan_failed' 'The source tree could not be inspected safely.'
}

$filesToScan = New-Object System.Collections.Generic.List[string]

foreach ($root in $secretScanRoots) {
    $rootPath = Join-Path -Path $sourcePath -ChildPath $root

    if (Test-Path -LiteralPath $rootPath -PathType Container) {
        foreach ($file in Get-ChildItem -LiteralPath $rootPath -File -Recurse -Force) {
            $filesToScan.Add($file.FullName)
        }
    }
}

foreach ($relativePath in $secretScanFiles) {
    $absolutePath = Join-Path -Path $sourcePath -ChildPath $relativePath

    if (Test-Path -LiteralPath $absolutePath -PathType Leaf) {
        $filesToScan.Add($absolutePath)
    }
}

foreach ($file in $filesToScan | Select-Object -Unique) {
    try {
        $bytes = [System.IO.File]::ReadAllBytes($file)

        if ($bytes.Length -gt 0 -and -not ($bytes -contains 0)) {
            $contents = [System.Text.Encoding]::UTF8.GetString($bytes)

            foreach ($pattern in $secretContentPatterns) {
                if ($contents -match $pattern) {
                    Add-Finding $blocking 'secret_content' 'An allowlisted preparation input matches a prohibited secret pattern.' (Get-RelativePath $sourcePath $file)
                    break
                }
            }
        }
    } catch {
        Add-Finding $blocking 'unreadable_input' 'An allowlisted preparation input could not be inspected.' (Get-RelativePath $sourcePath $file)
    }
}

$runtimeParent = Split-Path -Path $workspacePath -Parent
$runtimeLeaf = (Split-Path -Path $workspacePath -Leaf) + '-build-runtime'
$runtimePath = Get-CanonicalCandidate (Join-Path -Path $runtimeParent -ChildPath $runtimeLeaf)
$databasePath = Join-Path -Path $runtimePath -ChildPath 'build.sqlite'

if ((Test-PathsOverlap -First $runtimePath -Second $sourcePath) -or
    (Test-PathsOverlap -First $runtimePath -Second $workspacePath) -or
    (Test-PathsOverlap -First $runtimePath -Second $stagingPath)) {
    Add-Finding $blocking 'runtime_path_overlap' 'The planned isolated runtime path overlaps source, workspace, or staging.'
}

$preparationAllowlist = @(
    'app/**',
    'bootstrap/app.php',
    'config/**',
    'database/migrations/**',
    'resources/**',
    'routes/**',
    'public source/static assets except generated or runtime paths',
    'artisan',
    'composer.json',
    'composer.lock',
    'package.json',
    'package-lock.json',
    'vite.config.js',
    'postcss.config.js when present',
    'tailwind.config.js when present'
)

$plannedExclusions = @(
    '.env',
    '.env.*',
    '.git',
    '.github',
    'database/*.sqlite*',
    'storage/app/**',
    'storage/logs/**',
    'storage/framework/cache/**',
    'storage/framework/sessions/**',
    'storage/framework/views/**',
    'uploads/**',
    'backups/**',
    'logs/**',
    'public/storage',
    'public/uploads',
    'public/hot',
    'public/build',
    'vendor',
    'node_modules',
    'tests',
    'docs',
    'scripts',
    'credentials, private keys, tokens, and secrets'
)

$composerCommands = @(
    'composer validate --strict --no-check-publish',
    'composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --no-scripts',
    'php artisan package:discover --ansi'
)
$npmCommands = @(
    'npm ci',
    'npm run build'
)
$environment = [ordered]@{
    APP_ENV = 'production'
    APP_DEBUG = 'false'
    APP_KEY = '<ephemeral-build-key>'
    APP_URL = 'http://localhost'
    DB_CONNECTION = 'sqlite'
    DB_DATABASE = $databasePath
    CACHE_DRIVER = 'array'
    SESSION_DRIVER = 'array'
    QUEUE_CONNECTION = 'sync'
    MAIL_MAILER = 'log'
    LOG_CHANNEL = 'stderr'
    FILESYSTEM_DISK = 'local'
    GARMENTSOS_EXTERNAL_MODE = 'false'
}

$reportStatus = if ($blocking.Count -eq 0) { 'SAFE' } else { 'UNSAFE' }
$reportExitCode = if ($blocking.Count -eq 0) { 0 } else { 1 }
$blockingReport = @($blocking | ForEach-Object { $_ })
$advisoryReport = @($advisory | ForEach-Object { $_ })

$report = [ordered]@{
    status = $reportStatus
    dry_run = $true
    paths = [ordered]@{
        source = $sourcePath
        workspace = $workspacePath
        staging = $stagingPath
        rules = $rulesPath
        build_runtime = $runtimePath
    }
    target = [ordered]@{
        client_id = $Client
        client_name = $ClientName
        version = $Version
        channel = $Channel
        source_commit = $Commit.ToLowerInvariant()
    }
    findings = [ordered]@{
        blocking = $blockingReport
        advisory = $advisoryReport
    }
    plan = [ordered]@{
        preparation_allowlist = $preparationAllowlist
        exclusions = $plannedExclusions
        environment = $environment
        composer_commands = $composerCommands
        npm_commands = $npmCommands
        execution_enabled = $false
        creates_directories = $false
        copies_files = $false
    }
}

Write-ReportAndExit -Report $report -ExitCode $reportExitCode
