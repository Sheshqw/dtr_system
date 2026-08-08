# NEMSU UDTRMS — LAN Server Launcher
# Usage: Right-click → "Run with PowerShell"
# No need to edit this file when changing networks — run Setup-Network.ps1 instead.

$root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $root

# ── Read settings from .env ───────────────────────────────────────
function Get-Env($key, $default = '') {
    $line = Get-Content "$root\.env" | Where-Object { $_ -match "^$key=" } | Select-Object -First 1
    if ($line) { return ($line -split '=', 2)[1].Trim().Trim('"') }
    return $default
}

$LAN_IP   = Get-Env "LAN_IP"   "127.0.0.1"
$WEB_PORT = Get-Env "LAN_WEB_PORT" "8100"
$WS_PORT  = Get-Env "LAN_WS_PORT"  "8080"

if ($LAN_IP -eq "127.0.0.1") {
    Write-Host ""
    Write-Host "  [WARNING] LAN_IP is not set in .env." -ForegroundColor Yellow
    Write-Host "  Run Setup-Network.ps1 first to configure your network IP." -ForegroundColor Yellow
    Write-Host ""
}

# ── Display ───────────────────────────────────────────────────────
Write-Host ""
Write-Host "  ╔══════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "  ║       NEMSU UDTRMS — LAN Server              ║" -ForegroundColor Cyan
Write-Host "  ╚══════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""
Write-Host "  System URL  :  " -NoNewline -ForegroundColor Gray
Write-Host "http://${LAN_IP}:${WEB_PORT}" -ForegroundColor Green
Write-Host "  WebSocket   :  ws://${LAN_IP}:${WS_PORT}" -ForegroundColor DarkGray
Write-Host ""
Write-Host "  Share the URL above with any device on the same Wi-Fi." -ForegroundColor Yellow
Write-Host "  Employees can log in and print their own DTR records." -ForegroundColor Yellow
Write-Host ""
Write-Host "  Starting 3 servers..." -ForegroundColor DarkGray
Write-Host ""

# ── Web server (artisan serve — multi-threaded, handles concurrent requests) ──
Start-Process powershell -ArgumentList "-NoExit", "-Command", `
    "Write-Host '  [WEB]  http://${LAN_IP}:${WEB_PORT}' -ForegroundColor Green; " + `
    "Set-Location '$root'; " + `
    "php artisan serve --host=${LAN_IP} --port=${WEB_PORT}"

Start-Sleep -Seconds 1

# ── WebSocket server ──────────────────────────────────────────────
Start-Process powershell -ArgumentList "-NoExit", "-Command", `
    "Write-Host '  [WSS]  ws://${LAN_IP}:${WS_PORT}' -ForegroundColor Cyan; " + `
    "Set-Location '$root'; " + `
    "php artisan reverb:start --host=${LAN_IP} --port=${WS_PORT}"

Start-Sleep -Seconds 1

# ── Queue worker ──────────────────────────────────────────────────
Start-Process powershell -ArgumentList "-NoExit", "-Command", `
    "Write-Host '  [QUEUE]  worker running...' -ForegroundColor DarkCyan; " + `
    "Set-Location '$root'; " + `
    "php artisan queue:work --queue=default --sleep=3 --tries=3 --timeout=60"

Write-Host "  All 3 processes started." -ForegroundColor Green
Write-Host "  Close the 3 server windows (or press Ctrl+C here) to stop." -ForegroundColor DarkGray
Write-Host ""
pause
