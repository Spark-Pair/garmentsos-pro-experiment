param(
    [Parameter(Mandatory=$true)][string]$BackupFile,
    [string]$InstallDir = "C:\SparkPair\GarmentsOS",
    [string]$Confirm = ""
)

$ErrorActionPreference = "Stop"

if ($Confirm -ne "RESTORE GARMENTSOS") {
    throw "Restore requires -Confirm 'RESTORE GARMENTSOS'. Test on a copy before production restore."
}

if (-not (Test-Path $BackupFile)) {
    throw "Backup file not found: $BackupFile"
}

& (Join-Path $InstallDir "scripts\windows-docker-backup.ps1") -InstallDir $InstallDir

Push-Location $InstallDir
try {
    docker compose down
    $BackupDir = Split-Path $BackupFile
    $BackupName = Split-Path $BackupFile -Leaf
    docker run --rm -v garmentsos-pro_garmentsos_database:/database -v "$($BackupDir):/restore" alpine sh -c 'cp "$1" /database/database.sqlite' sh "/restore/$BackupName"
    docker compose up -d
} finally {
    Pop-Location
}

Write-Host "Restore completed from $BackupFile"
