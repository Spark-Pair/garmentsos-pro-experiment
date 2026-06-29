param(
    [string]$InstallDir = "C:\SparkPair\GarmentsOS",
    [string]$ReleaseDir = ""
)

$ErrorActionPreference = "Stop"

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "Docker Desktop is required."
}
docker info | Out-Null

if ([string]::IsNullOrWhiteSpace($ReleaseDir)) {
    $ReleaseDir = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
}

$Manifest = Get-Content (Join-Path $ReleaseDir "manifest.json") | ConvertFrom-Json
$ImageTar = Join-Path $ReleaseDir $Manifest.image_tar
if (-not (Test-Path $ImageTar)) {
    throw "Docker image tar not found: $ImageTar"
}

& (Join-Path $InstallDir "scripts\windows-docker-backup.ps1") -InstallDir $InstallDir

docker load -i $ImageTar | Out-Host

Copy-Item -Force (Join-Path $ReleaseDir "docker-compose.yml") $InstallDir
Copy-Item -Recurse -Force (Join-Path $ReleaseDir "scripts") $InstallDir
Copy-Item -Recurse -Force (Join-Path $ReleaseDir "docs") $InstallDir
Copy-Item -Force (Join-Path $ReleaseDir "manifest.json") $InstallDir

$EnvPath = Join-Path $InstallDir ".env"
if (-not (Test-Path $EnvPath)) {
    throw "Existing .env is required for update."
}

$envContent = Get-Content $EnvPath -Raw
if ($envContent -notmatch '(?m)^GARMENTSOS_IMAGE=') {
    $envContent += "`nGARMENTSOS_IMAGE=$($Manifest.image)`n"
} else {
    $envContent = $envContent -replace '(?m)^GARMENTSOS_IMAGE=.*$', "GARMENTSOS_IMAGE=$($Manifest.image)"
}
if ($envContent -notmatch '(?m)^RUN_MIGRATIONS_ON_START=') {
    $envContent += "RUN_MIGRATIONS_ON_START=true`n"
} else {
    $envContent = $envContent -replace '(?m)^RUN_MIGRATIONS_ON_START=.*$', "RUN_MIGRATIONS_ON_START=true"
}
Set-Content -Path $EnvPath -Value $envContent -Encoding UTF8

Push-Location $InstallDir
try {
    docker compose up -d
} finally {
    Pop-Location
}

$envContent = Get-Content $EnvPath -Raw
$envContent = $envContent -replace '(?m)^RUN_MIGRATIONS_ON_START=.*$', "RUN_MIGRATIONS_ON_START=false"
Set-Content -Path $EnvPath -Value $envContent -Encoding UTF8

Write-Host "Update complete. Volumes were preserved."
Write-Host "Rollback: load the previous image tar, set GARMENTSOS_IMAGE in .env to the previous tag, then run docker compose up -d."
