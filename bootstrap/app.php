<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'lan'     => \App\Http\Middleware\RequireLanAccess::class,
            'feature' => \App\Http\Middleware\CheckFeatureAccess::class,
        ]);
        // All routes are API / SPA — never redirect to route('login'), return 401 JSON instead
        $middleware->redirectGuestsTo(fn() => null);
        // ZKTeco/GRANDING device POSTs have no CSRF token — exempt the ADMS endpoints
        $middleware->validateCsrfTokens(except: [
            'iclock/*',
        ]);
        // Defense-in-depth response headers on every request
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Always return JSON for API unauthenticated errors — never redirect to route('login')
        $exceptions->render(function (
            \Illuminate\Auth\AuthenticationException $e,
            \Illuminate\Http\Request $request
        ) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        });

        // Record genuine server-side failures into System Health. Skip normal
        // control-flow exceptions (validation, auth, 4xx) so the page only
        // fills up with things an admin actually needs to act on.
        $exceptions->reportable(function (\Throwable $e) {
            $skip = $e instanceof \Illuminate\Validation\ValidationException
                || $e instanceof \Illuminate\Auth\AuthenticationException
                || $e instanceof \Illuminate\Auth\Access\AuthorizationException
                || $e instanceof \Illuminate\Http\Exceptions\HttpResponseException
                || ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                    && $e->getStatusCode() < 500);

            if ($skip) return;

            try {
                app(\App\Services\SystemHealthService::class)->record(
                    severity: 'critical',
                    type: 'exception',
                    message: $e->getMessage() ?: get_class($e),
                    file: $e->getFile(),
                    line: $e->getLine(),
                    trace: $e->getTraceAsString(),
                );
            } catch (\Throwable) {
                // Never let health logging break the real error response.
            }
        });
    })->create();
