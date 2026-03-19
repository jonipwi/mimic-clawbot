param(
    [string]$BindAddress,
    [string]$Port,
    [string]$CommandPath,
    [string]$Token,
    [int]$TimeoutSec,
    [switch]$UseGoRun,
    [switch]$NoEnv,
    [switch]$ShowOnly
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Set-Location -Path $PSScriptRoot

function Import-DotEnv {
    param(
        [Parameter(Mandatory = $true)][string]$Path
    )

    if (-not (Test-Path -Path $Path -PathType Leaf)) {
        return
    }

    $lines = Get-Content -Path $Path -ErrorAction Stop
    foreach ($line in $lines) {
        $trimmed = $line.Trim()
        if ([string]::IsNullOrWhiteSpace($trimmed) -or $trimmed.StartsWith('#')) {
            continue
        }

        $idx = $trimmed.IndexOf('=')
        if ($idx -le 0) {
            continue
        }

        $key = $trimmed.Substring(0, $idx).Trim()
        $value = $trimmed.Substring($idx + 1).Trim()
        if ([string]::IsNullOrWhiteSpace($key)) {
            continue
        }

        if (($value.StartsWith('"') -and $value.EndsWith('"')) -or ($value.StartsWith("'") -and $value.EndsWith("'"))) {
            $value = $value.Substring(1, $value.Length - 2)
        }

        if ([string]::IsNullOrEmpty([Environment]::GetEnvironmentVariable($key, 'Process'))) {
            [Environment]::SetEnvironmentVariable($key, $value, 'Process')
        }
    }
}

if (-not $NoEnv) {
    Import-DotEnv -Path (Join-Path $PSScriptRoot '.env')
}

if (-not [string]::IsNullOrWhiteSpace($BindAddress)) {
    [Environment]::SetEnvironmentVariable('MIMIC_BRIDGE_ADDR', $BindAddress, 'Process')
}
if (-not [string]::IsNullOrWhiteSpace($Port)) {
    [Environment]::SetEnvironmentVariable('MIMIC_BRIDGE_PORT', $Port, 'Process')
}
if (-not [string]::IsNullOrWhiteSpace($CommandPath)) {
    [Environment]::SetEnvironmentVariable('MIMIC_BRIDGE_COMMAND', $CommandPath, 'Process')
}
if ($PSBoundParameters.ContainsKey('TimeoutSec')) {
    [Environment]::SetEnvironmentVariable('MIMIC_BRIDGE_TIMEOUT_SEC', [string]$TimeoutSec, 'Process')
}
if (-not [string]::IsNullOrWhiteSpace($Token)) {
    [Environment]::SetEnvironmentVariable('MIMIC_WEB_ENDPOINT_TOKEN', $Token, 'Process')
}

if ([string]::IsNullOrWhiteSpace([Environment]::GetEnvironmentVariable('MIMIC_BRIDGE_PORT', 'Process'))) {
    [Environment]::SetEnvironmentVariable('MIMIC_BRIDGE_PORT', '8099', 'Process')
}
if ([string]::IsNullOrWhiteSpace([Environment]::GetEnvironmentVariable('MIMIC_BRIDGE_ADDR', 'Process'))) {
    [Environment]::SetEnvironmentVariable('MIMIC_BRIDGE_ADDR', '0.0.0.0', 'Process')
}
if ([string]::IsNullOrWhiteSpace([Environment]::GetEnvironmentVariable('MIMIC_BRIDGE_TIMEOUT_SEC', 'Process'))) {
    [Environment]::SetEnvironmentVariable('MIMIC_BRIDGE_TIMEOUT_SEC', '45', 'Process')
}

$defaultExe = Join-Path $PSScriptRoot 'windows\trading-bot-v3.exe'
if ([string]::IsNullOrWhiteSpace([Environment]::GetEnvironmentVariable('MIMIC_BRIDGE_COMMAND', 'Process')) -and (Test-Path -Path $defaultExe -PathType Leaf)) {
    [Environment]::SetEnvironmentVariable('MIMIC_BRIDGE_COMMAND', $defaultExe, 'Process')
}

$bridgeAddr = [Environment]::GetEnvironmentVariable('MIMIC_BRIDGE_ADDR', 'Process')
$bridgePort = [Environment]::GetEnvironmentVariable('MIMIC_BRIDGE_PORT', 'Process')
$bridgeCommand = [Environment]::GetEnvironmentVariable('MIMIC_BRIDGE_COMMAND', 'Process')
$bridgeTimeout = [Environment]::GetEnvironmentVariable('MIMIC_BRIDGE_TIMEOUT_SEC', 'Process')
$hasToken = -not [string]::IsNullOrWhiteSpace([Environment]::GetEnvironmentVariable('MIMIC_WEB_ENDPOINT_TOKEN', 'Process'))

Write-Host 'Mimic bridge configuration:'
Write-Host ('  Address : {0}' -f $bridgeAddr)
Write-Host ('  Port    : {0}' -f $bridgePort)
Write-Host ('  Command : {0}' -f ($(if ([string]::IsNullOrWhiteSpace($bridgeCommand)) { '<auto-detect>' } else { $bridgeCommand })))
Write-Host ('  Timeout : {0}s' -f $bridgeTimeout)
Write-Host ('  Token   : {0}' -f ($(if ($hasToken) { 'set' } else { 'not set' })))
Write-Host ('  Health  : http://127.0.0.1:{0}/healthz' -f $bridgePort)
Write-Host ('  Chat API: http://127.0.0.1:{0}/mimic/chat' -f $bridgePort)

$bridgeExe = Join-Path $PSScriptRoot 'mimic-bridge.exe'
if ((Test-Path -Path $bridgeExe -PathType Leaf) -and (-not $UseGoRun)) {
    if ($ShowOnly) {
        Write-Host ("Command: {0}" -f $bridgeExe)
        return
    }

    Write-Host 'Starting bridge binary...'
    & $bridgeExe
    exit $LASTEXITCODE
}

if ($ShowOnly) {
    Write-Host 'Command: go run ./cmd/mimic-bridge'
    return
}

Write-Host 'Starting bridge via go run...'
go run ./cmd/mimic-bridge
exit $LASTEXITCODE
