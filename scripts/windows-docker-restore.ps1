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
$restoreLogDir = Join-Path $InstallDir "logs"
New-Item -ItemType Directory -Force -Path $restoreLogDir | Out-Null
$restoreLog = Join-Path $restoreLogDir ("restore-" + (Get-Date -Format "yyyyMMdd_HHmmss") + ".log")

function Write-RestoreLog($Message) {
    "[$(Get-Date -Format o)] $Message" | Add-Content -LiteralPath $restoreLog
    Write-Host $Message
}

if (-not (Test-Path -LiteralPath $metadataPath) -or -not (Test-Path -LiteralPath $databasePath)) {
    throw "Backup is incomplete; restore skipped."
}

if ((Split-Path $databasePath -Leaf) -ne "database.sqlite") {
    throw "Backup is incomplete; restore skipped."
}

$databaseSize = (Get-Item -LiteralPath $databasePath).Length
if ($databaseSize -le 0) {
    throw "Backup is incomplete; restore skipped."
}

$stream = [System.IO.File]::OpenRead($databasePath)
try {
    $headerBytes = New-Object byte[] 16
    $read = $stream.Read($headerBytes, 0, 16)
} finally {
    $stream.Dispose()
}
if ($read -ne 16 -or ([System.Text.Encoding]::ASCII.GetString($headerBytes, 0, 16) -ne "SQLite format 3`0")) {
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

Write-RestoreLog "Business database restore requested."
Write-RestoreLog "InstallDir: $InstallDir"
Write-RestoreLog "Validated database: $databasePath"
Write-RestoreLog "Policy: restoring database.sqlite only. .env, install-id, license caches, device approval cache, and update markers are not restored."

& (Join-Path $InstallDir "scripts\windows-docker-backup.ps1") -InstallDir $InstallDir

Push-Location $InstallDir
try {
    docker compose down
    docker run --rm -v garmentsos-pro_garmentsos_database:/database -v "$($BackupDir):/restore" alpine sh -c 'cp "$1" /database/database.sqlite' sh "/restore/database.sqlite"
    docker compose up -d
} finally {
    Pop-Location
}

Write-RestoreLog "Business data restored. License/device approval remains tied to this installation."
Write-Host "Restore log: $restoreLog"
