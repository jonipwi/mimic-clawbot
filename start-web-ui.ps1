param(
    [string]$ListenHost = '127.0.0.1',
    [int]$Port = 8088,
    [switch]$OpenBrowser,
    [switch]$ForceRestart
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Set-Location -Path $PSScriptRoot

$php = Get-Command php -ErrorAction SilentlyContinue
if (-not $php) {
    throw 'PHP was not found in PATH. Install PHP or add php.exe to PATH.'
}

if ($Port -lt 1 -or $Port -gt 65535) {
    throw 'Port must be between 1 and 65535.'
}

$listen = "$ListenHost`:$Port"
$url = "http://$ListenHost`:$Port/"

if ($ForceRestart) {
    $listeners = Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue
    if ($listeners) {
        $ownerIds = $listeners | Select-Object -ExpandProperty OwningProcess -Unique
        foreach ($ownerId in $ownerIds) {
            Stop-Process -Id $ownerId -Force -ErrorAction SilentlyContinue
        }
        Start-Sleep -Milliseconds 300
    }
}

$existing = Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue
if ($existing) {
    throw "Port $Port is already in use. Re-run with -ForceRestart or choose another port."
}

Write-Host 'Starting PHP Web UI server...'
Write-Host ("  Root : {0}" -f (Join-Path $PSScriptRoot 'web'))
Write-Host ("  URL  : {0}" -f $url)
Write-Host 'Press Ctrl+C to stop.'

if ($OpenBrowser) {
    Start-Process $url | Out-Null
}

php -S $listen -t web
