param(
    [Parameter(Mandatory=$true)][string]$BackupFile,
    [string]$InstallDir = "C:\SparkPair\GarmentsOS",
    [string]$Confirm = ""
)

$ErrorActionPreference = "Stop"

$BackupFile = $BackupFile.Trim('"')
$InstallDir = $InstallDir.Trim('"')
$Confirm = $Confirm.Trim('"')

if ($Confirm -ne "RESTORE GARMENTSOS") {
    throw "Restore requires -Confirm 'RESTORE GARMENTSOS'. Test on a copy before production restore."
}

if (-not (Test-Path $BackupFile)) {
    throw "Backup file not found: $BackupFile"
}

$BackupDir = Split-Path $BackupFile
$metadataPath = Join-Path $BackupDir "backup-metadata.json"
$databasePath = Join-Path $BackupDir "database.sqlite"

if (-not (Test-Path -LiteralPath $metadataPath) -or -not (Test-Path -LiteralPath $databasePath)) {
    throw "Backup is incomplete; restore skipped."
}

$databaseSize = (Get-Item -LiteralPath $databasePath).Length
if ($databaseSize -le 0) {
    throw "Backup is incomplete; restore skipped."
}

try {
    $metadata = Get-Content -LiteralPath $metadataPath -Raw | ConvertFrom-Json
    if ([string]$metadata.backup_status -ne "success") {
        throw "Backup status is not success."
    }
} catch {
    throw "Backup is incomplete; restore skipped."
}

& (Join-Path $InstallDir "scripts\windows-docker-backup.ps1") -InstallDir $InstallDir

Push-Location $InstallDir
try {
    docker compose down
    $BackupName = Split-Path $BackupFile -Leaf
    docker run --rm -v garmentsos-pro_garmentsos_database:/database -v "$($BackupDir):/restore" alpine sh -c 'cp "$1" /database/database.sqlite' sh "/restore/$BackupName"
    docker compose up -d
} finally {
    Pop-Location
}

Write-Host "Restore completed from $BackupFile"
