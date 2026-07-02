param(
    [string]$InstallDir = "C:\SparkPair\GarmentsOS",
    [string]$ReleaseDir = "",
    [bool]$HideTechnicalFiles = $true
)

$ErrorActionPreference = "Stop"

$InstallDir = $InstallDir.Trim('"')
$ReleaseDir = $ReleaseDir.Trim('"')

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
        if (-not [string]::IsNullOrWhiteSpace($IconPath) -and (Test-Path -LiteralPath $IconPath)) {
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
    $setupLauncher = Join-Path $TargetDir "GarmentsOS-PRO-Setup.exe"
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
    $iconPath = if ($usesExe) { $setupLauncher } else { "" }

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
                New-GarmentsShortcut (Join-Path $startMenuFolder "GarmentsOS PRO Updater.lnk") $setupLauncher $TargetDir "" "Open GarmentsOS PRO Updater" $setupLauncher
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

function Stage-GarmentsLauncherUpdate($SourcePath, $DestinationPath, $TargetDir) {
    $updatesDir = Join-Path $TargetDir "updates"
    New-Item -ItemType Directory -Force -Path $updatesDir | Out-Null

    $pendingPath = Join-Path $updatesDir "GarmentsOS-PRO-Setup.exe.pending"
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
}

function Update-LauncherExeFromRelease($ReleaseDir, $InstallDir) {
    $sourceLauncher = Join-Path $ReleaseDir "GarmentsOS-PRO-Setup.exe"
    $destLauncher = Join-Path $InstallDir "GarmentsOS-PRO-Setup.exe"

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

$envContent = Get-Content $EnvPath -Raw
$envContent = Set-EnvLine $envContent "GARMENTSOS_IMAGE" $Manifest.image
$envContent = Set-EnvLine $envContent "RUN_MIGRATIONS_ON_START" "true"
Set-Content -Path $EnvPath -Value $envContent -Encoding UTF8

Push-Location $InstallDir
try {
    docker compose up -d
} finally {
    Pop-Location
}

$envContent = Get-Content $EnvPath -Raw
$envContent = Set-EnvLine $envContent "RUN_MIGRATIONS_ON_START" "false"
Set-Content -Path $EnvPath -Value $envContent -Encoding UTF8

Register-GarmentsProtocol $InstallDir
Install-GarmentsShortcuts $InstallDir

if ($HideTechnicalFiles) {
    Hide-GarmentsTechnicalFiles $InstallDir
} else {
    Write-Host "Technical files were left visible because HideTechnicalFiles is false."
}

Write-Host "Update complete. Volumes were preserved."
Write-Host "Rollback: load the previous image tar, set GARMENTSOS_IMAGE in .env to the previous tag, then run docker compose up -d."
