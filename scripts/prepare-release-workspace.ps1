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

function Write-JsonAndExit {
    param([hashtable]$Report, [int]$ExitCode)

    $Report | ConvertTo-Json -Depth 12
    exit $ExitCode
}

function Stop-Input {
    param([string]$Message)

    Write-JsonAndExit @{
        status = 'ERROR'
        phase = 'input'
        message = $Message
    } 2
}

function Stop-Operation {
    param(
        [string]$Phase,
        [string]$Message,
        [string]$CommandLabel = '',
        [int]$CommandExitCode = 1,
        [string]$LogPath = ''
    )

    $report = @{
        status = 'FAIL'
        phase = $Phase
        message = $Message
    }

    if ($CommandLabel -ne '') {
        $report.command_label = $CommandLabel
        $report.command_exit_code = $CommandExitCode
    }

    if ($LogPath -ne '') {
        $report.log_path = $LogPath
    }

    Write-JsonAndExit $report 1
}

function Test-AbsolutePath {
    param([string]$Path)

    return -not [string]::IsNullOrWhiteSpace($Path) -and [System.IO.Path]::IsPathRooted($Path)
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

        if ($leaf -ne '') {
            $missing.Insert(0, $leaf)
        }

        $parent = Split-Path -Path $cursor -Parent

        if ($parent -eq '' -or $parent -eq $cursor) {
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
    param([string]$First, [string]$Second)

    $firstPath = $First.TrimEnd('\', '/')
    $secondPath = $Second.TrimEnd('\', '/')
    $comparison = [System.StringComparison]::OrdinalIgnoreCase

    return [string]::Equals($firstPath, $secondPath, $comparison) -or
        $firstPath.StartsWith($secondPath + [System.IO.Path]::DirectorySeparatorChar, $comparison) -or
        $secondPath.StartsWith($firstPath + [System.IO.Path]::DirectorySeparatorChar, $comparison)
}

function Quote-ProcessArgument {
    param([string]$Argument)

    if ($Argument -notmatch '[\s"]') {
        return $Argument
    }

    return '"' + ($Argument -replace '(\\*)"', '$1$1\"' -replace '(\\+)$', '$1$1') + '"'
}

function Resolve-Application {
    param([string]$Name)

    $command = Get-Command $Name -CommandType Application -ErrorAction SilentlyContinue | Select-Object -First 1

    if ($null -eq $command) {
        return $null
    }

    return $command.Source
}

function Get-ComposerInvocation {
    param([string]$PhpExecutable)

    $composerExe = Resolve-Application 'composer.exe'

    if ($null -ne $composerExe) {
        return @{
            executable = $composerExe
            prefix = @()
        }
    }

    $composerCommand = Get-Command composer -ErrorAction SilentlyContinue | Select-Object -First 1

    if ($null -eq $composerCommand) {
        return $null
    }

    $composerPhar = Join-Path -Path (Split-Path -Path $composerCommand.Source -Parent) -ChildPath 'composer.phar'

    if (-not (Test-Path -LiteralPath $composerPhar -PathType Leaf)) {
        return $null
    }

    return @{
        executable = $PhpExecutable
        prefix = @($composerPhar)
    }
}

function Get-NpmInvocation {
    $npmExe = Resolve-Application 'npm.exe'

    if ($null -ne $npmExe) {
        return @{
            executable = $npmExe
            prefix = @()
        }
    }

    $nodeExecutable = Resolve-Application 'node.exe'

    if ($null -eq $nodeExecutable) {
        return $null
    }

    $npmCommand = Get-Command npm -ErrorAction SilentlyContinue | Select-Object -First 1

    if ($null -eq $npmCommand) {
        return $null
    }

    $nodeRoot = Split-Path -Path $npmCommand.Source -Parent
    $npmCli = Join-Path -Path $nodeRoot -ChildPath 'node_modules\npm\bin\npm-cli.js'

    if (-not (Test-Path -LiteralPath $npmCli -PathType Leaf)) {
        return $null
    }

    return @{
        executable = $nodeExecutable
        prefix = @($npmCli)
    }
}

function Test-AllowedCommand {
    param([string]$Label, [string[]]$Arguments)

    $joined = $Arguments -join "`n"
    $allowed = @{
        prepare_source = @('prepare-release-source.php', '--source=', '--workspace=', '--rules=')
        composer_validate = @('validate', '--strict', '--no-check-publish')
        composer_install = @('install', '--no-dev', '--prefer-dist', '--optimize-autoloader', '--no-interaction', '--no-progress', '--no-scripts')
        package_discover = @('artisan', 'package:discover', '--ansi')
        npm_ci = @('ci')
        npm_build = @('run', 'build')
        post_build_check = @('check-release-prerequisites.php', '--source=', '--rules=', '--validate-build=true')
        stage_release = @('stage-release.php', '--source=', '--staging=', '--rules=', '--client=', '--client-name=', '--version=', '--channel=', '--commit=')
    }

    if (-not $allowed.ContainsKey($Label)) {
        return $false
    }

    foreach ($token in $allowed[$Label]) {
        if ($joined.IndexOf($token, [System.StringComparison]::OrdinalIgnoreCase) -lt 0) {
            return $false
        }
    }

    $forbidden = @(
        'composer update',
        'npm install',
        'migrate',
        'artisan db:',
        'artisan schema:',
        'artisan serve',
        'queue:',
        'schedule:',
        'backup',
        'restore',
        'storage:link',
        'updater',
        'installer'
    )

    foreach ($term in $forbidden) {
        if ($joined.IndexOf($term, [System.StringComparison]::OrdinalIgnoreCase) -ge 0) {
            return $false
        }
    }

    return $true
}

function New-ControlledEnvironment {
    param(
        [string]$AppKey,
        [string]$DatabasePath,
        [string]$RuntimePath,
        [string]$CommandLabel
    )

    $environment = @{}

    foreach ($name in @('SystemRoot', 'WINDIR', 'PATH', 'PATHEXT', 'TEMP', 'TMP', 'USERPROFILE', 'APPDATA', 'LOCALAPPDATA')) {
        $value = [System.Environment]::GetEnvironmentVariable($name)

        if ($null -ne $value) {
            $environment[$name] = $value
        }
    }

    $environment['APP_ENV'] = 'production'
    $environment['APP_DEBUG'] = 'false'
    $environment['APP_KEY'] = $AppKey
    $environment['APP_URL'] = 'http://localhost'
    $environment['DB_CONNECTION'] = 'sqlite'
    $environment['DB_DATABASE'] = $DatabasePath
    $environment['CACHE_DRIVER'] = 'array'
    $environment['SESSION_DRIVER'] = 'array'
    $environment['QUEUE_CONNECTION'] = 'sync'
    $environment['MAIL_MAILER'] = 'log'
    $environment['LOG_CHANNEL'] = 'stderr'
    $environment['FILESYSTEM_DISK'] = 'local'
    $environment['GARMENTSOS_EXTERNAL_MODE'] = 'false'
    $environment['VIEW_COMPILED_PATH'] = Join-Path -Path $RuntimePath -ChildPath 'views'
    $environment['COMPOSER_HOME'] = Join-Path -Path $RuntimePath -ChildPath 'composer-home'
    $environment['NPM_CONFIG_CACHE'] = Join-Path -Path $RuntimePath -ChildPath 'npm-cache'
    $environment['GARMENTSOS_COMMAND_LABEL'] = $CommandLabel

    foreach ($testName in @('GARMENTSOS_TEST_REAL_PHP', 'GARMENTSOS_TEST_SHIM_LOG', 'GARMENTSOS_TEST_FAIL_LABEL')) {
        $testValue = [System.Environment]::GetEnvironmentVariable($testName)

        if ($null -ne $testValue) {
            $environment[$testName] = $testValue
        }
    }

    return $environment
}

function Invoke-StructuredProcess {
    param(
        [string]$Label,
        [string]$Executable,
        [string[]]$Arguments,
        [string]$WorkingDirectory,
        [hashtable]$Environment,
        [string]$LogPath,
        [string]$Secret
    )

    if (-not (Test-AllowedCommand -Label $Label -Arguments $Arguments)) {
        Stop-Operation 'command_validation' 'A command did not match the hardcoded allowlist.' $Label 1 $LogPath
    }

    $startInfo = New-Object System.Diagnostics.ProcessStartInfo
    $startInfo.FileName = $Executable
    $startInfo.Arguments = (($Arguments | ForEach-Object { Quote-ProcessArgument $_ }) -join ' ')
    $startInfo.WorkingDirectory = $WorkingDirectory
    $startInfo.UseShellExecute = $false
    $startInfo.RedirectStandardOutput = $true
    $startInfo.RedirectStandardError = $true
    $startInfo.CreateNoWindow = $true
    $startInfo.EnvironmentVariables.Clear()

    foreach ($entry in $Environment.GetEnumerator()) {
        $startInfo.EnvironmentVariables[$entry.Key] = [string]$entry.Value
    }

    $process = New-Object System.Diagnostics.Process
    $process.StartInfo = $startInfo

    if (-not $process.Start()) {
        Stop-Operation 'command_start' 'A command could not be started.' $Label 1 $LogPath
    }

    $stdout = $process.StandardOutput.ReadToEnd()
    $stderr = $process.StandardError.ReadToEnd()
    $process.WaitForExit()
    $exitCode = $process.ExitCode
    $safeOutput = (($stdout + $stderr).Replace($Secret, '<redacted>'))
    if ($LogPath -ne '') {
        [System.IO.File]::WriteAllText($LogPath, $safeOutput, [System.Text.Encoding]::UTF8)
    }

    return @{
        exit_code = $exitCode
        output = $stdout
        log_path = $LogPath
    }
}

function Get-PreparationSourceHashes {
    param([string]$Root)

    $hashes = @{}
    $directoryRoots = @('app', 'config', 'database\migrations', 'resources', 'routes', 'public\images', 'public\js')
    $exactFiles = @(
        'bootstrap\app.php',
        'artisan',
        'composer.json',
        'composer.lock',
        'package.json',
        'package-lock.json',
        'vite.config.js',
        'postcss.config.js',
        'tailwind.config.js',
        'public\.htaccess',
        'public\index.php',
        'public\favicon.ico',
        'public\manifest.json',
        'public\service-worker.js',
        'public\offline.html',
        'public\robots.txt',
        'public\jquery.js',
        'public\calibri-regular.ttf'
    )

    foreach ($directory in $directoryRoots) {
        $path = Join-Path -Path $Root -ChildPath $directory

        if (Test-Path -LiteralPath $path -PathType Container) {
            foreach ($file in Get-ChildItem -LiteralPath $path -File -Recurse -Force) {
                if (($file.Attributes -band [System.IO.FileAttributes]::ReparsePoint) -ne 0) {
                    Stop-Operation 'source_hash' 'An allowlisted source link cannot be hashed safely.'
                }

                $relative = $file.FullName.Substring($Root.TrimEnd('\').Length + 1).Replace('\', '/')
                $hashes[$relative] = (Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash
            }
        }
    }

    foreach ($relative in $exactFiles) {
        $path = Join-Path -Path $Root -ChildPath $relative

        if (Test-Path -LiteralPath $path -PathType Leaf) {
            $normalized = $relative.Replace('\', '/')
            $hashes[$normalized] = (Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash
        }
    }

    return $hashes
}

function Test-HashMapsEqual {
    param([hashtable]$Before, [hashtable]$After)

    if ($Before.Count -ne $After.Count) {
        return $false
    }

    foreach ($key in $Before.Keys) {
        if (-not $After.ContainsKey($key) -or $Before[$key] -ne $After[$key]) {
            return $false
        }
    }

    return $true
}

function Remove-VerifiedNodeModules {
    param(
        [string]$WorkspacePath,
        [string]$SourcePath,
        [string]$StagingPath
    )

    $workspaceCanonical = (Get-Item -LiteralPath $WorkspacePath -Force).FullName.TrimEnd('\', '/')
    $workspaceItem = Get-Item -LiteralPath $workspaceCanonical -Force
    $root = [System.IO.Path]::GetPathRoot($workspaceCanonical).TrimEnd('\', '/')

    if ($workspaceCanonical -eq $root -or
        (Test-PathsOverlap -First $workspaceCanonical -Second $SourcePath) -or
        (Test-PathsOverlap -First $workspaceCanonical -Second $StagingPath) -or
        ($workspaceItem.Attributes -band [System.IO.FileAttributes]::ReparsePoint) -ne 0) {
        Stop-Operation 'node_modules_cleanup' 'Workspace failed node_modules deletion safety checks.'
    }

    $target = Join-Path -Path $workspaceCanonical -ChildPath 'node_modules'

    if (-not (Test-Path -LiteralPath $target)) {
        return
    }

    $targetItem = Get-Item -LiteralPath $target -Force
    $targetCanonical = $targetItem.FullName.TrimEnd('\', '/')
    $targetParent = (Split-Path -Path $targetCanonical -Parent).TrimEnd('\', '/')

    if (-not $targetItem.PSIsContainer -or
        $targetItem.Name -cne 'node_modules' -or
        -not [string]::Equals($targetParent, $workspaceCanonical, [System.StringComparison]::OrdinalIgnoreCase) -or
        ($targetItem.Attributes -band [System.IO.FileAttributes]::ReparsePoint) -ne 0) {
        Stop-Operation 'node_modules_cleanup' 'node_modules failed canonical deletion safety checks.'
    }

    Remove-Item -LiteralPath $targetCanonical -Recurse -Force
}

$required = @{
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

foreach ($entry in $required.GetEnumerator()) {
    if ([string]::IsNullOrWhiteSpace([string]$entry.Value)) {
        Stop-Input "Missing required argument -$($entry.Key)."
    }
}

foreach ($path in @($Source, $Workspace, $Staging, $Rules)) {
    if (-not (Test-AbsolutePath $path)) {
        Stop-Input 'Source, workspace, staging, and rules paths must be absolute.'
    }
}

try {
    $sourcePath = Get-CanonicalCandidate $Source
    $workspacePath = Get-CanonicalCandidate $Workspace
    $stagingPath = Get-CanonicalCandidate $Staging
    $rulesPath = Get-CanonicalCandidate $Rules
} catch {
    Stop-Input 'Paths could not be canonicalized.'
}

if (-not (Test-Path -LiteralPath $sourcePath -PathType Container)) {
    Stop-Input 'Source must be an existing directory.'
}

foreach ($scriptName in @(
    'prepare-release-workspace-dry-run.ps1',
    'prepare-release-source.php',
    'check-release-prerequisites.php',
    'stage-release.php',
    'validate-release-inventory.php'
)) {
    if (-not (Test-Path -LiteralPath (Join-Path $sourcePath "scripts\$scriptName") -PathType Leaf)) {
        Stop-Input "Required source script is missing: $scriptName"
    }
}

$dryRunScript = Join-Path $sourcePath 'scripts\prepare-release-workspace-dry-run.ps1'
$dryRunArguments = @(
    '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', $dryRunScript,
    '-Source', $sourcePath, '-Workspace', $workspacePath, '-Staging', $stagingPath,
    '-Rules', $rulesPath, '-Client', $Client, '-ClientName', $ClientName,
    '-Version', $Version, '-Channel', $Channel, '-Commit', $Commit
)
$dryRunInfo = New-Object System.Diagnostics.ProcessStartInfo
$dryRunInfo.FileName = (Get-Process -Id $PID).Path
$dryRunInfo.Arguments = (($dryRunArguments | ForEach-Object { Quote-ProcessArgument $_ }) -join ' ')
$dryRunInfo.WorkingDirectory = $sourcePath
$dryRunInfo.UseShellExecute = $false
$dryRunInfo.RedirectStandardOutput = $true
$dryRunInfo.RedirectStandardError = $true
$dryRunInfo.CreateNoWindow = $true
$dryRunProcess = New-Object System.Diagnostics.Process
$dryRunProcess.StartInfo = $dryRunInfo
$null = $dryRunProcess.Start()
$dryRunOutput = $dryRunProcess.StandardOutput.ReadToEnd()
$dryRunError = $dryRunProcess.StandardError.ReadToEnd()
$dryRunProcess.WaitForExit()

try {
    $dryRunReport = $dryRunOutput | ConvertFrom-Json
} catch {
    Stop-Operation 'dry_run' 'Dry-run returned invalid JSON.'
}

if ($dryRunProcess.ExitCode -ne 0 -or $dryRunReport.status -ne 'SAFE') {
    Stop-Operation 'dry_run' 'Dry-run did not approve workspace preparation.' 'dry_run' $dryRunProcess.ExitCode
}

$sourceHashesBefore = Get-PreparationSourceHashes $sourcePath
$phpExecutable = Resolve-Application 'php.exe'

if ($null -eq $phpExecutable) {
    $phpExecutable = Resolve-Application 'php'
}

$composerInvocation = Get-ComposerInvocation $phpExecutable
$npmInvocation = Get-NpmInvocation

if ($null -eq $phpExecutable -or $null -eq $composerInvocation -or $null -eq $npmInvocation) {
    Stop-Operation 'tool_resolution' 'PHP, Composer, or npm could not be resolved safely.'
}

$prepareArguments = @(
    (Join-Path $sourcePath 'scripts\prepare-release-source.php'),
    "--source=$sourcePath",
    "--workspace=$workspacePath",
    "--rules=$rulesPath"
)
$temporaryEnvironment = New-ControlledEnvironment '<not-used>' (Join-Path (Split-Path $workspacePath -Parent) 'not-created.sqlite') (Split-Path $workspacePath -Parent) 'prepare_source'
$prepareResult = Invoke-StructuredProcess 'prepare_source' $phpExecutable $prepareArguments $sourcePath $temporaryEnvironment '' '<not-used>'

if ($prepareResult.exit_code -ne 0) {
    Stop-Operation 'prepare_source' 'Source preparation failed.' 'prepare_source' $prepareResult.exit_code
}

$runtimePath = Get-CanonicalCandidate (Join-Path (Split-Path $workspacePath -Parent) ((Split-Path $workspacePath -Leaf) + '-build-runtime'))

if ((Test-PathsOverlap $runtimePath $sourcePath) -or
    (Test-PathsOverlap $runtimePath $workspacePath) -or
    (Test-PathsOverlap $runtimePath $stagingPath)) {
    Stop-Operation 'runtime_create' 'Build runtime path overlaps a protected path.'
}

if (Test-Path -LiteralPath $runtimePath) {
    if (-not (Get-Item -LiteralPath $runtimePath -Force).PSIsContainer -or
        $null -ne (Get-ChildItem -LiteralPath $runtimePath -Force | Select-Object -First 1)) {
        Stop-Operation 'runtime_create' 'Build runtime must be absent or empty.'
    }
} else {
    $null = New-Item -ItemType Directory -Path $runtimePath
}

foreach ($directory in @('views', 'logs', 'composer-home', 'npm-cache')) {
    $null = New-Item -ItemType Directory -Path (Join-Path $runtimePath $directory)
}

$databasePath = Join-Path $runtimePath 'build.sqlite'
$null = New-Item -ItemType File -Path $databasePath
$logsPath = Join-Path $runtimePath 'logs'
[System.IO.File]::WriteAllText((Join-Path $logsPath '01-prepare-source.log'), $prepareResult.output, [System.Text.Encoding]::UTF8)
$keyBytes = New-Object byte[] 32
[System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($keyBytes)
$appKey = 'base64:' + [Convert]::ToBase64String($keyBytes)

$commands = @(
    @{
        label = 'composer_validate'
        executable = $composerInvocation.executable
        arguments = @($composerInvocation.prefix) + @('validate', '--strict', '--no-check-publish')
        log = '02-composer-validate.log'
    },
    @{
        label = 'composer_install'
        executable = $composerInvocation.executable
        arguments = @($composerInvocation.prefix) + @('install', '--no-dev', '--prefer-dist', '--optimize-autoloader', '--no-interaction', '--no-progress', '--no-scripts')
        log = '03-composer-install.log'
    },
    @{
        label = 'package_discover'
        executable = $phpExecutable
        arguments = @((Join-Path $workspacePath 'artisan'), 'package:discover', '--ansi')
        log = '04-package-discover.log'
    },
    @{
        label = 'npm_ci'
        executable = $npmInvocation.executable
        arguments = @($npmInvocation.prefix) + @('ci')
        log = '05-npm-ci.log'
    },
    @{
        label = 'npm_build'
        executable = $npmInvocation.executable
        arguments = @($npmInvocation.prefix) + @('run', 'build')
        log = '06-npm-build.log'
    }
)

$completed = New-Object System.Collections.Generic.List[object]

foreach ($command in $commands) {
    $environment = New-ControlledEnvironment $appKey $databasePath $runtimePath $command.label
    $logPath = Join-Path $logsPath $command.log
    $result = Invoke-StructuredProcess $command.label $command.executable $command.arguments $workspacePath $environment $logPath $appKey
    $completed.Add(@{ label = $command.label; exit_code = $result.exit_code; log_path = $logPath })

    if ($result.exit_code -ne 0) {
        Stop-Operation $command.label 'A preparation command failed.' $command.label $result.exit_code $logPath
    }
}

Remove-VerifiedNodeModules $workspacePath $sourcePath $stagingPath

$postCheckArguments = @(
    (Join-Path $sourcePath 'scripts\check-release-prerequisites.php'),
    "--source=$workspacePath",
    "--rules=$rulesPath",
    '--validate-build=true'
)
$postCheckLog = Join-Path $logsPath '07-post-build-check.log'
$postCheckResult = Invoke-StructuredProcess 'post_build_check' $phpExecutable $postCheckArguments $workspacePath (New-ControlledEnvironment $appKey $databasePath $runtimePath 'post_build_check') $postCheckLog $appKey
$completed.Add(@{ label = 'post_build_check'; exit_code = $postCheckResult.exit_code; log_path = $postCheckLog })

if ($postCheckResult.exit_code -ne 0) {
    Stop-Operation 'post_build_check' 'Prepared workspace failed prerequisite validation.' 'post_build_check' $postCheckResult.exit_code $postCheckLog
}

$stageArguments = @(
    (Join-Path $sourcePath 'scripts\stage-release.php'),
    "--source=$workspacePath",
    "--staging=$stagingPath",
    "--rules=$rulesPath",
    "--client=$Client",
    "--client-name=$ClientName",
    "--version=$Version",
    "--channel=$Channel",
    "--commit=$Commit"
)
$stageLog = Join-Path $logsPath '08-stage-release.log'
$stageResult = Invoke-StructuredProcess 'stage_release' $phpExecutable $stageArguments $workspacePath (New-ControlledEnvironment $appKey $databasePath $runtimePath 'stage_release') $stageLog $appKey
$completed.Add(@{ label = 'stage_release'; exit_code = $stageResult.exit_code; log_path = $stageLog })

if ($stageResult.exit_code -ne 0) {
    Stop-Operation 'stage_release' 'Release staging failed.' 'stage_release' $stageResult.exit_code $stageLog
}

$sourceHashesAfter = Get-PreparationSourceHashes $sourcePath

if (-not (Test-HashMapsEqual $sourceHashesBefore $sourceHashesAfter)) {
    Stop-Operation 'source_verification' 'An allowlisted source file changed during preparation.'
}

Write-JsonAndExit @{
    status = 'PASS'
    phase = 'complete'
    workspace = $workspacePath
    staging = $stagingPath
    build_runtime = $runtimePath
    commands = @($completed | ForEach-Object { $_ })
    source_unchanged = $true
    node_modules_removed = -not (Test-Path -LiteralPath (Join-Path $workspacePath 'node_modules'))
} 0
