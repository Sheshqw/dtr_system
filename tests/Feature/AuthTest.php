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

function failLogin($test, string $email, int $times)
{
    foreach (range(1, $times) as $attempt) {
        $response = $test->postJson('/api/auth/login', ['email' => $email, 'password' => 'wrong-password']);
    }

    return $response;
}

test('an account locks for 5 minutes after 3 failed login attempts', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-password')]);

    failLogin($this, $user->email, 2)
        ->assertStatus(422)
        ->assertJsonMissingPath('retry_after')
        ->assertJsonPath('errors.email.0', 'Invalid email or password. 1 attempt left.');

    failLogin($this, $user->email, 1)
        ->assertStatus(422)
        ->assertJsonPath('retry_after', 300);

    expect($user->fresh()->locked_until->isFuture())->toBeTrue();
});

test('a locked login reports how long is left on the timer', function () {
    $user = User::factory()->create([
        'password'     => bcrypt('correct-password'),
        'locked_until' => now()->addSeconds(120),
    ]);

    $seconds = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'correct-password'])
        ->assertStatus(422)
        ->json('retry_after');

    expect($seconds)->toBeGreaterThan(110)->toBeLessThanOrEqual(120);
});

test('every 3 wrong passwords starts a new timer once the last one ends', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-password')]);

    failLogin($this, $user->email, 3)->assertJsonPath('retry_after', 300);

    $this->travel(6)->minutes();

    failLogin($this, $user->email, 1)->assertJsonMissingPath('retry_after');
    failLogin($this, $user->email, 1)->assertJsonMissingPath('retry_after');
    failLogin($this, $user->email, 1)->assertJsonPath('retry_after', 300);
});

test('a login works again once the timer ends', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-password')]);

    failLogin($this, $user->email, 3);
    $this->travel(6)->minutes();

    $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'correct-password'])
        ->assertStatus(200);

    expect($user->fresh()->locked_until)->toBeNull();
});

test('unknown emails get the same timer, so it cannot reveal which accounts exist', function () {
    failLogin($this, 'nobody@nemsu.edu.ph', 2)
        ->assertJsonPath('errors.email.0', 'Invalid email or password. 1 attempt left.');

    failLogin($this, 'nobody@nemsu.edu.ph', 1)->assertJsonPath('retry_after', 300);

    $seconds = failLogin($this, 'nobody@nemsu.edu.ph', 1)->json('retry_after');
    expect($seconds)->toBeGreaterThan(290)->toBeLessThanOrEqual(300);
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
