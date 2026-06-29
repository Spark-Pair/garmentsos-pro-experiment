param(
    [string]$InstallDir = "C:\SparkPair\GarmentsOS"
)

$ErrorActionPreference = "Stop"

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "Docker Desktop is required."
}
docker info | Out-Null

$BackupRoot = Join-Path $InstallDir "backups"
$Stamp = Get-Date -Format "yyyyMMdd_HHmmss"
$BackupDir = Join-Path $BackupRoot "backup_$Stamp"
New-Item -ItemType Directory -Force -Path $BackupDir | Out-Null

Push-Location $InstallDir
try {
    docker compose exec -T app php artisan tinker --execute='$r = app(App\Services\BackupService::class)->createManualBackup("windows_docker_backup"); $ok = isset($r["success"]) && $r["success"]; if (!$ok) { fwrite(STDERR, $r["message"].PHP_EOL); exit(1); } echo $r["filename"].PHP_EOL;'
    docker run --rm -v garmentsos-pro_garmentsos_storage:/storage -v "$($BackupDir):/backup" alpine sh -c "cd /storage && tar czf /backup/storage-backup.tar.gz app/private/backups || true"
} finally {
    Pop-Location
}

Get-ChildItem -Path $BackupDir -File | ForEach-Object {
    $hash = Get-FileHash $_.FullName -Algorithm SHA256
    "$($hash.Hash.ToLower())  $($_.Name)" | Add-Content (Join-Path $BackupDir "SHA256SUMS.txt")
}

Write-Host "Backup created: $BackupDir"
