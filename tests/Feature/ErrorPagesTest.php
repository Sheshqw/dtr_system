<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Production behaviour: real error pages, never the debug screen.
    config(['app.debug' => false]);

    Route::middleware('web')->get('/__test/abort/{code}', fn (int $code) => abort($code));
    Route::middleware('web')->get('/__test/crash', function () {
        throw new RuntimeException('SQLSTATE[HY000] secret connection details');
    });
});

test('each error code renders its branded page', function (int $code, string $title) {
    $this->get("/__test/abort/{$code}")
        ->assertStatus($code)
        ->assertSee($title)
        ->assertSee('NEMSU · Daily Time Record System');
})->with([
    [403, 'Access denied'],
    [404, 'Page not found'],
    [419, 'Session expired'],
    [429, 'Too many requests'],
    [503, 'Under maintenance'],
]);

test('an unexpected server error shows the friendly 500 page without leaking details', function () {
    $this->get('/__test/crash')
        ->assertStatus(500)
        ->assertSee('Something went wrong')
        ->assertDontSee('SQLSTATE')
        ->assertDontSee('secret connection details');
});

test('api requests still get json errors, not html pages', function () {
    $this->getJson('/api/this-endpoint-does-not-exist')
        ->assertNotFound()
        ->assertHeader('content-type', 'application/json');
});

test('the static fallback page exists for the web server to serve', function () {
    $html = file_get_contents(public_path('errors/unavailable.html'));

    expect($html)
        ->toContain('Temporarily unavailable')
        ->toContain('http-equiv="refresh"')
        ->not->toContain('<?php');
});

test('page addresses still load the app, and the health check is reachable', function () {
    foreach (['/', '/login', '/reports', '/some/deep/link'] as $path) {
        $this->get($path)->assertOk()->assertSee('<div id="app"></div>', false);
    }

    $this->get('/up')->assertOk();
});
