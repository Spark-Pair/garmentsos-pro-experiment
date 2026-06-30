param(
    [string]$InstallDir = "C:\SparkPair\GarmentsOS",
    [string]$Version = "",
    [int]$Port = 8000
)

$ErrorActionPreference = "Stop"

$InstallDir = $InstallDir.Trim('"')
$Version = $Version.Trim('"')

function Require-Command($Name) {
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "$Name is required. Install Docker Desktop and try again."
    }
}

function Set-EnvLine($Content, $Name, $Value) {
    $pattern = "(?m)^" + [regex]::Escape($Name) + "=.*$"
    $line = "$Name=$Value"
    if ($Content -match $pattern) {
        return ($Content -replace $pattern, $line)
    }

    return ($Content.TrimEnd() + "`n" + $line + "`n")
}

Require-Command docker

docker --version | Out-Host
docker compose version | Out-Host
docker info | Out-Null

$Source = Split-Path -Parent $PSScriptRoot
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
$envContent = Set-EnvLine $envContent "APP_URL" "http://localhost:$Port"
$envContent = Set-EnvLine $envContent "APP_PORT" $Port
$envContent = Set-EnvLine $envContent "DB_DATABASE" "/var/www/html/database/database.sqlite"
$envContent = Set-EnvLine $envContent "LICENSE_ENFORCEMENT_ENABLED" "false"
if ($envContent -match '(?m)^APP_KEY=\s*$') {
    $bytes = New-Object byte[] 32
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $rng.GetBytes($bytes)
    } finally {
        $rng.Dispose()
    }

    $appKey = "base64:" + [Convert]::ToBase64String($bytes)
    $envContent = $envContent -replace '(?m)^APP_KEY=\s*$', "APP_KEY=$appKey"
}
$envContent = Set-EnvLine $envContent "GARMENTSOS_IMAGE" $Manifest.image
$envContent = Set-EnvLine $envContent "RUN_MIGRATIONS_ON_START" "true"
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
$envContent = Set-EnvLine $envContent "RUN_MIGRATIONS_ON_START" "false"
Set-Content -Path $EnvPath -Value $envContent -Encoding UTF8

$LanIp = (Get-NetIPAddress -AddressFamily IPv4 |
    Where-Object { $_.IPAddress -notlike "127.*" -and $_.PrefixOrigin -ne "WellKnown" } |
    Select-Object -First 1 -ExpandProperty IPAddress)

Write-Host ""
Write-Host "GarmentsOS PRO Docker install complete."
Write-Host "Local URL: http://localhost:$Port"
if ($LanIp) { Write-Host "LAN URL:   http://$LanIp`:$Port" }
Write-Host "Allow Windows Firewall access for Docker/Desktop when prompted."
