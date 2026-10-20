<?php

namespace App\Http\Controllers;

use App\Http\Traits\ScopesByCampus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Self-service management of a reviewer's own digital signature — the image
 * that appears on an employee's DTR when that reviewer approved the print
 * request for it (see DtrPrintConsentController, ReportController::form48()).
 * Only admins/HR officers/super admins review print requests, so only they
 * have a signature to manage.
 */
class SignatureController extends Controller
{
    use ScopesByCampus;

    public function show(Request $request)
    {
        $this->assertPrivileged($request);
        $user = $request->user();

        return response()->json([
            'signature_url'     => $user->signature_path ? Storage::url($user->signature_path) : null,
            'signature_enabled' => (bool) $user->signature_enabled,
        ]);
    }

    public function store(Request $request)
    {
        $this->assertPrivileged($request);
        $user = $request->user();

        $request->validate([
            'signature' => 'required|image|mimes:png,jpg,jpeg,webp|max:1024',
        ]);

        if ($user->signature_path && Storage::disk('public')->exists($user->signature_path)) {
            Storage::disk('public')->delete($user->signature_path);
        }

        $path = $request->file('signature')->store('signatures', 'public');
        $user->update(['signature_path' => $path, 'signature_enabled' => true]);

        return response()->json([
            'message'           => 'Signature uploaded. It will now appear on DTRs you approve for download.',
            'signature_url'     => Storage::url($path),
            'signature_enabled' => true,
        ]);
    }

    public function toggle(Request $request)
    {
        $this->assertPrivileged($request);
        $user = $request->user();

        $validated = $request->validate(['enabled' => 'required|boolean']);

        if ($validated['enabled'] && ! $user->signature_path) {
            return response()->json(['message' => 'Upload a signature image first.'], 422);
        }

        $user->update(['signature_enabled' => $validated['enabled']]);

        return response()->json([
            'message'           => $validated['enabled']
                ? 'Your signature will now appear on DTRs you approve for download.'
                : 'Your signature will no longer appear on DTRs — approvals still work as before.',
            'signature_enabled' => $validated['enabled'],
        ]);
    }

    public function destroy(Request $request)
    {
        $this->assertPrivileged($request);
        $user = $request->user();

        if ($user->signature_path && Storage::disk('public')->exists($user->signature_path)) {
            Storage::disk('public')->delete($user->signature_path);
        }

        $user->update(['signature_path' => null, 'signature_enabled' => false]);

        return response()->json(['message' => 'Signature removed.']);
    }

    private function assertPrivileged(Request $request): void
    {
        if (! $this->isPrivileged($request)) {
            abort(403, 'Only HR Officers, Campus Admins, and the Super Admin review print requests, so only they have a signature to manage.');
        }
    }
}
