<?php

use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;

beforeEach(function () {
    // These tests exercise AuthController's own lockout logic, not the
    // rate limiter — and cumulative requests across tests in this file
    // would otherwise trip the route's throttle:10,1 middleware.
    $this->withoutMiddleware(ThrottleRequests::class);
});

test('a user can log in with correct credentials', function () {
    $user = User::factory()->create([
        'email'    => 'jane@nemsu.edu.ph',
        'password' => bcrypt('correct-password'),
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email'    => 'jane@nemsu.edu.ph',
        'password' => 'correct-password',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['token', 'employee' => ['id', 'name', 'email', 'role']]);

    expect($user->fresh()->failed_login_attempts)->toBe(0);
});

test('login fails with the wrong password and records a failed attempt', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-password')]);

    $response = $this->postJson('/api/auth/login', [
        'email'    => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('email');
    expect($user->fresh()->failed_login_attempts)->toBe(1);
});

test('login fails for an unknown email without revealing whether the account exists', function () {
    $response = $this->postJson('/api/auth/login', [
        'email'    => 'nobody@nemsu.edu.ph',
        'password' => 'whatever',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('email');
});

test('an account locks for 30 minutes after 5 failed login attempts', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-password')]);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    $user->refresh();
    expect($user->failed_login_attempts)->toBe(5);
    expect($user->locked_until)->not->toBeNull();
    expect($user->locked_until->isFuture())->toBeTrue();
});

test('a locked account is rejected even with the correct password', function () {
    $user = User::factory()->create([
        'password'              => bcrypt('correct-password'),
        'failed_login_attempts' => 5,
        'locked_until'          => now()->addMinutes(30),
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email'    => $user->email,
        'password' => 'correct-password',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('email');
});

test('an inactive employee cannot log in', function () {
    $user = User::factory()->inactive()->create(['password' => bcrypt('correct-password')]);

    $response = $this->postJson('/api/auth/login', [
        'email'    => $user->email,
        'password' => 'correct-password',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('email');
});

test('a successful login resets any prior failed attempt count', function () {
    $user = User::factory()->create([
        'password'              => bcrypt('correct-password'),
        'failed_login_attempts' => 3,
    ]);

    $this->postJson('/api/auth/login', [
        'email'    => $user->email,
        'password' => 'correct-password',
    ])->assertStatus(200);

    expect($user->fresh()->failed_login_attempts)->toBe(0);
});

test('logout revokes the current access token', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/auth/logout')
        ->assertStatus(200);

    expect($user->tokens()->count())->toBe(0);
});

test('faculty and staff accounts cannot view or revoke active sessions', function () {
    $user  = User::factory()->create(['role' => 'employee']);
    $token = $user->createToken('this-device')->plainTextToken;
    $other = $user->createToken('other-device');

    $this->withHeader('Authorization', "Bearer {$token}");

    $this->getJson('/api/auth/sessions')->assertStatus(403);
    $this->deleteJson("/api/auth/sessions/{$other->accessToken->id}")->assertStatus(403);
    $this->postJson('/api/auth/sessions/revoke-others')->assertStatus(403);

    expect($user->tokens()->count())->toBe(2);
});

test('non-employee roles can still manage active sessions', function () {
    $user  = User::factory()->create(['role' => 'hr_officer']);
    $token = $user->createToken('this-device')->plainTextToken;
    $user->createToken('other-device');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/auth/sessions')
        ->assertStatus(200)
        ->assertJsonCount(2);
});

test('an unauthenticated request is rejected with json, never a redirect', function () {
    $response = $this->getJson('/api/auth/me');

    $response->assertStatus(401)->assertJson(['message' => 'Unauthenticated.']);
});
