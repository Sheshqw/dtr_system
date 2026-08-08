<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireLanAccess
{
    public function handle(Request $request, Closure $next)
    {
        $allowed = array_filter(array_map(
            'trim',
            explode(',', env('LAN_ALLOWED_IPS', '127.0.0.1,::1'))
        ));

        $clientIp = $request->ip();

        foreach ($allowed as $range) {
            if ($this->ipInRange($clientIp, $range)) {
                return $next($request);
            }
        }

        return response()->json([
            'error'   => 'Access denied.',
            'message' => 'Attendance punching is only allowed from the campus network. Your IP: ' . $clientIp,
        ], 403);
    }

    private function ipInRange(string $ip, string $range): bool
    {
        // Exact match
        if ($ip === $range) return true;

        // CIDR notation (e.g. 192.168.1.0/24)
        if (str_contains($range, '/')) {
            [$subnet, $bits] = explode('/', $range);
            $bits    = (int) $bits;
            $ipLong  = ip2long($ip);
            $subLong = ip2long($subnet);

            if ($ipLong === false || $subLong === false) return false;

            $mask = $bits === 0 ? 0 : (~0 << (32 - $bits));
            return ($ipLong & $mask) === ($subLong & $mask);
        }

        // Wildcard (e.g. 192.168.1.*)
        if (str_contains($range, '*')) {
            $pattern = '/^' . str_replace('\*', '\d+', preg_quote($range, '/')) . '$/';
            return (bool) preg_match($pattern, $ip);
        }

        return false;
    }
}
