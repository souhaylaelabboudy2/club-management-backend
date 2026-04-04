<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\ClubRoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 🔴 CRITICAL: Trust Railway's proxy servers
        // Railway sits between your app and users, so we need to trust it
        $middleware->trustProxies(at: '*');
        
        // Enable stateful API (sessions work on API routes)
        $middleware->statefulApi();
        
        // Exclude API routes from CSRF (we're using session cookies instead)
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
        
        // Register custom middleware aliases
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'club_role' => ClubRoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();