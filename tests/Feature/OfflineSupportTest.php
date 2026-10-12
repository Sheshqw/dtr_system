<?php

// The service worker is intentionally tiny: an offline page and nothing
// else. These checks guard that contract — if someone later turns it into a
// general-purpose cache, users could get stuck on stale builds or data.

test('the service worker only pre-caches the offline page and its own files', function () {
    $sw = file_get_contents(public_path('sw.js'));

    expect($sw)
        ->toContain("const OFFLINE_ASSETS = [OFFLINE_URL, '/offline.js', '/theme-init.js', '/nemsu.png'];")
        ->toContain("request.mode === 'navigate'")
        ->not->toContain('/build/')
        ->not->toContain('/api/');
});

test('every file the offline page needs exists', function () {
    foreach (['offline.html', 'offline.js', 'theme-init.js', 'nemsu.png', 'sw.js'] as $file) {
        expect(file_exists(public_path($file)))->toBeTrue("public/{$file} is missing");
    }

    expect(file_get_contents(public_path('offline.html')))
        ->toContain("You're offline")
        ->toContain('<script src="/offline.js"></script>')
        ->not->toContain('<script>'); // no inline script — the CSP would block it
});

test('the app page still allows registering a same-origin service worker under the CSP', function () {
    $csp = $this->get('/login')->headers->get('Content-Security-Policy');

    // worker-src falls back to script-src, which must keep allowing 'self'.
    expect($csp)->toContain("script-src 'self'")->not->toContain('worker-src');
});
