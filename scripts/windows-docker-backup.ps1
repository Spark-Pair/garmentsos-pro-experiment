param(
    [string]$InstallDir = "C:\SparkPair\GarmentsOS",
    [string]$FromVersion = "",
    [string]$ToVersion = "",
    [string]$PackageSha256 = "",
    [switch]$Force
)

$ErrorActionPreference = "Stop"

$InstallDir = $InstallDir.Trim('"')
$FromVersion = $FromVersion.Trim('"')
$ToVersion = $ToVersion.Trim('"')
$PackageSha256 = $PackageSha256.Trim('"')

function Write-BackupLog($Message) {
    $line = "[$(Get-Date -Format o)] $Message"
    Write-Host $line
    if ($script:BackupDir) {
        $line | Add-Content -Path (Join-Path $script:BackupDir "backup.log")
    }
}

function Copy-IfExists($Source, $Destination) {
    if (Test-Path -LiteralPath $Source) {
        Copy-Item -LiteralPath $Source -Destination $Destination -Force
        Write-BackupLog "Copied: $Source"
        return $true
    }

    Write-BackupLog "Not found, skipped: $Source"
    return $false
}

function Get-InstalledManifestVersion($Path) {
    try {
        if (Test-Path -LiteralPath $Path) {
            $manifest = Get-Content -LiteralPath $Path -Raw | ConvertFrom-Json
            return [string]$manifest.version
        }
    } catch {
        Write-BackupLog "Could not read installed manifest version: $($_.Exception.Message)"
    }

    return ""
}

function Get-AppContainerId($InstallDir) {
    Push-Location $InstallDir
    try {
        $containerId = (& docker compose ps -q app 2>$null)
        if ($LASTEXITCODE -eq 0 -and -not [string]::IsNullOrWhiteSpace($containerId)) {
            return $containerId.Trim()
        }
    } finally {
        Pop-Location
    }

    return ""
}

function Backup-DatabaseFromRunningContainer($InstallDir, $BackupDir) {
    $containerId = Get-AppContainerId $InstallDir
    if ([string]::IsNullOrWhiteSpace($containerId)) {
        throw "App container is not running; cannot create safe SQLite backup."
    }

    $containerBackupPath = "/tmp/garmentsos-database-backup.sqlite"
    $hostBackupPath = Join-Path $BackupDir "database.sqlite"

    Push-Location $InstallDir
    try {
        $shellScript = @'
set -eu
DB_PATH="/var/www/html/database/database.sqlite"
if [ -f /var/www/html/.env ]; then
  FOUND_DB="$(grep -E '^DB_DATABASE=' /var/www/html/.env | tail -n 1 | cut -d= -f2- | tr -d '"' | tr -d "'" || true)"
  if [ -n "$FOUND_DB" ]; then
    DB_PATH="$FOUND_DB"
  fi
fi
if [ ! -f "$DB_PATH" ]; then
  echo "SQLite database not found: $DB_PATH" >&2
  exit 40
fi
rm -f /tmp/garmentsos-database-backup.sqlite
sqlite3 "$DB_PATH" ".backup '/tmp/garmentsos-database-backup.sqlite'"
test -s /tmp/garmentsos-database-backup.sqlite
printf '%s' "$DB_PATH" > /tmp/garmentsos-database-path.txt
'@
        $encoded = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($shellScript))
        & docker compose exec -T app sh -lc "echo $encoded | base64 -d | sh"
        if ($LASTEXITCODE -ne 0) {
            throw "Container SQLite backup command failed with exit code $LASTEXITCODE."
        }

        docker cp "${containerId}:${containerBackupPath}" $hostBackupPath
        if ($LASTEXITCODE -ne 0) {
            throw "docker cp failed while copying database backup."
        }

        $dbPathFile = Join-Path $BackupDir "database-path.txt"
        docker cp "${containerId}:/tmp/garmentsos-database-path.txt" $dbPathFile 2>$null | Out-Null

        if (-not (Test-Path -LiteralPath $hostBackupPath)) {
            throw "Database backup file was not created."
        }

        $dbSize = (Get-Item -LiteralPath $hostBackupPath).Length
        if ($dbSize -le 0) {
            throw "Database backup file is empty."
        }

        Write-BackupLog "SQLite database backup created: $hostBackupPath ($dbSize bytes)"
        return @{
            ok = $true
            path = $hostBackupPath
            size = $dbSize
            container_database_path = if (Test-Path -LiteralPath $dbPathFile) { (Get-Content -LiteralPath $dbPathFile -Raw).Trim() } else { "" }
        }
    } finally {
        try {
            & docker compose exec -T app sh -lc "rm -f /tmp/garmentsos-database-backup.sqlite /tmp/garmentsos-database-path.txt" 2>$null | Out-Null
        } catch {
        }
        Pop-Location
    }
}

