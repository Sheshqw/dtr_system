# NEMSU UDTRMS — Network Setup
# Run this whenever you move to a different Wi-Fi or LAN.
# It auto-detects your IP, updates .env, and rebuilds the frontend.
# Usage: Right-click → "Run with PowerShell"

$root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $root

$WEB_PORT = 8100
$WS_PORT  = 8080

Write-Host ""
Write-Host "  ╔══════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "  ║     NEMSU UDTRMS — Network Setup             ║" -ForegroundColor Cyan
Write-Host "  ╚══════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

# ── Step 1: Auto-detect LAN IP ────────────────────────────────────
Write-Host "  [1/4] Detecting your LAN IP address..." -ForegroundColor DarkGray

$candidates = Get-NetIPAddress -AddressFamily IPv4 | Where-Object {
    $_.IPAddress -notlike "127.*" -and
    $_.IPAddress -notlike "169.254.*" -and
    $_.PrefixLength -lt 32
} | Sort-Object InterfaceMetric

$detected = $candidates | Select-Object -First 1

if (-not $detected) {
    Write-Host ""
    Write-Host "  [ERROR] Could not detect a LAN IP address." -ForegroundColor Red
    Write-Host "  Make sure you are connected to a Wi-Fi or LAN network." -ForegroundColor Red
    Write-Host ""
    pause
    exit 1
}

$LAN_IP = $detected.IPAddress

# Show all candidates if more than one
if ($candidates.Count -gt 1) {
    Write-Host ""
    Write-Host "  Multiple network interfaces found:" -ForegroundColor Yellow
    $i = 0
    $candidates | ForEach-Object {
        $marker = if ($i -eq 0) { " (auto-selected)" } else { "" }
        Write-Host "    [$i] $($_.IPAddress)  — $($_.InterfaceAlias)$marker" -ForegroundColor White
        $i++
    }
    Write-Host ""
    $choice = Read-Host "  Press Enter to use $LAN_IP, or type a number to pick a different one"
    if ($choice -match '^\d+$' -and [int]$choice -lt $candidates.Count) {
        $LAN_IP = ($candidates | Select-Object -Index ([int]$choice)).IPAddress
    }
}

Write-Host "  IP selected: $LAN_IP" -ForegroundColor Green

# ── Step 2: Update .env ───────────────────────────────────────────
Write-Host ""
Write-Host "  [2/4] Updating .env..." -ForegroundColor DarkGray

$envPath = "$root\.env"
$subnet  = ($LAN_IP -split '\.')[0..2] -join '.'   # e.g. 192.168.1

$content = Get-Content $envPath -Raw

$content = $content -replace '(?m)^APP_URL=.*$',         "APP_URL=http://${LAN_IP}:${WEB_PORT}"
$content = $content -replace '(?m)^REVERB_HOST=.*$',     "REVERB_HOST=`"${LAN_IP}`""
$content = $content -replace '(?m)^LAN_IP=.*$',          "LAN_IP=${LAN_IP}"
$content = $content -replace '(?m)^LAN_WEB_PORT=.*$',    "LAN_WEB_PORT=${WEB_PORT}"
$content = $content -replace '(?m)^LAN_WS_PORT=.*$',     "LAN_WS_PORT=${WS_PORT}"
$content = $content -replace '(?m)^LAN_ALLOWED_IPS=.*$', "LAN_ALLOWED_IPS=127.0.0.1,::1,${subnet}.*"

Set-Content $envPath $content -Encoding UTF8 -NoNewline
Write-Host "  .env updated." -ForegroundColor Green

# ── Step 3: Clear Laravel config cache ───────────────────────────
Write-Host ""
Write-Host "  [3/4] Clearing Laravel config cache..." -ForegroundColor DarkGray
php artisan config:clear 2>&1 | Out-Null
Write-Host "  Config cache cleared." -ForegroundColor Green

# ── Step 4: Rebuild frontend ──────────────────────────────────────
Write-Host ""
Write-Host "  [4/4] Rebuilding frontend (baking new IP into JS bundle)..." -ForegroundColor DarkGray
$build = npm run build 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "  Frontend rebuilt." -ForegroundColor Green
} else {
    Write-Host "  [WARNING] Frontend build failed. Run 'npm run build' manually." -ForegroundColor Yellow
    Write-Host ($build | Select-String "error" | Select-Object -First 5) -ForegroundColor Red
}

# ── Done ──────────────────────────────────────────────────────────
Write-Host ""
Write-Host "  ╔══════════════════════════════════════════════╗" -ForegroundColor Green
Write-Host "  ║  Setup complete!                             ║" -ForegroundColor Green
Write-Host "  ╚══════════════════════════════════════════════╝" -ForegroundColor Green
Write-Host ""
Write-Host "  System URL : http://${LAN_IP}:${WEB_PORT}" -ForegroundColor Cyan
Write-Host ""
Write-Host "  Next step  : Run Start-LanServer.ps1 to start the servers." -ForegroundColor Yellow
Write-Host ""
pause
