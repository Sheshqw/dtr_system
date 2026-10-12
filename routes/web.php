<?php

use App\Http\Controllers\BiometricPushController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ── GRANDING/ZKTeco ADMS push receiver ──────────────────────────────────────
// Device hits: http://192.168.1.100/iclock/cdata  (no /api prefix)
Route::get('/iclock/cdata',      [BiometricPushController::class, 'init']);
Route::post('/iclock/cdata',     [BiometricPushController::class, 'push']);
Route::get('/iclock/getrequest', [BiometricPushController::class, 'heartbeat']);
Route::post('/iclock/devicecmd', [BiometricPushController::class, 'ack']);

// Everything else is served by the Vue SPA (its router shows its own 404
// page for unknown paths). A fallback rather than a `/{any}` catch-all, so
// it only runs when no real route matched — it can never shadow /up or a
// later-registered route — and an unknown /api/* URL gets a proper 404
// (JSON for API clients) instead of the SPA's HTML with a 200.
Route::get('/', fn () => view('app'));
Route::fallback(function (Request $request) {
    abort_if($request->is('api', 'api/*'), 404);

    return view('app');
});
