param(
    [string]$InstallDir = "C:\SparkPair\GarmentsOS",
    [string]$Version = "",
    [int]$Port = 8000,
    [bool]$HideTechnicalFiles = $true
)

$ErrorActionPreference = "Stop"

$InstallDir = $InstallDir.Trim('"')
$Version = $Version.Trim('"')
$script:EnvBackupCreated = $false

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

function New-GarmentsShortcut($ShortcutPath, $TargetPath, $WorkingDirectory, $Arguments = "", $Description = "Open GarmentsOS PRO", $IconPath = "") {
    try {
        New-Item -ItemType Directory -Force -Path (Split-Path -Parent $ShortcutPath) | Out-Null
        $shell = New-Object -ComObject WScript.Shell
        $shortcut = $shell.CreateShortcut($ShortcutPath)
        $shortcut.TargetPath = $TargetPath
        $shortcut.Arguments = $Arguments
        $shortcut.WorkingDirectory = $WorkingDirectory
        $shortcut.Description = $Description
        $iconSourcePath = if ([string]::IsNullOrWhiteSpace($IconPath)) { "" } else { ($IconPath -replace ',\d+$', '') }
        if (-not [string]::IsNullOrWhiteSpace($IconPath) -and (Test-Path -LiteralPath $iconSourcePath)) {
            $shortcut.IconLocation = $IconPath
        }
        $shortcut.Save()
        Write-Host "Shortcut target: $TargetPath $Arguments"
        Write-Host "Shortcut created: $ShortcutPath"
    } catch {
        Write-Warning "Shortcut creation failed: $ShortcutPath. $($_.Exception.Message)"
    }
}

