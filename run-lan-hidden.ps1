# NEMSU UDTRMS — Silent background launcher (Task Scheduler)
# No need to edit this file when changing networks — run Setup-Network.ps1 instead.

$root = "c:\Users\markn\Herd\dtr_system"
Set-Location $root

# ── Read settings from .env ───────────────────────────────────────
function Get-Env($key, $default = '') {
    $line = Get-Content "$root\.env" | Where-Object { $_ -match "^$key=" } | Select-Object -First 1
    if ($line) { return ($line -split '=', 2)[1].Trim().Trim('"') }
    return $default
}

$LAN_IP   = Get-Env "LAN_IP"       "127.0.0.1"
$WEB_PORT = Get-Env "LAN_WEB_PORT" "8100"
$WS_PORT  = Get-Env "LAN_WS_PORT"  "8080"

# ── Web server ────────────────────────────────────────────────────
Start-Process -FilePath "php" `
    -ArgumentList "-S", "${LAN_IP}:${WEB_PORT}", "-t", "public" `
    -WorkingDirectory $root `
    -WindowStyle Hidden

Start-Sleep -Seconds 2

# ── WebSocket server ──────────────────────────────────────────────
Start-Process -FilePath "php" `
    -ArgumentList "artisan", "reverb:start", "--host=${LAN_IP}", "--port=${WS_PORT}" `
    -WorkingDirectory $root `
    -WindowStyle Hidden

Start-Sleep -Seconds 1

# ── Queue worker ──────────────────────────────────────────────────
Start-Process -FilePath "php" `
    -ArgumentList "artisan", "queue:work", "--queue=default", "--sleep=3", "--tries=3", "--timeout=60" `
    -WorkingDirectory $root `
    -WindowStyle Hidden
