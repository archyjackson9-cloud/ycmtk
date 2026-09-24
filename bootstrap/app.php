<?php

use App\Http\Middleware\ShareStorefrontData;
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
        // Behind the Caddy reverse proxy: trust its X-Forwarded-* headers so
        // Laravel sees the original https scheme/host (URLs, secure cookies).
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'storefront' => ShareStorefrontData::class,
        ]);

        // The MTN MoMo callback is a server-to-server callback, so it must be
        // exempt from CSRF verification (TOR §5.2 Payment Service).
        $middleware->validateCsrfTokens(except: [
            'webhooks/mtn-momo',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('webhooks/*') || $request->expectsJson(),
        );
    })->create();
