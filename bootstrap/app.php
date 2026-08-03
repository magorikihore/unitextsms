<?php

use App\Http\Middleware\EnsureClientAccess;
use App\Http\Middleware\EnsurePlatformAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'platform' => EnsurePlatformAdmin::class,
            'client' => EnsureClientAccess::class,
            'api.credential' => \App\Http\Middleware\AuthenticateApiCredential::class,
            'dlr.ip' => \App\Http\Middleware\EnsureDlrIpAllowed::class,
        ]);

        $middleware->trustProxies(at: '*');

        $middleware->validateCsrfTokens(except: [
            'dlr/jasmin',
            'dlr/jasmin-submit-error',
            'webhooks/payin',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson()
                || $request->ajax()
                || $request->is('api/*')
                || $request->is('dlr/*'),
        );
    })->create();
