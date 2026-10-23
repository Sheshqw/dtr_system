<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\SystemSetting;
use App\Services\TurnstileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SystemSettingController extends Controller
{
    public function __construct(private TurnstileService $turnstile)
    {
    }

    /**
     * Public — the login page needs the current branding before anyone
     * is authenticated, so this intentionally has no auth middleware.
     * Security-policy fields (lockout threshold, session timeout, etc.) would
     * hand an attacker exactly what they need to time around them, so those
     * are only included when the caller is a signed-in Super Admin —
     * resolved manually here since there's no auth middleware to do it.
     */
    public function show(Request $request)
    {
        $settings = SystemSetting::current();
        $employee = auth('sanctum')->user();

        if ($employee?->role === 'super_admin') {
            return response()->json($this->format($settings));
        }

        return response()->json($this->formatPublic($settings));
    }

    public function update(Request $request)
    {
        $this->gate($request);

        $data = $request->validate([
            'system_name' => 'required|string|max:60',
            'short_name'  => 'required|string|max:30',
            'tagline'     => 'required|string|max:150',
            'logo'        => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            // A wide campus photo for the login page — larger than the logo.
            'login_background' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',

            // Security & session policy — 15 min .. 30 days for the session
            // timeout, generous bounds elsewhere just to keep the values sane.
            'session_timeout_minutes'  => 'required|integer|min:15|max:43200',
            'password_min_length'      => 'required|integer|min:6|max:32',
            'password_require_number'  => 'required|boolean',
            'password_require_symbol'  => 'required|boolean',
            'max_login_attempts'       => 'required|integer|min:3|max:20',
            'lockout_duration_minutes' => 'required|integer|min:1|max:1440',
            'turnstile_enabled'        => 'required|boolean',
        ]);

        // Switching it on without keys would show users a widget that can
        // never load — refuse up front instead.
        if ($data['turnstile_enabled'] && ! $this->turnstile->isConfigured()) {
            throw ValidationException::withMessages([
                'turnstile_enabled' => ['Add TURNSTILE_SITE_KEY and TURNSTILE_SECRET_KEY to the server\'s .env before enabling Cloudflare Turnstile.'],
            ]);
        }

        $settings = SystemSetting::current();

        if ($request->hasFile('logo')) {
            if ($settings->logo_path && Storage::disk('public')->exists($settings->logo_path)) {
                Storage::disk('public')->delete($settings->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('branding', 'public');
        }

        if ($request->hasFile('login_background')) {
            if ($settings->login_background_path && Storage::disk('public')->exists($settings->login_background_path)) {
                Storage::disk('public')->delete($settings->login_background_path);
            }
            $data['login_background_path'] = $request->file('login_background')->store('branding', 'public');
        }

        $data['updated_by'] = $request->user()->id;
        unset($data['logo'], $data['login_background']);

        $settings->update($data);

        return response()->json($this->format($settings->fresh()));
    }

    /**
     * Revert to the bundled default logo.
     */
    public function resetLogo(Request $request)
    {
        $this->gate($request);

        $settings = SystemSetting::current();

        if ($settings->logo_path && Storage::disk('public')->exists($settings->logo_path)) {
            Storage::disk('public')->delete($settings->logo_path);
        }

        $settings->update(['logo_path' => null, 'updated_by' => $request->user()->id]);

        return response()->json($this->format($settings->fresh()));
    }

    /**
     * Revert the login page to its bundled illustrated background.
     */
    public function resetLoginBackground(Request $request)
    {
        $this->gate($request);

        $settings = SystemSetting::current();

        if ($settings->login_background_path && Storage::disk('public')->exists($settings->login_background_path)) {
            Storage::disk('public')->delete($settings->login_background_path);
        }

        $settings->update(['login_background_path' => null, 'updated_by' => $request->user()->id]);

        return response()->json($this->format($settings->fresh()));
    }

    /**
     * System settings apply to every campus (branding, security policy, the
     * Turnstile login check), so only the Super Admin may change them — a
     * campus Admin's authority stops at their own campus.
     */
    private function gate(Request $request): void
    {
        if ($request->user()->role !== 'super_admin') {
            abort(403, 'Only the Super Admin can change system settings.');
        }
    }

    /**
     * Every active campus that has a photo, for the login page slideshow —
     * name and address only, nothing a visitor shouldn't see.
     */
    private function campusSlides(): array
    {
        return Campus::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'address', 'photo_path'])
            ->filter(fn (Campus $c) => $c->photo_url)
            ->map(fn (Campus $c) => [
                'name'       => $c->name,
                'address'    => $c->address,
                'photo_url'  => $c->photo_url,
                'credit'     => $c->photoCredit()[0] ?? null,
                'credit_url' => $c->photoCredit()[1] ?? null,
            ])
            ->values()
            ->all();
    }

    private function formatPublic(SystemSetting $s): array
    {
        $turnstileActive = $s->turnstile_enabled && $this->turnstile->isConfigured();

        return [
            'system_name' => $s->system_name,
            'short_name'  => $s->short_name,
            'tagline'     => $s->tagline,
            'logo_url'    => $s->logo_url,
            'login_background_url' => $s->login_background_url,
            'campus_slides'        => $this->campusSlides(),

            // The login page needs these before anyone signs in. The site key
            // is public by design (it's embedded in the page); the secret key
            // never leaves the server.
            'turnstile' => [
                'enabled'  => $turnstileActive,
                'site_key' => $turnstileActive ? $this->turnstile->siteKey() : null,
            ],
        ];
    }

    private function format(SystemSetting $s): array
    {
        return array_merge($this->formatPublic($s), [
            'updated_at' => $s->updated_at?->format('M j, Y h:i A'),
            'updated_by' => $s->updatedBy?->full_name,

            'session_timeout_minutes'  => $s->session_timeout_minutes,
            'password_min_length'      => $s->password_min_length,
            'password_require_number'  => $s->password_require_number,
            'password_require_symbol'  => $s->password_require_symbol,
            'max_login_attempts'       => $s->max_login_attempts,
            'lockout_duration_minutes' => $s->lockout_duration_minutes,
            'turnstile_enabled'        => $s->turnstile_enabled,
            'turnstile_configured'     => $this->turnstile->isConfigured(),
        ]);
    }
}
