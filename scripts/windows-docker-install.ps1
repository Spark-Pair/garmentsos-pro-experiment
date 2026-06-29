param(
    [string]$InstallDir = "C:\SparkPair\GarmentsOS",
    [string]$Version = "",
    [int]$Port = 8000
)

$ErrorActionPreference = "Stop"

function Require-Command($Name) {
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "$Name is required. Install Docker Desktop and try again."
    }
}

Require-Command docker

docker --version | Out-Host
docker compose version | Out-Host
docker info | Out-Null

$Source = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
New-Item -ItemType Directory -Force -Path $InstallDir | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $InstallDir "backups") | Out-Null

Copy-Item -Force (Join-Path $Source "docker-compose.yml") $InstallDir
Copy-Item -Force (Join-Path $Source ".env.example") $InstallDir
Copy-Item -Recurse -Force (Join-Path $Source "scripts") $InstallDir
Copy-Item -Recurse -Force (Join-Path $Source "docs") $InstallDir
Copy-Item -Recurse -Force (Join-Path $Source "images") $InstallDir
Copy-Item -Recurse -Force (Join-Path $Source "checksums") $InstallDir
Copy-Item -Force (Join-Path $Source "manifest.json") $InstallDir

$Manifest = Get-Content (Join-Path $InstallDir "manifest.json") | ConvertFrom-Json
if ([string]::IsNullOrWhiteSpace($Version)) {
    $Version = $Manifest.version
}

$ImageTar = Join-Path $InstallDir $Manifest.image_tar
if (-not (Test-Path $ImageTar)) {
    throw "Docker image tar not found: $ImageTar"
}

docker load -i $ImageTar | Out-Host

$EnvPath = Join-Path $InstallDir ".env"
if (-not (Test-Path $EnvPath)) {
    Copy-Item (Join-Path $InstallDir ".env.example") $EnvPath
}

$envContent = Get-Content $EnvPath -Raw
$envContent = $envContent -replace '(?m)^APP_URL=.*$', "APP_URL=http://localhost:$Port"
$envContent = $envContent -replace '(?m)^DB_DATABASE=.*$', "DB_DATABASE=/var/www/html/database/database.sqlite"
if ($envContent -notmatch '(?m)^APP_PORT=') {
    $envContent += "`nAPP_PORT=$Port`n"
}
if ($envContent -match '(?m)^APP_KEY=\s*$') {
    $bytes = New-Object byte[] 32
    [System.Security.Cryptography.RandomNumberGenerator]::Fill($bytes)
    $appKey = "base64:" + [Convert]::ToBase64String($bytes)
    $envContent = $envContent -replace '(?m)^APP_KEY=\s*$', "APP_KEY=$appKey"
}
if ($envContent -notmatch '(?m)^GARMENTSOS_IMAGE=') {
    $envContent += "GARMENTSOS_IMAGE=$($Manifest.image)`n"
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
    docker volume create garmentsos-pro_garmentsos_database | Out-Null
    docker volume create garmentsos-pro_garmentsos_storage | Out-Null
    docker compose up -d
} finally {
    Pop-Location
}

$envContent = Get-Content $EnvPath -Raw
$envContent = $envContent -replace '(?m)^RUN_MIGRATIONS_ON_START=.*$', "RUN_MIGRATIONS_ON_START=false"
Set-Content -Path $EnvPath -Value $envContent -Encoding UTF8

$LanIp = (Get-NetIPAddress -AddressFamily IPv4 |
    Where-Object { $_.IPAddress -notlike "127.*" -and $_.PrefixOrigin -ne "WellKnown" } |
    Select-Object -First 1 -ExpandProperty IPAddress)

Write-Host ""
Write-Host "GarmentsOS PRO Docker install complete."
Write-Host "Local URL: http://localhost:$Port"
if ($LanIp) { Write-Host "LAN URL:   http://$LanIp`:$Port" }
Write-Host "Allow Windows Firewall access for Docker/Desktop when prompted."
