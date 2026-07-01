param(
    [string]$InstallDir = "C:\SparkPair\GarmentsOS",
    [string]$Version = "",
    [int]$Port = 8000,
    [bool]$HideTechnicalFiles = $true
)

$ErrorActionPreference = "Stop"

$InstallDir = $InstallDir.Trim('"')
$Version = $Version.Trim('"')

function Copy-RootLaunchers($SourceDir, $TargetDir) {
    $launchers = @(
        "Open GarmentsOS.bat",
        "Backup GarmentsOS.bat",
        "Stop GarmentsOS.bat",
        "Update GarmentsOS.bat"
    )

    foreach ($launcher in $launchers) {
        $sourcePath = Join-Path $SourceDir $launcher
        $stubPath = Join-Path $SourceDir ("scripts\package-launchers\" + $launcher + ".stub")
        $targetPath = Join-Path $TargetDir $launcher

        if (Test-Path $sourcePath) {
            Copy-Item -Force $sourcePath $targetPath
        } elseif (Test-Path $stubPath) {
            Copy-Item -Force $stubPath $targetPath
        } else {
            Write-Warning "Launcher not found and was not installed: $launcher"
        }
    }
}

function New-GarmentsShortcut($ShortcutPath, $TargetPath, $WorkingDirectory) {
    try {
        $shell = New-Object -ComObject WScript.Shell
        $shortcut = $shell.CreateShortcut($ShortcutPath)
        $shortcut.TargetPath = $TargetPath
        $shortcut.WorkingDirectory = $WorkingDirectory
        $shortcut.Description = "Open GarmentsOS PRO"
        $shortcut.Save()
        Write-Host "Created shortcut: $ShortcutPath"
    } catch {
        Write-Warning "Could not create shortcut $ShortcutPath. $($_.Exception.Message)"
    }
}

function Install-GarmentsShortcuts($TargetDir) {
    $setupLauncher = Join-Path $TargetDir "GarmentsOS-PRO-Setup.exe"
    $rootGuiLauncher = Join-Path $TargetDir "GarmentsOS PRO Launcher.exe"
    $guiLauncher = Join-Path $TargetDir "launcher\GarmentsOS PRO Launcher.exe"
    $openLauncher = Join-Path $TargetDir "Open GarmentsOS.bat"
    $shortcutTarget = if (Test-Path $setupLauncher) {
        $setupLauncher
    } elseif (Test-Path $rootGuiLauncher) {
        $rootGuiLauncher
    } elseif (Test-Path $guiLauncher) {
        $guiLauncher
    } else {
        $openLauncher
    }

    if (-not (Test-Path $shortcutTarget)) {
        Write-Warning "GarmentsOS launcher was not found. Shortcuts were not created."
        return
    }

    try {
        $desktop = [Environment]::GetFolderPath("Desktop")
        if (-not [string]::IsNullOrWhiteSpace($desktop)) {
            New-GarmentsShortcut (Join-Path $desktop "GarmentsOS PRO.lnk") $shortcutTarget $TargetDir
        }
    } catch {
        Write-Warning "Could not resolve Desktop folder. $($_.Exception.Message)"
    }

    try {
        $programs = [Environment]::GetFolderPath("Programs")
        if (-not [string]::IsNullOrWhiteSpace($programs)) {
            $sparkPair = Join-Path $programs "SparkPair"
            New-Item -ItemType Directory -Force -Path $sparkPair | Out-Null
            New-GarmentsShortcut (Join-Path $sparkPair "GarmentsOS PRO.lnk") $shortcutTarget $TargetDir
        }
    } catch {
        Write-Warning "Could not create Start Menu shortcut. $($_.Exception.Message)"
    }
}

function Resolve-GarmentsGuiLauncher($TargetDir) {
    $candidates = @(
        (Join-Path $TargetDir "GarmentsOS-PRO-Setup.exe"),
        (Join-Path $TargetDir "GarmentsOS PRO Launcher.exe"),
        (Join-Path $TargetDir "launcher\GarmentsOS-PRO-Setup.exe"),
        (Join-Path $TargetDir "launcher\GarmentsOS PRO Launcher.exe")
    )

    foreach ($candidate in $candidates) {
        if (Test-Path $candidate) {
            return $candidate
        }
    }

    return $null
}

function Register-GarmentsProtocol($TargetDir) {
    $launcher = Resolve-GarmentsGuiLauncher $TargetDir
    if ([string]::IsNullOrWhiteSpace($launcher)) {
        Write-Warning "GarmentsOS GUI launcher was not found. garmentsos:// protocol was not registered."
        return
    }

    try {
        $baseKey = [Microsoft.Win32.Registry]::CurrentUser.CreateSubKey("Software\Classes\garmentsos")
        $baseKey.SetValue("", "URL:GarmentsOS PRO Launcher")
        $baseKey.SetValue("URL Protocol", "")
        $baseKey.Close()

        $commandKey = [Microsoft.Win32.Registry]::CurrentUser.CreateSubKey("Software\Classes\garmentsos\shell\open\command")
        $commandKey.SetValue("", "`"$launcher`" `"%1`"")
        $commandKey.Close()

        Write-Host "Registered garmentsos:// protocol for: $launcher"
    } catch {
        Write-Warning "Could not register garmentsos:// protocol. $($_.Exception.Message)"
    }
}

function Hide-GarmentsTechnicalFiles($TargetDir) {
    $items = @(
        "scripts",
        "images",
        "checksums",
        "docs",
        "docker-compose.yml",
        ".env",
        ".env.example",
        "manifest.json"
    )

    foreach ($item in $items) {
        $path = Join-Path $TargetDir $item
        if (-not (Test-Path -LiteralPath $path)) {
            continue
        }

        try {
            $target = Get-Item -LiteralPath $path -Force
            $target.Attributes = $target.Attributes -bor [System.IO.FileAttributes]::Hidden
        } catch {
            Write-Warning "Could not hide $path. $($_.Exception.Message)"
        }
    }
}

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
if (Test-Path (Join-Path $Source "GarmentsOS-PRO-Setup.exe")) {
    Copy-Item -Force (Join-Path $Source "GarmentsOS-PRO-Setup.exe") $InstallDir
}
if (Test-Path (Join-Path $Source "GarmentsOS PRO Launcher.exe")) {
    Copy-Item -Force (Join-Path $Source "GarmentsOS PRO Launcher.exe") $InstallDir
}
if (Test-Path (Join-Path $Source "launcher")) {
    Copy-Item -Recurse -Force (Join-Path $Source "launcher") $InstallDir
}
$installedSetupLauncher = Join-Path $InstallDir "GarmentsOS-PRO-Setup.exe"
$installedNestedLauncher = Join-Path $InstallDir "launcher\GarmentsOS PRO Launcher.exe"
if (-not (Test-Path $installedSetupLauncher) -and (Test-Path $installedNestedLauncher)) {
    Copy-Item -Force $installedNestedLauncher $installedSetupLauncher
}
Copy-RootLaunchers $Source $InstallDir

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

Install-GarmentsShortcuts $InstallDir
Register-GarmentsProtocol $InstallDir
if ($HideTechnicalFiles) {
    Hide-GarmentsTechnicalFiles $InstallDir
} else {
    Write-Host "Technical files were left visible because HideTechnicalFiles is false."
}

$LanIp = (Get-NetIPAddress -AddressFamily IPv4 |
    Where-Object { $_.IPAddress -notlike "127.*" -and $_.PrefixOrigin -ne "WellKnown" } |
    Select-Object -First 1 -ExpandProperty IPAddress)

Write-Host ""
Write-Host "GarmentsOS PRO Docker install complete."
Write-Host "Local URL: http://localhost:$Port"
if ($LanIp) { Write-Host "LAN URL:   http://$LanIp`:$Port" }
Write-Host "Allow Windows Firewall access for Docker/Desktop when prompted."
