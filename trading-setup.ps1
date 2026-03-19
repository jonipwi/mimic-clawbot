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

Stop-TradingStack

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