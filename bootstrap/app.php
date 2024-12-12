<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register your custom route middleware here
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'api' => [
                \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class, // Sanctum middleware for API
                'throttle:api',
                \Illuminate\Routing\Middleware\SubstituteBindings::class,
            ],
            'web' => [
                // \App\Http\Middleware\EncryptCookies::class,
                \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
                \Illuminate\Session\Middleware\StartSession::class,
                \Illuminate\View\Middleware\ShareErrorsFromSession::class,
                \App\Http\Middleware\VerifyCsrfToken::class, // CSRF protection for web routes
                \Illuminate\Routing\Middleware\SubstituteBindings::class,
            ],
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
