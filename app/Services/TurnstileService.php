<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Turnstile — server-side half of the login bot check.
 *
 * The browser widget only hands the user a one-time token; the token means
 * nothing until this service confirms it with Cloudflare using the secret
 * key. Without this check a bot could simply skip the login page and POST
 * to /api/auth/login directly.
 */
class TurnstileService
{
    public const PASSED      = 'passed';
    public const FAILED      = 'failed';
    public const UNAVAILABLE = 'unavailable';

    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    // Cloudflare error codes that mean "our side is misconfigured or theirs
    // is having trouble" rather than "this visitor failed the check".
    // Treated as UNAVAILABLE so a bad secret key or a Cloudflare hiccup can't
    // lock every user — including the Super Admin who'd fix it — out.
    private const SERVICE_ERRORS = ['missing-input-secret', 'invalid-input-secret', 'internal-error'];

    public function isConfigured(): bool
    {
        return filled(config('services.turnstile.site_key'))
            && filled(config('services.turnstile.secret_key'));
    }

    /** Enforced only when both keys are set AND the System Settings toggle is on. */
    public function isActive(): bool
    {
        return $this->isConfigured() && SystemSetting::current()->turnstile_enabled;
    }

    public function siteKey(): ?string
    {
        return config('services.turnstile.site_key');
    }

    /**
     * PASSED      — Cloudflare confirmed the token.
     * FAILED      — missing, invalid, expired or already-used token.
     * UNAVAILABLE — Cloudflare couldn't be reached or reported a service
     *               error; the caller should fail open (allow the login and
     *               leave it to the lockout/rate-limit protections).
     */
    public function verify(?string $token, ?string $ip = null): string
    {
        if (blank($token)) {
            return self::FAILED;
        }

        try {
            $response = Http::asForm()
                ->timeout(config('services.turnstile.timeout', 5))
                ->post(self::VERIFY_URL, array_filter([
                    'secret'   => config('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (ConnectionException $e) {
            Log::warning('Turnstile verification skipped: Cloudflare unreachable.', ['error' => $e->getMessage()]);

            return self::UNAVAILABLE;
        }

        if (! $response->successful()) {
            Log::warning('Turnstile verification skipped: Cloudflare returned HTTP ' . $response->status() . '.');

            return self::UNAVAILABLE;
        }

        if ($response->json('success') === true) {
            return self::PASSED;
        }

        $errors = (array) $response->json('error-codes', []);

        if (array_intersect($errors, self::SERVICE_ERRORS)) {
            Log::error('Turnstile verification skipped: Cloudflare reported ' . implode(', ', $errors) . '.');

            return self::UNAVAILABLE;
        }

        return self::FAILED;
    }
}
