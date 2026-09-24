<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SystemSettingController extends Controller
{
    /**
     * Public — the login page needs the current branding before anyone
     * is authenticated, so this intentionally has no auth middleware.
     * Security-policy fields (lockout threshold, session timeout, etc.) would
     * hand an attacker exactly what they need to time around them, so those
     * are only included when the caller is a signed-in Super Admin/Admin —
     * resolved manually here since there's no auth middleware to do it.
     */
    public function show(Request $request)
    {
        $settings = SystemSetting::current();
        $employee = auth('sanctum')->user();

        if ($employee && in_array($employee->role, ['super_admin', 'admin'])) {
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

            // Security & session policy — 15 min .. 30 days for the session
            // timeout, generous bounds elsewhere just to keep the values sane.
            'session_timeout_minutes'  => 'required|integer|min:15|max:43200',
            'password_min_length'      => 'required|integer|min:6|max:32',
            'password_require_number'  => 'required|boolean',
            'password_require_symbol'  => 'required|boolean',
            'max_login_attempts'       => 'required|integer|min:3|max:20',
            'lockout_duration_minutes' => 'required|integer|min:1|max:1440',
        ]);

        $settings = SystemSetting::current();

        if ($request->hasFile('logo')) {
            if ($settings->logo_path && Storage::disk('public')->exists($settings->logo_path)) {
                Storage::disk('public')->delete($settings->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('branding', 'public');
        }

        $data['updated_by'] = $request->user()->id;
        unset($data['logo']);

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

    private function gate(Request $request): void
    {
        if (! in_array($request->user()->role, ['super_admin', 'admin'])) {
            abort(403, 'Only Super Admin or Admin can change system settings.');
        }
    }

    private function formatPublic(SystemSetting $s): array
    {
        return [
            'system_name' => $s->system_name,
            'short_name'  => $s->short_name,
            'tagline'     => $s->tagline,
            'logo_url'    => $s->logo_url,
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
        ]);
    }
}
