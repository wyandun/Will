<?php

use App\Http\Middleware\EnsureModulePermission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all proxies so Railway's X-Forwarded-Proto header is respected.
        // This ensures the request scheme is detected as HTTPS instead of HTTP,
        // fixing mixed-content errors in L5-Swagger and scheme-dependent URLs.
        $middleware->trustProxies(at: '*');
        $middleware->prepend(HandleCors::class);
        $middleware->alias([
            'module.permission' => EnsureModulePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
