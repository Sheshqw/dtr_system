<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\FeaturePermission;
use App\Models\User;
use App\Services\FeaturePermissionService;
use Illuminate\Http\Request;

class FeaturePermissionController extends Controller
{
    public function __construct(private FeaturePermissionService $permissions)
    {
    }

    private function assertSuperAdmin(Request $request): void
    {
        if ($request->user()->role !== 'super_admin') {
            abort(403, 'Only Super Admin can manage feature access.');
        }
    }

    /**
     * The feature catalog and each role's default grants — lets the UI
     * render every toggle and know what "reset to default" means.
     */
    public function catalog(Request $request)
    {
        $this->assertSuperAdmin($request);

        return response()->json([
            'features'      => config('feature_permissions.features'),
            'role_defaults' => config('feature_permissions.role_defaults'),
        ]);
    }

    /**
     * A given employee's effective permissions, plus which ones are
     * per-user overrides (as opposed to just inheriting their role default).
     */
    public function show(Request $request, User $user)
    {
        $this->assertSuperAdmin($request);

        $overrides = FeaturePermission::where('user_id', $user->id)
            ->pluck('granted', 'feature_key');

        return response()->json([
            'user' => [
                'id'   => $user->id,
                'name' => $user->full_name,
                'role' => $user->role,
            ],
            'defaults'  => $this->permissions->roleDefaults($user->role),
            'effective' => $this->permissions->effectiveFor($user),
            'overrides' => $overrides,
        ]);
    }

    /**
     * Grant or revoke one feature for one employee, overriding their role default.
     */
    public function update(Request $request)
    {
        $this->assertSuperAdmin($request);

        $data = $request->validate([
            'user_id'     => 'required|exists:employees,id',
            'feature_key' => 'required|string|in:' . implode(',', $this->permissions->featureKeys()),
            'granted'     => 'required|boolean',
        ]);

        $target = User::findOrFail($data['user_id']);

        $existing = FeaturePermission::where('user_id', $target->id)
            ->where('feature_key', $data['feature_key'])
            ->first();
        $oldValue = $existing?->granted ?? $this->permissions->roleDefaults($target->role)[$data['feature_key']];

        $override = FeaturePermission::updateOrCreate(
            ['user_id' => $target->id, 'feature_key' => $data['feature_key']],
            ['granted' => $data['granted'], 'granted_by' => $request->user()->id],
        );

        $label = config("feature_permissions.features.{$data['feature_key']}", $data['feature_key']);
        $verb  = $data['granted'] ? 'Granted' : 'Revoked';

        AuditLog::record(
            'FEATURE_PERMISSION_CHANGED',
            'feature_permissions',
            $override->id,
            ['feature_key' => $data['feature_key'], 'granted' => (bool) $oldValue],
            ['feature_key' => $data['feature_key'], 'granted' => $data['granted']],
            "{$verb} \"{$label}\" for {$target->full_name}",
        );

        return response()->json(['message' => 'Permission updated.', 'effective' => $this->permissions->effectiveFor($target)]);
    }

    /**
     * Grant or revoke one feature for every employee in the system at once —
     * the "All Employees" scope in the Access Control screen, as opposed to
     * update() above which only ever touches the one selected employee.
     */
    public function bulkUpdate(Request $request)
    {
        $this->assertSuperAdmin($request);

        $data = $request->validate([
            'feature_key' => 'required|string|in:' . implode(',', $this->permissions->featureKeys()),
            'granted'     => 'required|boolean',
        ]);

        $userIds = User::pluck('id');

        foreach ($userIds as $userId) {
            FeaturePermission::updateOrCreate(
                ['user_id' => $userId, 'feature_key' => $data['feature_key']],
                ['granted' => $data['granted'], 'granted_by' => $request->user()->id],
            );
        }

        $label = config("feature_permissions.features.{$data['feature_key']}", $data['feature_key']);
        $verb  = $data['granted'] ? 'Granted' : 'Revoked';

        AuditLog::record(
            'FEATURE_PERMISSION_BULK_CHANGED',
            'feature_permissions',
            null,
            null,
            ['feature_key' => $data['feature_key'], 'granted' => $data['granted']],
            "{$verb} \"{$label}\" for all {$userIds->count()} employees",
        );

        return response()->json(['message' => "\"{$label}\" updated for all {$userIds->count()} employees."]);
    }

    /**
     * Remove the per-user override for this feature from every employee at
     * once, so everyone falls back to their own role's default — the bulk
     * counterpart to reset() below.
     */
    public function bulkReset(Request $request, string $featureKey)
    {
        $this->assertSuperAdmin($request);

        if (! in_array($featureKey, $this->permissions->featureKeys())) {
            abort(404);
        }

        $count = FeaturePermission::where('feature_key', $featureKey)->count();
        FeaturePermission::where('feature_key', $featureKey)->delete();

        $label = config("feature_permissions.features.{$featureKey}", $featureKey);

        AuditLog::record(
            'FEATURE_PERMISSION_BULK_RESET',
            'feature_permissions',
            null,
            null,
            ['feature_key' => $featureKey],
            "Reset \"{$label}\" to role defaults for all employees ({$count} override(s) cleared)",
        );

        return response()->json(['message' => "\"{$label}\" reset to role default for everyone."]);
    }

    /**
     * Remove a per-user override so the employee falls back to their role default.
     */
    public function reset(Request $request, User $user, string $featureKey)
    {
        $this->assertSuperAdmin($request);

        if (! in_array($featureKey, $this->permissions->featureKeys())) {
            abort(404);
        }

        $existing = FeaturePermission::where('user_id', $user->id)
            ->where('feature_key', $featureKey)
            ->first();

        if ($existing) {
            $wasGranted = (bool) $existing->granted;
            $existing->delete();

            $label = config("feature_permissions.features.{$featureKey}", $featureKey);

            AuditLog::record(
                'FEATURE_PERMISSION_RESET',
                'feature_permissions',
                $user->id,
                ['feature_key' => $featureKey, 'granted' => $wasGranted],
                ['feature_key' => $featureKey, 'granted' => $this->permissions->roleDefaults($user->role)[$featureKey]],
                "Reset \"{$label}\" to {$user->role} default for {$user->full_name}",
            );
        }

        return response()->json(['message' => 'Reset to role default.', 'effective' => $this->permissions->effectiveFor($user)]);
    }
}
