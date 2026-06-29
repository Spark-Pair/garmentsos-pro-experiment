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
    $phpCode = @'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$r = app(App\Services\BackupService::class)->createManualBackup('windows_docker_backup');
$ok = isset($r['success']) && $r['success'];
if (!$ok) {
    fwrite(STDERR, ($r['message'] ?? 'Backup failed.') . PHP_EOL);
    exit(1);
}
echo ($r['filename'] ?? 'database-backup-created') . PHP_EOL;
'@
    $encodedPhp = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($phpCode))
    $phpRunner = "eval(base64_decode('$encodedPhp'));"

    $appBackupOutput = & docker compose exec -T app php -r $phpRunner 2>&1
    if ($LASTEXITCODE -eq 0) {
        $appBackupOutput | Out-Host
    } else {
        Write-Warning "In-app database backup could not be created. Continuing with Docker volume backup fallback."
        if ($appBackupOutput) {
            $appBackupOutput | ForEach-Object { Write-Verbose $_ }
        }
    }

    $storageBackupOutput = & docker run --rm -v garmentsos-pro_garmentsos_storage:/storage -v "$($BackupDir):/backup" alpine sh -c "cd /storage && tar czf /backup/storage-backup.tar.gz app/private/backups || true" 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Warning "Docker volume backup fallback did not complete cleanly."
        if ($storageBackupOutput) {
            $storageBackupOutput | ForEach-Object { Write-Verbose $_ }
        }
    }
} finally {
    Pop-Location
}

Get-ChildItem -Path $BackupDir -File | ForEach-Object {
    $hash = Get-FileHash $_.FullName -Algorithm SHA256
    "$($hash.Hash.ToLower())  $($_.Name)" | Add-Content (Join-Path $BackupDir "SHA256SUMS.txt")
}

Write-Host "Backup created: $BackupDir"
