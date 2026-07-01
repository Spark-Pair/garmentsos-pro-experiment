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
        reason = "launcher_exe_locked"
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

function Copy-GarmentsLauncherSafely($SourcePath, $TargetDir) {
    if (-not (Test-Path -LiteralPath $SourcePath)) {
        return
    }

    $destinationPath = Join-Path $TargetDir "GarmentsOS-PRO-Setup.exe"

    try {
        if (Test-GarmentsFileLocked $destinationPath) {
            Stage-GarmentsLauncherUpdate $SourcePath $destinationPath $TargetDir
            return
        }

        Copy-Item -Force -LiteralPath $SourcePath -Destination $destinationPath
        Write-Host "Installed GUI launcher: $destinationPath"
    } catch {
        Write-Warning "Could not update GUI launcher now. $($_.Exception.Message)"
        try {
            Stage-GarmentsLauncherUpdate $SourcePath $destinationPath $TargetDir
        } catch {
            Write-Warning "Could not stage GUI launcher update. $($_.Exception.Message)"
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
if (Test-Path (Join-Path $ReleaseDir "GarmentsOS-PRO-Setup.exe")) {
    Copy-GarmentsLauncherSafely (Join-Path $ReleaseDir "GarmentsOS-PRO-Setup.exe") $InstallDir
} elseif (Test-Path (Join-Path $ReleaseDir "GarmentsOS PRO Launcher.exe")) {
    Copy-GarmentsLauncherSafely (Join-Path $ReleaseDir "GarmentsOS PRO Launcher.exe") $InstallDir
}
if (Test-Path (Join-Path $ReleaseDir "launcher")) {
    Copy-Item -Recurse -Force (Join-Path $ReleaseDir "launcher") $InstallDir
}
$installedSetupLauncher = Join-Path $InstallDir "GarmentsOS-PRO-Setup.exe"
$installedNestedLauncher = Join-Path $InstallDir "launcher\GarmentsOS PRO Launcher.exe"
if (-not (Test-Path $installedSetupLauncher) -and (Test-Path $installedNestedLauncher)) {
    Copy-GarmentsLauncherSafely $installedNestedLauncher $InstallDir
}
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

if ($HideTechnicalFiles) {
    Hide-GarmentsTechnicalFiles $InstallDir
} else {
    Write-Host "Technical files were left visible because HideTechnicalFiles is false."
}

Register-GarmentsProtocol $InstallDir

Write-Host "Update complete. Volumes were preserved."
Write-Host "Rollback: load the previous image tar, set GARMENTSOS_IMAGE in .env to the previous tag, then run docker compose up -d."
