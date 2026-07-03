param(
    [string]$InstallDir = "C:\SparkPair\GarmentsOS",
    [switch]$DryRun
)

$ErrorActionPreference = "Stop"

function Write-Step($message) {
    Write-Host "[cleanup] $message"
}

function Ensure-Directory($path) {
    if (!(Test-Path -LiteralPath $path)) {
        New-Item -ItemType Directory -Path $path -Force | Out-Null
    }
}

$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$backupRoot = Join-Path $InstallDir "backups"
$cleanupBackupDir = Join-Path $backupRoot "cleanup_$timestamp"

# Never touch these names
$protectedNames = @(
    ".env",
    ".env.example",
    "backups",
    "updates",
    "docker-compose.yml",
    "manifest.json",
    "GarmentsOS-PRO.exe",
    "scripts",
    "data",
    "database",
    "storage"
)

# Known old technical leftovers only
$cleanupTargets = @(
    @{
        Name = "launcher"
        Reason = "Old internal launcher source/build folder from previous installs"
    },
    @{
        Name = "GarmentsOS-PRO-Setup.exe"
        Reason = "Old setup executable kept for compatibility before unified GarmentsOS-PRO.exe"
    }
)

$moved = @()
$wouldMove = @()
$warnings = @()

if (!(Test-Path -LiteralPath $InstallDir)) {
    Write-Step "Install directory not found: $InstallDir"
    exit 0
}

foreach ($target in $cleanupTargets) {
    $name = $target.Name
    $reason = $target.Reason
    $path = Join-Path $InstallDir $name

    if (!(Test-Path -LiteralPath $path)) {
        continue
    }

    if ($protectedNames -contains $name) {
        $warnings += "Skipped protected item: $name"
        continue
    }

    if ($DryRun) {
        $wouldMove += [ordered]@{
            name = $name
            path = $path
            reason = $reason
        }
        Write-Step "Dry run: would move '$name' because: $reason"
        continue
    }

    try {
        Ensure-Directory $cleanupBackupDir
        $destination = Join-Path $cleanupBackupDir $name

        if (Test-Path -LiteralPath $destination) {
            $destination = Join-Path $cleanupBackupDir "$name.$timestamp"
        }

        Move-Item -LiteralPath $path -Destination $destination -Force

        $moved += [ordered]@{
            name = $name
            from = $path
            to = $destination
            reason = $reason
        }

        Write-Step "Moved '$name' to cleanup backup."
    }
    catch {
        $warnings += "Failed to move '$name': $($_.Exception.Message)"
        Write-Warning "Failed to move '$name': $($_.Exception.Message)"
    }
}

if (!$DryRun -and ($moved.Count -gt 0 -or $warnings.Count -gt 0)) {
    Ensure-Directory $cleanupBackupDir

    $metadata = [ordered]@{
        timestamp = (Get-Date).ToUniversalTime().ToString("o")
        install_dir = $InstallDir
        dry_run = $false
        moved = $moved
        warnings = $warnings
    }

    $metadataPath = Join-Path $cleanupBackupDir "cleanup-metadata.json"
    $metadata | ConvertTo-Json -Depth 8 | Set-Content -Path $metadataPath -Encoding UTF8
    Write-Step "Cleanup metadata written: $metadataPath"
}

if ($DryRun) {
    $result = [ordered]@{
        timestamp = (Get-Date).ToUniversalTime().ToString("o")
        install_dir = $InstallDir
        dry_run = $true
        would_move = $wouldMove
        warnings = $warnings
    }

    $result | ConvertTo-Json -Depth 8
}

Write-Step "Cleanup completed."
exit 0