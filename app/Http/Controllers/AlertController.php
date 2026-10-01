<?php

namespace App\Http\Controllers;

use App\Http\Traits\ScopesByCampus;
use App\Models\SystemHealthLog;
use Illuminate\Http\Request;

/**
 * Aggregates "needs your attention" items from across the system into one
 * lightweight feed for the header notification bell. Each source already
 * has its own full page (System Health) — this endpoint just surfaces
 * counts + deep links so nothing gets missed.
 */
class AlertController extends Controller
{
    use ScopesByCampus;

    public function index(Request $request)
    {
        $alerts = [];

        if ($this->isSuperAdmin($request)) {
            $critical = SystemHealthLog::where('status', 'new')->where('severity', 'critical')->count();
            $warnings = SystemHealthLog::where('status', 'new')->where('severity', '!=', 'critical')->count();

            if ($critical > 0) {
                $alerts[] = $this->item(
                    id: 'health_critical',
                    severity: 'critical',
                    icon: 'shield-alert',
                    title: 'Critical System Issues',
                    message: $critical . ' critical issue' . ($critical === 1 ? '' : 's') . ' need immediate attention.',
                    count: $critical,
                    route: 'system-health',
                );
            }

            if ($warnings > 0) {
                $alerts[] = $this->item(
                    id: 'health_warning',
                    severity: 'warning',
                    icon: 'alert-triangle',
                    title: 'System Warnings',
                    message: $warnings . ' warning' . ($warnings === 1 ? '' : 's') . ' logged by the integrity scanner.',
                    count: $warnings,
                    route: 'system-health',
                );
            }
        }

        usort($alerts, fn($a, $b) => $this->severityWeight($a['severity']) <=> $this->severityWeight($b['severity']));

        return response()->json([
            'alerts' => array_values($alerts),
            'total'  => array_sum(array_column($alerts, 'count')),
        ]);
    }

    private function item(string $id, string $severity, string $icon, string $title, string $message, int $count, string $route): array
    {
        return compact('id', 'severity', 'icon', 'title', 'message', 'count', 'route');
    }

    private function severityWeight(string $severity): int
    {
        return match ($severity) {
            'critical' => 0,
            'warning'  => 1,
            default    => 2,
        };
    }
}
