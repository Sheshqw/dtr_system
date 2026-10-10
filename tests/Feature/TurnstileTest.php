<?php

use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;

const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);

    config([
        'services.turnstile.site_key'   => 'test-site-key',
        'services.turnstile.secret_key' => 'test-secret-key',
    ]);

    $this->user = User::factory()->create([
        'email'    => 'jane@nemsu.edu.ph',
        'password' => bcrypt('correct-password'),
    ]);
});

function enableTurnstile(): void
{
    SystemSetting::current()->update(['turnstile_enabled' => true]);
}

function loginAs(string $password = 'correct-password', ?string $token = 'browser-token'): \Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/auth/login', array_filter([
        'email'           => 'jane@nemsu.edu.ph',
        'password'        => $password,
        'turnstile_token' => $token,
    ]));
}

test('turnstile is not enforced while the setting is off', function () {
    Http::fake();

    loginAs(token: null)->assertOk();

    Http::assertNothingSent();
});

test('turnstile is not enforced when the keys are missing, even if the setting is on', function () {
    config(['services.turnstile.secret_key' => null]);
    enableTurnstile();
    Http::fake();

    loginAs(token: null)->assertOk();

    Http::assertNothingSent();
});

test('a login with a token cloudflare confirms succeeds', function () {
    enableTurnstile();
    Http::fake([VERIFY_URL => Http::response(['success' => true])]);

    loginAs()->assertOk()->assertJsonStructure(['token']);

    Http::assertSent(fn (HttpRequest $request) =>
        $request['secret'] === 'test-secret-key' && $request['response'] === 'browser-token'
    );
});

test('a login without a token is rejected before the password is checked', function () {
    enableTurnstile();
    Http::fake();

    loginAs(password: 'wrong-password', token: null)
        ->assertStatus(422)
        ->assertJsonValidationErrors('turnstile_token');

    // Never reached the lockout logic, and never bothered Cloudflare.
    expect($this->user->fresh()->failed_login_attempts)->toBe(0);
    Http::assertNothingSent();
});

test('a token cloudflare rejects blocks the login and is audit-logged', function () {
    enableTurnstile();
    Http::fake([VERIFY_URL => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);

    loginAs(password: 'wrong-password')
        ->assertStatus(422)
        ->assertJsonValidationErrors('turnstile_token');

    expect($this->user->fresh()->failed_login_attempts)->toBe(0);
    expect(AuditLog::where('action', 'LOGIN_CAPTCHA_FAILED')->exists())->toBeTrue();
});

test('login still works when cloudflare is unreachable (fails open)', function () {
    enableTurnstile();
    Http::fake(fn () => throw new ConnectionException('Could not resolve host'));

    loginAs()->assertOk();

    expect(AuditLog::where('action', 'TURNSTILE_UNAVAILABLE')->exists())->toBeTrue();
});

test('a wrong secret key fails open instead of locking everyone out', function () {
    enableTurnstile();
    Http::fake([VERIFY_URL => Http::response(['success' => false, 'error-codes' => ['invalid-input-secret']])]);

    loginAs()->assertOk();
});

test('failing open never bypasses the password check', function () {
    enableTurnstile();
    Http::fake(fn () => throw new ConnectionException('Could not resolve host'));

    loginAs(password: 'wrong-password')->assertStatus(422)->assertJsonValidationErrors('email');

    expect($this->user->fresh()->failed_login_attempts)->toBe(1);
});

test('the public settings expose the site key only while turnstile is active, never the secret', function () {
    $this->getJson('/api/system-settings')
        ->assertJsonPath('turnstile.enabled', false)
        ->assertJsonPath('turnstile.site_key', null);

    enableTurnstile();

    $response = $this->getJson('/api/system-settings')
        ->assertJsonPath('turnstile.enabled', true)
        ->assertJsonPath('turnstile.site_key', 'test-site-key');

    expect($response->getContent())->not->toContain('test-secret-key');
});

test('turnstile cannot be enabled until both keys are configured', function () {
    config(['services.turnstile.site_key' => null]);
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/system-settings', [
            'system_name'              => 'NEMSU',
            'short_name'               => 'UDTRMS',
            'tagline'                  => 'University Daily Time Record Monitoring System',
            'session_timeout_minutes'  => 1440,
            'password_min_length'      => 8,
            'password_require_number'  => true,
            'password_require_symbol'  => false,
            'max_login_attempts'       => 5,
            'lockout_duration_minutes' => 30,
            'turnstile_enabled'        => true,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('turnstile_enabled');

    expect(SystemSetting::current()->turnstile_enabled)->toBeFalse();
});

test('the content security policy allows the turnstile widget', function () {
    $csp = $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("script-src 'self' https://challenges.cloudflare.com")
        ->toContain('frame-src https://challenges.cloudflare.com');
});
