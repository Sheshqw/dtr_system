<?php

namespace App\Http\Middleware;

use App\Services\FeaturePermissionService;
use Closure;
use Illuminate\Http\Request;

class CheckFeatureAccess
{
    public function __construct(private FeaturePermissionService $permissions)
    {
    }

    public function handle(Request $request, Closure $next, string $featureKey): mixed
    {
        $user = $request->user();

        if (! $user || ! $this->permissions->can($user, $featureKey)) {
            return response()->json(['error' => 'You do not have access to this feature.'], 403);
        }

        return $next($request);
    }
}
