param(
    [string]$InstallDir = "C:\SparkPair\GarmentsOS",
    [string]$ReleaseDir = "",
    [bool]$HideTechnicalFiles = $true
)

$ErrorActionPreference = "Stop"

$InstallDir = $InstallDir.Trim('"')
$ReleaseDir = $ReleaseDir.Trim('"')
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

function Test-GarmentsFileLocked($Path) {
    if (-not (Test-Path -LiteralPath $Path)) {
        return $false
    }

    try {
        $stream = [System.IO.File]::Open($Path, [System.IO.FileMode]::Open, [System.IO.FileAccess]::ReadWrite, [System.IO.FileShare]::None)
        $stream.Close()
        return $false
    } catch {
        return $true
    }
}

function Start-PendingLauncherReplacementHelper($MarkerPath, $TargetDir) {
    try {
        $updatesDir = Join-Path $TargetDir "updates"
        New-Item -ItemType Directory -Force -Path $updatesDir | Out-Null

        $helperPath = Join-Path $updatesDir "ApplyPendingLauncherUpdate.ps1"
        $helperScript = @'
param(
    [string]$MarkerPath
)

$ErrorActionPreference = "Stop"

function Write-PendingLauncherLog($Message) {
    try {
        $installDir = Split-Path -Parent $MarkerPath
        $logPath = Join-Path $installDir "pending-launcher-update.log"
        "[$(Get-Date -Format o)] $Message" | Add-Content -Path $logPath
    } catch {
    }
}

function Register-GarmentsProtocolFromLauncher($LauncherPath) {
    $baseKey = [Microsoft.Win32.Registry]::CurrentUser.CreateSubKey("Software\Classes\garmentsos")
    $baseKey.SetValue("", "URL:GarmentsOS PRO Launcher")
    $baseKey.SetValue("URL Protocol", "")
    $baseKey.Close()

    $iconKey = [Microsoft.Win32.Registry]::CurrentUser.CreateSubKey("Software\Classes\garmentsos\DefaultIcon")
    $iconKey.SetValue("", "$LauncherPath,0")
    $iconKey.Close()

    $commandKey = [Microsoft.Win32.Registry]::CurrentUser.CreateSubKey("Software\Classes\garmentsos\shell\open\command")
    $commandKey.SetValue("", "`"$LauncherPath`" `"%1`"")
    $commandKey.Close()
}

function Get-LauncherProcesses {
    $names = @("GarmentsOS-PRO", "GarmentsOS-PRO-Setup", "GarmentsOS PRO Launcher")
    foreach ($name in $names) {
        Get-Process -Name $name -ErrorAction SilentlyContinue
    }
}

Write-PendingLauncherLog "helper started"
Write-PendingLauncherLog "marker path: $MarkerPath"

$deadline = (Get-Date).AddSeconds(60)
$lastError = $null

while ((Get-Date) -lt $deadline) {
    try {
        if (-not (Test-Path -LiteralPath $MarkerPath)) {
            Write-PendingLauncherLog "marker not found; nothing to replace"
            exit 0
        }

        $marker = Get-Content -LiteralPath $MarkerPath -Raw | ConvertFrom-Json
        $pendingPath = [string]$marker.pending_path
        $destinationPath = [string]$marker.destination_path

        Write-PendingLauncherLog "pending path: $pendingPath"
        Write-PendingLauncherLog "destination path: $destinationPath"

        if (-not (Test-Path -LiteralPath $pendingPath)) {
            throw "Pending launcher EXE not found: $pendingPath"
        }

        $processes = @(Get-LauncherProcesses)
        if ($processes.Count -gt 0) {
            Write-PendingLauncherLog "waiting for launcher processes: $($processes.ProcessName -join ', ')"
            Start-Sleep -Seconds 2
            continue
        }

        $destinationDir = Split-Path -Parent $destinationPath
        if (-not (Test-Path -LiteralPath $destinationDir)) {
            New-Item -ItemType Directory -Force -Path $destinationDir | Out-Null
        }

        Copy-Item -LiteralPath $pendingPath -Destination $destinationPath -Force
        Write-PendingLauncherLog "copy success"

        Remove-Item -LiteralPath $pendingPath -Force -ErrorAction SilentlyContinue
        Write-PendingLauncherLog "pending file removed"

        Remove-Item -LiteralPath $MarkerPath -Force -ErrorAction SilentlyContinue
        Write-PendingLauncherLog "marker removed"

        Register-GarmentsProtocolFromLauncher $destinationPath
        Write-PendingLauncherLog "protocol registered"
        exit 0
    } catch {
        $lastError = $_.Exception.Message
        Write-PendingLauncherLog "copy failure/retry: $lastError"
        Start-Sleep -Seconds 2
    }
}

Write-PendingLauncherLog "error: pending launcher replacement failed after 60 seconds. $lastError"
exit 1
'@

        Set-Content -Path $helperPath -Value $helperScript -Encoding UTF8
        Start-Process -FilePath "powershell.exe" -ArgumentList @(
            "-NoProfile",
            "-ExecutionPolicy", "Bypass",
            "-WindowStyle", "Hidden",
            "-File", $helperPath,
            "-MarkerPath", $MarkerPath
        ) -WindowStyle Hidden

        Write-Host "Pending launcher replacement helper started by update script."
        Write-Host "Pending launcher helper script: $helperPath"
    } catch {
        Write-Warning "Could not start pending launcher replacement helper. $($_.Exception.Message)"
    }
}

function Stage-GarmentsLauncherUpdate($SourcePath, $DestinationPath, $TargetDir) {
    $updatesDir = Join-Path $TargetDir "updates"
    New-Item -ItemType Directory -Force -Path $updatesDir | Out-Null

    $pendingPath = Join-Path $updatesDir "GarmentsOS-PRO.exe.pending"
    Copy-Item -Force -LiteralPath $SourcePath -Destination $pendingPath

    $markerPath = Join-Path $TargetDir ".pending-launcher-update.json"
    $marker = [ordered]@{
        app = "garmentsos-pro"
        reason = "launcher_locked"
        pending_path = $pendingPath
        destination_path = $DestinationPath
        protocol = "garmentsos"
        protocol_command = "`"$DestinationPath`" `"%1`""
        staged_at = (Get-Date).ToUniversalTime().ToString("o")
    }

    $marker | ConvertTo-Json -Depth 4 | Set-Content -Path $markerPath -Encoding UTF8
    Write-Warning "Launcher EXE is running; staged launcher update will be applied after updater exits."
    Write-Host "Pending launcher update: $pendingPath"
    Write-Host "Pending launcher marker: $markerPath"
    Start-PendingLauncherReplacementHelper $markerPath $TargetDir
}

function Update-LauncherExeFromRelease($ReleaseDir, $InstallDir) {
    $sourceLauncher = Join-Path $ReleaseDir "GarmentsOS-PRO.exe"
    if (-not (Test-Path -LiteralPath $sourceLauncher)) {
        $legacySourceLauncher = Join-Path $ReleaseDir "launcher\GarmentsOS-PRO.exe"
        if (Test-Path -LiteralPath $legacySourceLauncher) {
            $sourceLauncher = $legacySourceLauncher
        }
    }
    if (-not (Test-Path -LiteralPath $sourceLauncher)) {
        $legacySourceLauncher = Join-Path $ReleaseDir "GarmentsOS-PRO-Setup.exe"
        if (Test-Path -LiteralPath $legacySourceLauncher) {
            $sourceLauncher = $legacySourceLauncher
        }
    }

    $destLauncher = Join-Path $InstallDir "GarmentsOS-PRO.exe"

    if (-not (Test-Path -LiteralPath $sourceLauncher)) {
        Write-Host "Launcher EXE not found in release package; continuing app update only."
        return
    }

    Write-Host "Launcher EXE found in release package."
    New-Item -ItemType Directory -Force -Path $InstallDir | Out-Null

    try {
        if (Test-GarmentsFileLocked $destLauncher) {
            Stage-GarmentsLauncherUpdate $sourceLauncher $destLauncher $InstallDir
            return
        }

        Copy-Item -Force -LiteralPath $sourceLauncher -Destination $destLauncher
        Write-Host "Launcher EXE updated."
        Register-GarmentsProtocol $InstallDir
    } catch {
        Write-Warning "Could not update launcher EXE now. $($_.Exception.Message)"
        try {
            Stage-GarmentsLauncherUpdate $sourceLauncher $destLauncher $InstallDir
        } catch {
            Write-Warning "Could not stage launcher EXE update. Continuing app update only. $($_.Exception.Message)"
        }
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

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "Docker Desktop is required."
}
docker info | Out-Null

if ([string]::IsNullOrWhiteSpace($ReleaseDir)) {
    $ReleaseDir = Split-Path -Parent $PSScriptRoot
}

$Manifest = Get-Content (Join-Path $ReleaseDir "manifest.json") | ConvertFrom-Json
$ImageTar = Join-Path $ReleaseDir $Manifest.image_tar
if (-not (Test-Path $ImageTar)) {
    throw "Docker image tar not found: $ImageTar"
}

$ReleaseBackupScript = Join-Path $ReleaseDir "scripts\windows-docker-backup.ps1"
$InstalledBackupScript = Join-Path $InstallDir "scripts\windows-docker-backup.ps1"
$BackupScript = if (Test-Path $ReleaseBackupScript) {
    $ReleaseBackupScript
} else {
    $InstalledBackupScript
}

if (-not (Test-Path $BackupScript)) {
    throw "Backup script not found. Checked release and installed script paths."
}

Write-Host "Using backup script: $BackupScript"
& $BackupScript -InstallDir $InstallDir

docker load -i $ImageTar | Out-Host

Copy-Item -Force (Join-Path $ReleaseDir "docker-compose.yml") $InstallDir
Copy-Item -Force (Join-Path $ReleaseDir ".env.example") $InstallDir
Copy-Item -Recurse -Force (Join-Path $ReleaseDir "scripts") $InstallDir
Copy-Item -Recurse -Force (Join-Path $ReleaseDir "docs") $InstallDir
Copy-Item -Recurse -Force (Join-Path $ReleaseDir "images") $InstallDir
Copy-Item -Recurse -Force (Join-Path $ReleaseDir "checksums") $InstallDir
Copy-Item -Force (Join-Path $ReleaseDir "manifest.json") $InstallDir
if (Test-Path (Join-Path $ReleaseDir "launcher")) {
    Copy-Item -Recurse -Force (Join-Path $ReleaseDir "launcher") $InstallDir
}
Update-LauncherExeFromRelease $ReleaseDir $InstallDir
Copy-RootLaunchers $ReleaseDir $InstallDir

$EnvPath = Join-Path $InstallDir ".env"
if (-not (Test-Path $EnvPath)) {
    throw "Existing .env is required for update."
}

Ensure-GarmentsUpdaterEnvKeys $EnvPath
Ensure-GarmentsLicenseEnvKeys $EnvPath

$envContent = Get-Content $EnvPath -Raw
$envContent = Set-EnvLine $envContent "GARMENTSOS_IMAGE" $Manifest.image
$envContent = Set-EnvLine $envContent "RUN_MIGRATIONS_ON_START" "true"
Save-EnvContent $EnvPath $envContent

Push-Location $InstallDir
try {
    docker compose up -d
} finally {
    Pop-Location
}

$envContent = Get-Content $EnvPath -Raw
$envContent = Set-EnvLine $envContent "RUN_MIGRATIONS_ON_START" "false"
Save-EnvContent $EnvPath $envContent

Register-GarmentsProtocol $InstallDir
Install-GarmentsShortcuts $InstallDir

if ($HideTechnicalFiles) {
    Hide-GarmentsTechnicalFiles $InstallDir
} else {
    Write-Host "Technical files were left visible because HideTechnicalFiles is false."
}

Write-Host "Update complete. Volumes were preserved."
Write-Host "Rollback: load the previous image tar, set GARMENTSOS_IMAGE in .env to the previous tag, then run docker compose up -d."
