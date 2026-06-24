<?php

use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\ProtectAgainstRequestAvalanche;
use App\Http\Middleware\UseRelativeUrls;
use Illuminate\Foundation\Application;
use Illuminate\Console\Scheduling\Schedule;
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
        App\Console\Commands\DatabaseHealthCommand::class,
        App\Console\Commands\ExpirePendingPaymentOrdersCommand::class,
        App\Console\Commands\PruneAiTrashCommand::class,
        App\Console\Commands\PrewarmShopCacheCommand::class,
        App\Console\Commands\RefreshCurrencySnapshotCommand::class,
        App\Console\Commands\ShopInstallCommand::class,
        App\Console\Commands\SyncFlashSaleCampaignsCommand::class,
    ])
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('shop:orders-expire-pending-payments')->everyMinute()->withoutOverlapping();
        $schedule->command('shop:currency-refresh')->dailyAt('02:40')->withoutOverlapping();
        $schedule->command('shop:flash-sale-sync')->dailyAt('02:55')->withoutOverlapping();
        $schedule->command('shop:ai-trash-prune')->dailyAt('03:20')->withoutOverlapping();
        $schedule->command('shop:cache-prewarm')->everyTenMinutes()->withoutOverlapping();
    })
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
        $middleware->append(ProtectAgainstRequestAvalanche::class);

        $middleware->web(append: [
            EnsureInstalled::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (Throwable $exception): void {
            try {
                app(App\Services\AlertBotService::class)->notify('ShopWeb P0 未捕获异常', $exception->getMessage(), [
                    'exception' => $exception::class,
                    'url' => request()?->fullUrl(),
                    'ip' => request()?->ip(),
                ], 'P0');
            } catch (Throwable) {
                //
            }
        });
    })->create();
