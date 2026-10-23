<?php

use App\Models\SystemSetting;
use App\Models\User;

function settingsPayload(array $overrides = []): array
{
    return array_merge([
        'system_name'              => 'NEMSU',
        'short_name'               => 'UDTRMS',
        'tagline'                  => 'University Daily Time Record Monitoring System',
        'session_timeout_minutes'  => 1440,
        'password_min_length'      => 8,
        'password_require_number'  => true,
        'password_require_symbol'  => false,
        'max_login_attempts'       => 5,
        'lockout_duration_minutes' => 30,
        'turnstile_enabled'        => false,
    ], $overrides);
}

test('only the super admin can change system settings', function () {
    $this->actingAs(User::factory()->superAdmin()->create(), 'sanctum')
        ->postJson('/api/system-settings', settingsPayload(['max_login_attempts' => 7]))
        ->assertOk()
        ->assertJsonPath('max_login_attempts', 7);
});

test('an hr officer cannot change system settings or reset the logo', function () {
    $admin = User::factory()->hrOfficer()->create();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/system-settings', settingsPayload(['system_name' => 'Hijacked']))
        ->assertForbidden();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/system-settings/reset-logo')
        ->assertForbidden();

    expect(SystemSetting::current()->system_name)->toBe('NEMSU');
});

test('an hr officer only receives the public branding, not the security policy', function () {
    $this->actingAs(User::factory()->hrOfficer()->create(), 'sanctum')
        ->getJson('/api/system-settings')
        ->assertOk()
        ->assertJsonStructure(['system_name', 'short_name', 'tagline', 'turnstile'])
        ->assertJsonMissingPath('max_login_attempts')
        ->assertJsonMissingPath('turnstile_enabled');
});

test('the super admin can set a login background photo that the public login page receives, then reset it', function () {
    Illuminate\Support\Facades\Storage::fake('public');
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super, 'sanctum')
        ->post('/api/system-settings', settingsPayload([
            'login_background' => Illuminate\Http\UploadedFile::fake()->image('campus.png', 1920, 1080),
        ]), ['Accept' => 'application/json'])
        ->assertOk();

    $path = SystemSetting::current()->login_background_path;
    Illuminate\Support\Facades\Storage::disk('public')->assertExists($path);

    // Signed out, as the login page fetches it.
    auth()->forgetGuards();
    $this->getJson('/api/system-settings')->assertJsonPath('login_background_url', Illuminate\Support\Facades\Storage::disk('public')->url($path));

    $this->actingAs($super, 'sanctum')
        ->postJson('/api/system-settings/reset-login-background')
        ->assertOk()
        ->assertJsonPath('login_background_url', null);

    Illuminate\Support\Facades\Storage::disk('public')->assertMissing($path);
});

test('an hr officer cannot reset the login background', function () {
    $this->actingAs(User::factory()->hrOfficer()->create(), 'sanctum')
        ->postJson('/api/system-settings/reset-login-background')
        ->assertForbidden();
});
