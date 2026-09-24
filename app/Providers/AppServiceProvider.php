<?php

namespace App\Providers;

use App\Models\SystemSetting;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Baseline password policy used everywhere a password is set —
        // referenced as Password::default() in validation rules. Letters are
        // always required regardless of the configured policy; only the
        // minimum length and the number/symbol requirements are admin-editable
        // (System Settings → Security & Session Policy).
        Password::defaults(function () {
            $settings = SystemSetting::current();
            $rule = Password::min($settings->password_min_length)->letters();
            if ($settings->password_require_number) {
                $rule = $rule->numbers();
            }
            if ($settings->password_require_symbol) {
                $rule = $rule->symbols();
            }
            return $rule;
        });

        // How long a login token stays valid, admin-editable from the same
        // settings screen. Skipped for console commands (migrations, tests,
        // queue workers never authenticate via Sanctum) so it never runs
        // before the settings table itself has been migrated, and wrapped
        // defensively since boot() runs before the request has a chance to
        // report a friendly error if the DB isn't reachable yet.
        if (! $this->app->runningInConsole()) {
            try {
                config(['sanctum.expiration' => SystemSetting::current()->session_timeout_minutes]);
            } catch (\Throwable $e) {
                // Keep config/sanctum.php's static default.
            }
        }
    }
}