function Copy-ContainerPath($InstallDir, $ContainerId, $ContainerPath, $TargetPath) {
    try {
        & docker exec $ContainerId sh -lc "test -e '$ContainerPath'"
        if ($LASTEXITCODE -ne 0) {
            Write-BackupLog "Container path not found, skipped: $ContainerPath"
            return $false
        }

        docker cp "${ContainerId}:${ContainerPath}" $TargetPath
        if ($LASTEXITCODE -eq 0) {
            Write-BackupLog "Copied container path: $ContainerPath"
            return $true
        }
    } catch {
        Write-BackupLog "Container path copy failed for ${ContainerPath}: $($_.Exception.Message)"
    }

    return $false
}

if (-not (Test-Path -LiteralPath $InstallDir)) {
    throw "Install directory not found: $InstallDir"
}

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "Docker Desktop is required."
}

docker info | Out-Null

$BackupRoot = Join-Path $InstallDir "backups"
$Stamp = Get-Date -Format "yyyyMMdd_HHmmss"
$script:BackupDir = Join-Path $BackupRoot "backup_$Stamp"
New-Item -ItemType Directory -Force -Path $script:BackupDir | Out-Null

Write-BackupLog "Starting GarmentsOS PRO full backup."
Write-BackupLog "InstallDir: $InstallDir"
Write-BackupLog "BackupDir: $script:BackupDir"

if ([string]::IsNullOrWhiteSpace($FromVersion)) {
    $FromVersion = Get-InstalledManifestVersion (Join-Path $InstallDir "manifest.json")
}

$backupStatus = "started"
$databaseBackup = $null
$containerId = ""

try {
    Copy-IfExists (Join-Path $InstallDir ".env") (Join-Path $script:BackupDir ".env") | Out-Null
    Copy-IfExists (Join-Path $InstallDir "manifest.json") (Join-Path $script:BackupDir "manifest.json") | Out-Null
    Copy-IfExists (Join-Path $InstallDir "docker-compose.yml") (Join-Path $script:BackupDir "docker-compose.yml") | Out-Null

    $databaseBackup = Backup-DatabaseFromRunningContainer $InstallDir $script:BackupDir
    $containerId = Get-AppContainerId $InstallDir
    if (-not [string]::IsNullOrWhiteSpace($containerId)) {
        Copy-ContainerPath $InstallDir $containerId "/var/www/html/storage/app" (Join-Path $script:BackupDir "storage-app") | Out-Null
        Copy-ContainerPath $InstallDir $containerId "/var/www/html/public/uploads" (Join-Path $script:BackupDir "public-uploads") | Out-Null
    }

    $backupStatus = "success"
} catch {
    $backupStatus = "failed"
    Write-BackupLog "Backup failed: $($_.Exception.Message)"
    if (-not $Force) {
        throw
    }
}

$metadata = [ordered]@{
    from_version = $FromVersion
    to_version = $ToVersion
    app_version = $FromVersion
    timestamp = (Get-Date).ToUniversalTime().ToString("o")
    package_sha256 = $PackageSha256
    database_path = if ($databaseBackup) { $databaseBackup.container_database_path } else { "" }
    database_backup_file = if ($databaseBackup) { $databaseBackup.path } else { "" }
    backup_status = $backupStatus
    backup_path = $script:BackupDir
    install_dir = $InstallDir
    database_volume = "garmentsos-pro_garmentsos_database"
    storage_volume = "garmentsos-pro_garmentsos_storage"
}

$metadataPath = Join-Path $script:BackupDir "backup-metadata.json"
$metadata | ConvertTo-Json -Depth 6 | Set-Content -Path $metadataPath -Encoding UTF8

try {
    if (-not [string]::IsNullOrWhiteSpace($containerId)) {
        docker cp $metadataPath "${containerId}:/var/www/html/storage/app/private/update-backup-status.json" 2>$null | Out-Null
    }
} catch {
    Write-BackupLog "Could not copy backup status into app storage: $($_.Exception.Message)"
}

Get-ChildItem -Path $script:BackupDir -File | ForEach-Object {
    $hash = Get-FileHash $_.FullName -Algorithm SHA256
    "$($hash.Hash.ToLower())  $($_.Name)" | Add-Content (Join-Path $script:BackupDir "SHA256SUMS.txt")
}

if ($backupStatus -ne "success" -and -not $Force) {
    throw "Backup did not complete successfully."
}

Write-BackupLog "Backup created: $script:BackupDir"
