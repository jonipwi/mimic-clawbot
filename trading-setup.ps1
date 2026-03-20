param(
    [string]$BridgeToken = 'bridge-shared-token-8099',
    [switch]$StartBot,
    [switch]$StopAll
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Set-Location -Path $PSScriptRoot

# Ensure logs directory and timestamped file paths
$LogDir = Join-Path $PSScriptRoot 'logs'
if (-not (Test-Path -Path $LogDir -PathType Container)) {
    New-Item -ItemType Directory -Path $LogDir | Out-Null
}
$ts = Get-Date -Format 'yyyyMMdd-HHmmss'
$WebUiOutLog = Join-Path $LogDir ("web-ui-$ts.out.log")
$WebUiErrLog = Join-Path $LogDir ("web-ui-$ts.err.log")
$BridgeOutLog = Join-Path $LogDir ("mimic-bridge-$ts.out.log")
$BridgeErrLog = Join-Path $LogDir ("mimic-bridge-$ts.err.log")
$BotOutLog = Join-Path $LogDir ("mimic-bot-$ts.out.log")
$BotErrLog = Join-Path $LogDir ("mimic-bot-$ts.err.log")

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
$webUiStartParams = @{
    FilePath = 'powershell.exe'
    ArgumentList = @('-NoProfile','-ExecutionPolicy','Bypass','-File',(Join-Path $PSScriptRoot 'start-web-ui.ps1'))
    WorkingDirectory = $PSScriptRoot
    RedirectStandardOutput = $WebUiOutLog
    RedirectStandardError  = $WebUiErrLog
}
Start-Process @webUiStartParams | Out-Null
Write-Host ("  Logs -> {0}" -f $WebUiOutLog)
Write-Host ("          {0}" -f $WebUiErrLog)

Write-Host 'Starting Mimic Bridge in background...'
$bridgeStartParams = @{
    FilePath = 'powershell.exe'
    ArgumentList = @('-NoProfile','-ExecutionPolicy','Bypass','-File',(Join-Path $PSScriptRoot 'start-mimic-bridge.ps1'),'-Token',$BridgeToken)
    WorkingDirectory = $PSScriptRoot
    RedirectStandardOutput = $BridgeOutLog
    RedirectStandardError  = $BridgeErrLog
}
Start-Process @bridgeStartParams | Out-Null

Write-Host 'Mimic Bridge started.'
Write-Host 'Health check: http://127.0.0.1:8099/healthz'
Write-Host ("  Logs -> {0}" -f $BridgeOutLog)
Write-Host ("          {0}" -f $BridgeErrLog)

if ($StartBot) {
    Write-Host 'Starting Mimic Bot in background...'
    $botStartParams = @{
        FilePath = (Join-Path $PSScriptRoot 'mimic-clawbot.exe')
        WorkingDirectory = $PSScriptRoot
        RedirectStandardOutput = $BotOutLog
        RedirectStandardError  = $BotErrLog
    }
    Start-Process @botStartParams | Out-Null
    Write-Host 'Mimic Bot started.'
    Write-Host ("  Logs -> {0}" -f $BotOutLog)
    Write-Host ("          {0}" -f $BotErrLog)
}
else {
    Write-Host 'Run Mimic Bot manually: .\mimic-clawbot.exe'
    Write-Host 'Tip: use -StartBot to auto-start bot after build.'
}