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
    docker run --rm -v garmentsos-pro_garmentsos_database:/database -v "$(Split-Path $BackupFile):/restore" alpine sh -c "cp /restore/$(Split-Path $BackupFile -Leaf) /database/database.sqlite"
    docker compose up -d
} finally {
    Pop-Location
}

Write-Host "Restore completed from $BackupFile"
