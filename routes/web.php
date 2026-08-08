<?php

use App\Http\Controllers\BiometricPushController;
use Illuminate\Support\Facades\Route;

// ── GRANDING/ZKTeco ADMS push receiver ──────────────────────────────────────
// Device hits: http://192.168.1.100/iclock/cdata  (no /api prefix)
Route::get('/iclock/cdata',      [BiometricPushController::class, 'init']);
Route::post('/iclock/cdata',     [BiometricPushController::class, 'push']);
Route::get('/iclock/getrequest', [BiometricPushController::class, 'heartbeat']);
Route::post('/iclock/devicecmd', [BiometricPushController::class, 'ack']);

// All other routes served by the Vue SPA
Route::get('/{any?}', fn() => view('app'))->where('any', '.*');
