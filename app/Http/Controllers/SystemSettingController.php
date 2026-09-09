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
     * Nothing sensitive is exposed here (name/tagline/logo URL only).
     */
    public function show()
    {
        return response()->json($this->format(SystemSetting::current()));
    }

    public function update(Request $request)
    {
        $this->gate($request);

        $data = $request->validate([
            'system_name' => 'required|string|max:60',
            'short_name'  => 'required|string|max:30',
            'tagline'     => 'required|string|max:150',
            'logo'        => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
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

    private function format(SystemSetting $s): array
    {
        return [
            'system_name' => $s->system_name,
            'short_name'  => $s->short_name,
            'tagline'     => $s->tagline,
            'logo_url'    => $s->logo_url,
            'updated_at'  => $s->updated_at?->format('M j, Y h:i A'),
            'updated_by'  => $s->updatedBy?->full_name,
        ];
    }
}