function Install-GarmentsShortcuts($TargetDir) {
    $setupLauncher = Join-Path $TargetDir "GarmentsOS-PRO.exe"
    $openLauncher = Join-Path $TargetDir "Open GarmentsOS.bat"
    $shortcutTarget = if (Test-Path -LiteralPath $setupLauncher) {
        $setupLauncher
    } else {
        $openLauncher
    }

    if (-not (Test-Path -LiteralPath $shortcutTarget)) {
        Write-Warning "GarmentsOS launcher was not found. Shortcuts were not created."
        return
    }

    $usesExe = (Test-Path -LiteralPath $setupLauncher)
    $openArguments = if ($usesExe) { "garmentsos://open" } else { "" }
    $iconPath = if ($usesExe) { "$setupLauncher,0" } else { "" }

    try {
        $desktop = [Environment]::GetFolderPath("Desktop")
        if (-not [string]::IsNullOrWhiteSpace($desktop)) {
            New-GarmentsShortcut (Join-Path $desktop "GarmentsOS PRO.lnk") $shortcutTarget $TargetDir $openArguments "Open GarmentsOS PRO" $iconPath
            Write-Host "Desktop shortcut created: $(Join-Path $desktop "GarmentsOS PRO.lnk")"
        }
    } catch {
        Write-Warning "Could not resolve Desktop folder. $($_.Exception.Message)"
    }

    try {
        $programs = [Environment]::GetFolderPath("Programs")
        if (-not [string]::IsNullOrWhiteSpace($programs)) {
            $startMenuFolder = Join-Path $programs "SparkPair\GarmentsOS PRO"
            New-GarmentsShortcut (Join-Path $startMenuFolder "GarmentsOS PRO.lnk") $shortcutTarget $TargetDir $openArguments "Open GarmentsOS PRO" $iconPath
            Write-Host "Start Menu shortcut created: $(Join-Path $startMenuFolder "GarmentsOS PRO.lnk")"

            if ($usesExe) {
                New-GarmentsShortcut (Join-Path $startMenuFolder "GarmentsOS PRO Updater.lnk") $setupLauncher $TargetDir "" "Open GarmentsOS PRO Updater" $iconPath
                Write-Host "Start Menu shortcut created: $(Join-Path $startMenuFolder "GarmentsOS PRO Updater.lnk")"
            }

            New-GarmentsShortcut (Join-Path $startMenuFolder "Open Install Folder.lnk") "explorer.exe" $TargetDir "`"$TargetDir`"" "Open GarmentsOS PRO install folder"
            Write-Host "Start Menu shortcut created: $(Join-Path $startMenuFolder "Open Install Folder.lnk")"
        }
    } catch {
        Write-Warning "Could not create Start Menu shortcut. $($_.Exception.Message)"
    }
}

function Resolve-GarmentsGuiLauncher($TargetDir) {
    $candidates = @(
        (Join-Path $TargetDir "GarmentsOS-PRO.exe"),
        (Join-Path $TargetDir "GarmentsOS-PRO-Setup.exe"),
        (Join-Path $TargetDir "GarmentsOS PRO Launcher.exe"),
        (Join-Path $TargetDir "launcher\GarmentsOS-PRO.exe"),
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

        $iconKey = [Microsoft.Win32.Registry]::CurrentUser.CreateSubKey("Software\Classes\garmentsos\DefaultIcon")
        $iconKey.SetValue("", "$launcher,0")
        $iconKey.Close()

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

function Backup-EnvFile($EnvPath) {
    if ($script:EnvBackupCreated -or -not (Test-Path -LiteralPath $EnvPath)) {
        return
    }

    $installRoot = Split-Path -Parent $EnvPath
    $backupDir = Join-Path $installRoot "backups"
    New-Item -ItemType Directory -Force -Path $backupDir | Out-Null

    $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
    $backupPath = Join-Path $backupDir "env_$timestamp.env"
    Copy-Item -LiteralPath $EnvPath -Destination $backupPath -Force
    $script:EnvBackupCreated = $true
    Write-Host "Environment backup created: $backupPath"
}

function Save-EnvContent($EnvPath, $Content) {
    Backup-EnvFile $EnvPath
    Set-Content -Path $EnvPath -Value $Content -Encoding UTF8
}

function Ensure-EnvKey($EnvPath, $Key, $DefaultValue) {
    if (-not (Test-Path -LiteralPath $EnvPath)) {
        throw ".env file not found: $EnvPath"
    }

    $content = Get-Content -LiteralPath $EnvPath -Raw
    $pattern = "(?m)^\s*" + [regex]::Escape($Key) + "="
    if ($content -match $pattern) {
        Write-Host "Environment key already exists: $Key"
        return
    }

    Backup-EnvFile $EnvPath
    $line = "$Key=$DefaultValue"
    $lineEnding = if ($content -match "`r`n") { "`r`n" } else { "`n" }
    $updated = $content.TrimEnd("`r", "`n") + $lineEnding + $line + $lineEnding
    Set-Content -Path $EnvPath -Value $updated -Encoding UTF8
    Write-Host "Added missing environment key: $Key"
}

function Ensure-GarmentsUpdaterEnvKeys($EnvPath) {
    Ensure-EnvKey $EnvPath "UPDATE_FEED_URL" "https://sparkpair.dev/api/updates/garmentsos-pro/stable/latest.json"
    Ensure-EnvKey $EnvPath "UPDATE_FALLBACK_FEED_URL" "https://github.com/Spark-Pair/garmentsos-pro-experiment/releases/download/latest-stable/latest.json"
    Ensure-EnvKey $EnvPath "UPDATE_LOCK_TTL_MINUTES" "30"
    Ensure-EnvKey $EnvPath "UPDATE_CHANNEL" "stable"
    Ensure-EnvKey $EnvPath "UPDATE_LAUNCHER_PROTOCOL" "garmentsos"
    Ensure-EnvKey $EnvPath "UPDATE_REQUEST_TTL_MINUTES" "10"
}

function Ensure-GarmentsLicenseEnvKeys($EnvPath) {
    Ensure-EnvKey $EnvPath "LICENSE_ENABLED" "false"
    Ensure-EnvKey $EnvPath "LICENSE_CLIENT_ID" ""
    Ensure-EnvKey $EnvPath "LICENSE_CLIENT_NAME" ""
    Ensure-EnvKey $EnvPath "LICENSE_KEY" ""
    Ensure-EnvKey $EnvPath "LICENSE_CHECK_URL" "https://sparkpair.dev/api/licenses/verify"
    Ensure-EnvKey $EnvPath "LICENSE_GRACE_DAYS" "7"
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
if (Test-Path (Join-Path $Source "GarmentsOS-PRO.exe")) {
    Copy-Item -Force (Join-Path $Source "GarmentsOS-PRO.exe") $InstallDir
} elseif (Test-Path (Join-Path $Source "GarmentsOS-PRO-Setup.exe")) {
    Copy-Item -Force (Join-Path $Source "GarmentsOS-PRO-Setup.exe") (Join-Path $InstallDir "GarmentsOS-PRO.exe")
}
if (Test-Path (Join-Path $Source "GarmentsOS PRO Launcher.exe")) {
    Copy-Item -Force (Join-Path $Source "GarmentsOS PRO Launcher.exe") $InstallDir
}
if (Test-Path (Join-Path $Source "launcher")) {
    Copy-Item -Recurse -Force (Join-Path $Source "launcher") $InstallDir
}
$installedSetupLauncher = Join-Path $InstallDir "GarmentsOS-PRO.exe"
$installedNestedPrimaryLauncher = Join-Path $InstallDir "launcher\GarmentsOS-PRO.exe"
$installedNestedLauncher = Join-Path $InstallDir "launcher\GarmentsOS PRO Launcher.exe"
if (-not (Test-Path $installedSetupLauncher) -and (Test-Path $installedNestedPrimaryLauncher)) {
    Copy-Item -Force $installedNestedPrimaryLauncher $installedSetupLauncher
} elseif (-not (Test-Path $installedSetupLauncher) -and (Test-Path $installedNestedLauncher)) {
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
$EnvCreated = $false
if (-not (Test-Path $EnvPath)) {
    Copy-Item (Join-Path $InstallDir ".env.example") $EnvPath
    $EnvCreated = $true
}

Ensure-GarmentsUpdaterEnvKeys $EnvPath
Ensure-GarmentsLicenseEnvKeys $EnvPath

$envContent = Get-Content $EnvPath -Raw
if ($EnvCreated) {
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
    Save-EnvContent $EnvPath $envContent
} else {
    Ensure-EnvKey $EnvPath "APP_PORT" $Port
    Ensure-EnvKey $EnvPath "LICENSE_ENFORCEMENT_ENABLED" "false"
    Ensure-EnvKey $EnvPath "GARMENTSOS_IMAGE" $Manifest.image
}

Push-Location $InstallDir
try {
    docker volume create garmentsos-pro_garmentsos_database | Out-Null
    docker volume create garmentsos-pro_garmentsos_storage | Out-Null
    docker compose up -d
} finally {
    Pop-Location
}

if ($EnvCreated) {
    $envContent = Get-Content $EnvPath -Raw
    $envContent = Set-EnvLine $envContent "RUN_MIGRATIONS_ON_START" "false"
    Save-EnvContent $EnvPath $envContent
}

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
