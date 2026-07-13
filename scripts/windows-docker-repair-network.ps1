param(
    [string]$InstallDir = "C:\SparkPair\GarmentsOS",
    [int]$Port = 8000
)

$ErrorActionPreference = "Stop"
$InstallDir = $InstallDir.Trim('"')

function Read-EnvValueFromFile($EnvPath, $Name, $DefaultValue = "") {
    try {
        if (-not (Test-Path -LiteralPath $EnvPath)) {
            return $DefaultValue
        }

        foreach ($line in Get-Content -LiteralPath $EnvPath -ErrorAction Stop) {
            $trimmed = ([string]$line).Trim()
            if ($trimmed -match ("^" + [regex]::Escape($Name) + "=(.*)$")) {
                return $Matches[1].Trim().Trim('"').Trim("'")
            }
        }
    } catch {
        Write-Warning "Could not read $Name from .env. $($_.Exception.Message)"
    }

    return $DefaultValue
}

function Ensure-GarmentsFirewallRule($InstallDir, [int]$DefaultPort = 8000) {
    $envPath = Join-Path $InstallDir ".env"
    $portValue = Read-EnvValueFromFile $envPath "APP_PORT" ([string]$DefaultPort)
    $port = 0
    if (-not [int]::TryParse($portValue, [ref]$port) -or $port -le 0) {
        $port = $DefaultPort
    }

    $ruleName = "GarmentsOS PRO $port"
    Write-Host "Ensuring Windows Firewall inbound rule for TCP port $port..."

    try {
        $existing = Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue
        if ($existing) {
            $existing | Set-NetFirewallRule -Enabled True -Direction Inbound -Action Allow -Profile Any -ErrorAction Stop
            $existing | Get-NetFirewallPortFilter | Set-NetFirewallPortFilter -Protocol TCP -LocalPort $port -ErrorAction Stop
            Write-Host "Windows Firewall rule updated: $ruleName"
        } else {
            New-NetFirewallRule -DisplayName $ruleName -Direction Inbound -Action Allow -Protocol TCP -LocalPort $port -Profile Any -RemoteAddress Any -ErrorAction Stop | Out-Null
            Write-Host "Windows Firewall rule created: $ruleName"
        }
    } catch {
        throw "LAN access may be blocked by Windows Firewall. Run this repair launcher as administrator or allow inbound TCP port $port. $($_.Exception.Message)"
    }

    try {
        $profile = Get-NetConnectionProfile -ErrorAction SilentlyContinue |
            Where-Object { $_.NetworkCategory -eq 'Public' } |
            Select-Object -First 1

        if ($profile) {
            Set-NetConnectionProfile -InterfaceIndex $profile.InterfaceIndex -NetworkCategory Private -ErrorAction Stop
            Write-Host "Windows network profile set to Private for LAN access."
        }
    } catch {
        Write-Warning "Could not set Windows network profile to Private. LAN access may still require firewall confirmation. $($_.Exception.Message)"
    }

    Write-Host "LAN firewall repair complete. Client PCs can use http://SERVER_IP:$port"
}

Ensure-GarmentsFirewallRule $InstallDir $Port
