<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\FeaturePermissionService;
use App\Services\TurnstileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private FeaturePermissionService $permissions,
        private TurnstileService $turnstile,
    ) {
    }

    // Deliberately identical whether the email doesn't exist, the account
    // is inactive, or the password is wrong — telling those apart lets an
    // attacker enumerate valid staff emails one probe at a time.
    private const INVALID_CREDENTIALS = 'Invalid email or password.';
    private const TOO_MANY_ATTEMPTS   = 'Too many failed attempts. Please wait before trying again.';

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
            'turnstile_token' => 'nullable|string|max:2048',
        ]);

        // Before any account lookup: a request that fails the bot check never
        // touches failed_login_attempts, so a bot can't lock staff out by
        // spamming wrong passwords at their email addresses.
        $this->ensureHuman($request);

        $employee = User::where('email', $request->email)->first();
        $policy   = SystemSetting::current();

        if (! $employee || ! $employee->is_active) {
            return $this->failUnknownAccount($request->email, $policy);
        }

        if ($employee->locked_until && $employee->locked_until->isFuture()) {
            AuditLog::record('LOGIN_BLOCKED', 'employees', $employee->id, null, null, 'Attempted login while locked out.', $employee->id);

            return $this->lockedResponse(max(1, $employee->locked_until->getTimestamp() - now()->timestamp));
        }

        if (! Hash::check($request->password, $employee->password)) {
            $maxAttempts  = $policy->max_login_attempts;
            $lockoutMins  = $policy->lockout_duration_minutes;

            // A lockout that has run its course starts a fresh round.
            $attempts = ($employee->locked_until ? 0 : $employee->failed_login_attempts) + 1;

            $justLocked = $attempts >= $maxAttempts;
            $employee->failed_login_attempts = $justLocked ? 0 : $attempts;
            $employee->locked_until = $justLocked ? now()->addMinutes($lockoutMins) : null;
            $employee->save();

            AuditLog::record('LOGIN_FAILED', 'employees', $employee->id, null, null, "Wrong password (attempt {$attempts}/{$maxAttempts}).", $employee->id);
            if ($justLocked) {
                AuditLog::record('ACCOUNT_LOCKED', 'employees', $employee->id, null, null, "Locked for {$lockoutMins} minutes after {$maxAttempts} failed attempts.", $employee->id);

                return $this->lockedResponse($lockoutMins * 60);
            }

            $this->throwInvalidCredentials($maxAttempts - $attempts);
        }

        $employee->update(['failed_login_attempts' => 0, 'locked_until' => null]);

        $token = $employee->createToken($this->describeDevice($request))->plainTextToken;

        AuditLog::record('LOGIN', 'employees', $employee->id, null, null, $this->describeDevice($request), $employee->id);

        $employee->loadMissing('department', 'campus');

        return response()->json([
            'token'    => $token,
            'employee' => [
                'id'          => $employee->id,
                'name'        => $employee->full_name,
                'email'       => $employee->email,
                'role'        => $employee->role,
                'photo'       => $employee->photo_path,
                'employee_id' => $employee->employee_id,
                'department'  => $employee->department?->name,
                'position'    => $employee->position,
                'campus_id'   => $employee->campus_id,
                'campus'      => $employee->campus?->only('id', 'name', 'code'),
                'semi_monthly_dtr'    => $employee->usesSemiMonthlyDtr(),
                'feature_permissions' => $this->permissions->effectiveFor($employee),
            ],
        ]);
    }

    /**
     * Unknown and inactive emails get the same attempt count and timer as a
     * real account — if only real accounts could lock, the timer would tell
     * an attacker which staff emails exist. There's no employee row to hold
     * the count, so it lives in the cache.
     */
    private function failUnknownAccount(string $email, SystemSetting $policy)
    {
        // No employee row to attach this to — logged with a null
        // record/user so probing unknown emails still shows up for review.
        AuditLog::record('LOGIN_FAILED', 'employees', null, null, null, 'Unknown or inactive email: ' . $email);

        $key   = 'login-failures:' . sha1(strtolower($email));
        $state = Cache::get($key, ['attempts' => 0, 'locked_until' => 0]);

        if ($state['locked_until'] > now()->timestamp) {
            return $this->lockedResponse($state['locked_until'] - now()->timestamp);
        }

        $attempts = ($state['locked_until'] ? 0 : $state['attempts']) + 1;

        if ($attempts >= $policy->max_login_attempts) {
            $seconds = $policy->lockout_duration_minutes * 60;
            Cache::put($key, ['attempts' => 0, 'locked_until' => now()->timestamp + $seconds], now()->addDay());

            return $this->lockedResponse($seconds);
        }

        Cache::put($key, ['attempts' => $attempts, 'locked_until' => 0], now()->addDay());

        $this->throwInvalidCredentials($policy->max_login_attempts - $attempts);
    }

    private function throwInvalidCredentials(int $attemptsLeft): never
    {
        $suffix = $attemptsLeft === 1 ? '1 attempt left.' : "{$attemptsLeft} attempts left.";

        throw ValidationException::withMessages([
            'email' => [self::INVALID_CREDENTIALS . ' ' . $suffix],
        ]);
    }

    /** retry_after drives the countdown on the login page. */
    private function lockedResponse(int $seconds)
    {
        return response()->json([
            'message'     => self::TOO_MANY_ATTEMPTS,
            'errors'      => ['email' => [self::TOO_MANY_ATTEMPTS]],
            'retry_after' => $seconds,
        ], 422);
    }

    public function logout(Request $request)
    {
        AuditLog::record('LOGOUT', 'employees', $request->user()->id, null, null, null, $request->user()->id);

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => ['required', 'string', 'confirmed', Password::default()],
        ]);

        $employee = $request->user();

        if (! Hash::check($request->current_password, $employee->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $employee->update(['password' => Hash::make($request->password)]);
        $employee->tokens()->delete();

        $token = $employee->createToken($this->describeDevice($request))->plainTextToken;

        return response()->json([
            'message' => 'Password changed successfully.',
            'token'   => $token,
        ]);
    }

    /**
     * List the authenticated user's active sessions (Sanctum tokens).
     */
    public function sessions(Request $request)
    {
        $this->ensureCanManageSessions($request);

        $currentId = $request->user()->currentAccessToken()->id;

        $sessions = $request->user()->tokens()
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($t) => [
                'id'           => $t->id,
                'name'         => $t->name,
                'is_current'   => $t->id === $currentId,
                'last_used_at' => $t->last_used_at?->diffForHumans(),
                'created_at'   => $t->created_at->format('M j, Y h:i A'),
            ]);

        return response()->json($sessions);
    }

    /**
     * Revoke a single session — never the one making the request.
     */
    public function revokeSession(Request $request, int $tokenId)
    {
        $this->ensureCanManageSessions($request);

        $currentId = $request->user()->currentAccessToken()->id;

        if ($tokenId === $currentId) {
            abort(422, "You can't revoke your current session — use Sign Out instead.");
        }

        $deleted = $request->user()->tokens()->where('id', $tokenId)->delete();

        if (! $deleted) {
            abort(404, 'Session not found.');
        }

        return response()->json(['message' => 'Session revoked.']);
    }

    /**
     * Revoke every session except the one making the request.
     */
    public function revokeOtherSessions(Request $request)
    {
        $this->ensureCanManageSessions($request);

        $currentId = $request->user()->currentAccessToken()->id;
        $count     = $request->user()->tokens()->where('id', '!=', $currentId)->delete();

        return response()->json([
            'message' => $count > 0
                ? "{$count} other session(s) signed out."
                : 'No other active sessions.',
        ]);
    }

    /**
     * Session management is hidden from Faculty / Staff accounts in the UI;
     * this keeps the endpoints closed to them too.
     */
    private function ensureCanManageSessions(Request $request): void
    {
        if ($request->user()->role === 'employee') {
            abort(403, 'Faculty and staff accounts cannot manage active sessions.');
        }
    }

    /**
     * Cloudflare Turnstile check — a no-op unless it's configured and enabled
     * in System Settings. Fails open when Cloudflare can't be reached, so an
     * internet outage degrades to the normal lockout/rate-limit protections
     * instead of blocking every login.
     */
    private function ensureHuman(Request $request): void
    {
        if (! $this->turnstile->isActive()) {
            return;
        }

        $result = $this->turnstile->verify($request->input('turnstile_token'), $request->ip());

        if ($result === TurnstileService::UNAVAILABLE) {
            AuditLog::record('TURNSTILE_UNAVAILABLE', 'employees', null, null, null, 'Security check skipped (Cloudflare unreachable) for login as: ' . $request->email);

            return;
        }

        if ($result === TurnstileService::FAILED) {
            AuditLog::record('LOGIN_CAPTCHA_FAILED', 'employees', null, null, null, 'Security check failed for login as: ' . $request->email);

            throw ValidationException::withMessages([
                'turnstile_token' => ['Security check failed. Please complete the verification and try again.'],
            ]);
        }
    }

    /**
     * A short "Browser on OS" label captured at login, so a user reviewing
     * their active sessions can recognize each one (e.g. "Chrome on Windows").
     */
    private function describeDevice(Request $request): string
    {
        $ua = $request->userAgent() ?? '';

        $browser = match (true) {
            str_contains($ua, 'Edg/')                                       => 'Edge',
            str_contains($ua, 'Chrome/') && ! str_contains($ua, 'Chromium') => 'Chrome',
            str_contains($ua, 'Firefox/')                                   => 'Firefox',
            str_contains($ua, 'Safari/') && ! str_contains($ua, 'Chrome')   => 'Safari',
            default                                                         => 'Browser',
        };

        $os = match (true) {
            str_contains($ua, 'Windows')                       => 'Windows',
            str_contains($ua, 'Mac OS X')                       => 'Mac',
            str_contains($ua, 'Android')                        => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Linux')                          => 'Linux',
            default                                              => null,
        };

        return $os ? "{$browser} on {$os}" : $browser;
    }

    public function me(Request $request)
    {
        $employee = $request->user()->load('department', 'shift');

        $employee->loadMissing('campus');

        return response()->json([
            'id'          => $employee->id,
            'name'        => $employee->full_name,
            'first_name'  => $employee->first_name,
            'last_name'   => $employee->last_name,
            'email'       => $employee->email,
            'role'        => $employee->role,
            'employee_id' => $employee->employee_id,
            'position'    => $employee->position,
            'photo'       => $employee->photo_path,
            'campus_id'   => $employee->campus_id,
            'campus'      => $employee->campus?->only('id', 'name', 'code'),
            'department'  => $employee->department?->only('id', 'name', 'code'),
            'shift'       => $employee->shift?->only('id', 'name', 'morning_in', 'afternoon_out'),
            'semi_monthly_dtr'    => $employee->usesSemiMonthlyDtr(),
            'feature_permissions' => $this->permissions->effectiveFor($employee),
        ]);
    }
}
