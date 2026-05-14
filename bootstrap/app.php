<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            // Role-specific route files (all get web + auth middleware)
            $roleFiles = ['owner', 'doctor', 'receptionist', 'triage', 'laboratory', 'radiology', 'pharmacy', 'patient', 'shared', 'vendor'];
            foreach ($roleFiles as $file) {
                Route::middleware(['web', 'auth'])
                    ->group(base_path("routes/{$file}.php"));
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'active'               => \App\Http\Middleware\EnsureUserIsActive::class,
            'require.credentials'  => \App\Http\Middleware\RequireDocumentVerification::class,
            'auth.sidecar_jwt'     => \App\Http\Middleware\VerifySidecarJwt::class,
        ]);

        // Apply is_active check to all web routes after auth
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureUserIsActive::class);
    })
    ->booting(function () {
        // Configure rate limiters for sensitive endpoints
        RateLimiter::for('ai-analysis', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Clinical second-opinion: expensive, capped tightly per user
        RateLimiter::for('consultation', function (Request $request) {
            return Limit::perMinute(3)->by($request->user()?->id ?: $request->ip());
        });

        // Role-based AI chat queries (RAGFlow + persona routing)
        RateLimiter::for('ai-chat', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('fbr-submit', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('global-search', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('notifications-poll', function (Request $request) {
            // 30-second polling = max 2/min per user; allow 10 to handle tab burst
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
