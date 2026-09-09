<?php

namespace App\Providers;

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
        // referenced as Password::default() in validation rules.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());
    }
}
