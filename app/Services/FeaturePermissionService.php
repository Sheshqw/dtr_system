<?php

namespace App\Services;

use App\Models\FeaturePermission;
use App\Models\User;

class FeaturePermissionService
{
    /**
     * All feature keys known to the system, in catalog order.
     */
    public function featureKeys(): array
    {
        return array_keys(config('feature_permissions.features'));
    }

    /**
     * The role-level default grant for every feature, before any per-user override.
     */
    public function roleDefaults(string $role): array
    {
        return config("feature_permissions.role_defaults.{$role}")
            ?? array_fill_keys($this->featureKeys(), false);
    }

    /**
     * feature_key => bool for a given user: role default, overridden by any
     * row in feature_permissions belonging to that user.
     */
    public function effectiveFor(User $user): array
    {
        $effective = $this->roleDefaults($user->role);

        $overrides = FeaturePermission::where('user_id', $user->id)->get(['feature_key', 'granted']);
        foreach ($overrides as $override) {
            $effective[$override->feature_key] = (bool) $override->granted;
        }

        return $effective;
    }

    public function can(User $user, string $featureKey): bool
    {
        $override = FeaturePermission::where('user_id', $user->id)
            ->where('feature_key', $featureKey)
            ->first();

        if ($override) {
            return (bool) $override->granted;
        }

        return (bool) ($this->roleDefaults($user->role)[$featureKey] ?? false);
    }
}
