<?php

use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\UseRelativeUrls;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        App\Console\Commands\ShopInstallCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = trim((string) env('TRUSTED_PROXIES', '*'));
        $trustedProxyList = match (true) {
            strcasecmp($trustedProxies, 'none') === 0 => null,
            $trustedProxies === '' || $trustedProxies === '*' => '*',
            default => array_map('trim', explode(',', $trustedProxies)),
        };

        $middleware->trustProxies(
            at: $trustedProxyList,
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX,
        );

        $middleware->append(UseRelativeUrls::class);

        $middleware->web(append: [
            EnsureInstalled::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
