param(
    [string]$BridgeToken = 'bridge-shared-token-8099',
    [switch]$StartBot,
    [switch]$StopAll
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Set-Location -Path $PSScriptRoot

function Stop-TradingStack {
    param(
        [int[]]$Ports = @(8088, 8099)
    )

    Write-Host 'Stopping running trading-related processes (if any)...'
    $processNames = @('trading-bot', 'trading-bot-v3', 'mimic-clawbot', 'mimic-bridge')
    foreach ($name in $processNames) {
        Get-Process -Name $name -ErrorAction SilentlyContinue | ForEach-Object {
            Stop-Process -Id $_.Id -Force -ErrorAction SilentlyContinue
        }
    }

    foreach ($port in $Ports) {
        $listeners = Get-NetTCPConnection -State Listen -LocalPort $port -ErrorAction SilentlyContinue
        if ($listeners) {
            $ownerIds = $listeners | Select-Object -ExpandProperty OwningProcess -Unique
            foreach ($ownerId in $ownerIds) {
                Stop-Process -Id $ownerId -Force -ErrorAction SilentlyContinue
            }
            Write-Host ("Stopped listeners on port {0}." -f $port)
        }
    }
}

if ($StopAll) {
    Stop-TradingStack
    Write-Host 'All trading stack services were stopped.'
    exit 0
}

function Invoke-GoBuild {
    param(
        [Parameter(Mandatory = $true)][string]$GoOs,
        [Parameter(Mandatory = $true)][string]$GoArch,
        [Parameter(Mandatory = $true)][string]$Output,
        [string]$Target = '.'
    )

    $prevGoOs = $env:GOOS
    $prevGoArch = $env:GOARCH

    try {
        $env:GOOS = $GoOs
        $env:GOARCH = $GoArch

        Write-Host "Building $Output (GOOS=$GoOs GOARCH=$GoArch)..."
        go build -o $Output $Target
        if ($LASTEXITCODE -ne 0) {
            throw "go build failed for $Output"
        }
    }
    finally {
        if ([string]::IsNullOrEmpty($prevGoOs)) {
            Remove-Item Env:GOOS -ErrorAction SilentlyContinue
        }
        else {
            $env:GOOS = $prevGoOs
        }

        if ([string]::IsNullOrEmpty($prevGoArch)) {
            Remove-Item Env:GOARCH -ErrorAction SilentlyContinue
        }
        else {
            $env:GOARCH = $prevGoArch
        }
    }
}

Write-Host 'Checking Go installation...'
go version
if ($LASTEXITCODE -ne 0) {
    throw 'go version failed. Ensure Go is installed and in PATH.'
}

Write-Host 'Tidying go modules...'
go mod tidy
if ($LASTEXITCODE -ne 0) {
    throw 'go mod tidy failed.'
}

Stop-TradingStack

New-Item -ItemType Directory -Force -Path "$PSScriptRoot\linux" | Out-Null

# Windows binaries
Invoke-GoBuild -GoOs 'windows' -GoArch 'amd64' -Output '.\mimic-clawbot.exe'
Invoke-GoBuild -GoOs 'windows' -GoArch 'amd64' -Output '.\mimic-bridge.exe' -Target '.\cmd\mimic-bridge'
# Invoke-GoBuild -GoOs 'windows' -GoArch 'amd64' -Output '.\windows\trading-bot.exe'

# Linux binaries
Invoke-GoBuild -GoOs 'linux' -GoArch 'arm64' -Output '.\mimic-clawbot'
Invoke-GoBuild -GoOs 'linux' -GoArch 'arm64' -Output '.\linux\mimic-bridge' -Target '.\cmd\mimic-bridge'
# Invoke-GoBuild -GoOs 'linux' -GoArch 'arm64' -Output '.\linux\trading-bot'

Write-Host ''
Write-Host 'Build completed successfully.'
# Write-Host 'Run Windows bot: .\windows\trading-bot.exe'

Write-Host 'Starting Trading Web UI...'
Start-Process -FilePath powershell.exe -ArgumentList '-NoProfile','-ExecutionPolicy','Bypass','-File',(Join-Path $PSScriptRoot 'start-web-ui.ps1') -WorkingDirectory $PSScriptRoot | Out-Null

Write-Host 'Starting Mimic Bridge in background...'
Start-Process -FilePath powershell.exe -ArgumentList '-NoProfile','-ExecutionPolicy','Bypass','-File',(Join-Path $PSScriptRoot 'start-mimic-bridge.ps1'),'-Token',$BridgeToken -WorkingDirectory $PSScriptRoot | Out-Null

Write-Host 'Mimic Bridge started.'
Write-Host 'Health check: http://127.0.0.1:8099/healthz'

if ($StartBot) {
    Write-Host 'Starting Mimic Bot in background...'
    Start-Process -FilePath (Join-Path $PSScriptRoot 'mimic-clawbot.exe') -WorkingDirectory $PSScriptRoot | Out-Null
    Write-Host 'Mimic Bot started.'
}
else {
    Write-Host 'Run Mimic Bot manually: .\mimic-clawbot.exe'
    Write-Host 'Tip: use -StartBot to auto-start bot after build.'
}