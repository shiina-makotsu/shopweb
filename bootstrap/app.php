<?php

use App\Console\Commands\DatabaseHealthCommand;
use App\Console\Commands\ExpirePendingPaymentOrdersCommand;
use App\Console\Commands\PrewarmShopCacheCommand;
use App\Console\Commands\PruneAiTrashCommand;
use App\Console\Commands\RecordSystemMetricSnapshotCommand;
use App\Console\Commands\RefreshCurrencySnapshotCommand;
use App\Console\Commands\ShopInstallCommand;
use App\Console\Commands\SyncFlashSaleCampaignsCommand;
use App\Console\Commands\SyncTelegramCommand;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\HandleNavigationPrefetch;
use App\Http\Middleware\ProtectAgainstRequestAvalanche;
use App\Http\Middleware\ShowFirstVisitLoadingPage;
use App\Http\Middleware\TrackPageVisits;
use App\Services\AlertBotService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
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
        DatabaseHealthCommand::class,
        ExpirePendingPaymentOrdersCommand::class,
        PruneAiTrashCommand::class,
        PrewarmShopCacheCommand::class,
        RefreshCurrencySnapshotCommand::class,
        RecordSystemMetricSnapshotCommand::class,
        ShopInstallCommand::class,
        SyncFlashSaleCampaignsCommand::class,
        SyncTelegramCommand::class,
    ])
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('shop:orders-expire-pending-payments')->everyMinute()->withoutOverlapping();
        $schedule->command('shop:currency-refresh')->dailyAt('02:40')->withoutOverlapping();
        $schedule->command('shop:flash-sale-sync')->dailyAt('02:55')->withoutOverlapping();
        $schedule->command('shop:ai-trash-prune')->dailyAt('03:20')->withoutOverlapping();
        $schedule->command('shop:cache-prewarm')->everyTenMinutes()->withoutOverlapping();
        $schedule->command('shop:system-metrics-record')->everyMinute()->withoutOverlapping();
        $schedule->command('shop:telegram-sync')->everyMinute()->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: ['telegram/webhook']);
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

        $middleware->append(ProtectAgainstRequestAvalanche::class);
        $middleware->append(TrackPageVisits::class);

        $middleware->web(append: [
            EnsureInstalled::class,
            ShowFirstVisitLoadingPage::class,
            HandleNavigationPrefetch::class,
        ]);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (in_array($request->header('X-ShopWeb-Purpose'), ['storefront-prefetch', 'admin-prefetch'], true)) {
                return response()->noContent()->header('X-ShopWeb-Prefetch', 'authentication-skipped');
            }

            return null;
        });

        $exceptions->respond(function ($response) {
            if (config('app.debug')) {
                return $response;
            }

            $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 500;

            if ($status < 500 || request()?->expectsJson()) {
                return $response;
            }

            return response()
                ->view('errors.500', ['status' => $status], $status)
                ->withHeaders([
                    'Cache-Control' => 'no-store, no-cache, must-revalidate',
                ]);
        });

        $exceptions->report(function (Throwable $exception): void {
            try {
                app(AlertBotService::class)->notify('ShopWeb P0 未捕获异常', $exception->getMessage(), [
                    'exception' => $exception::class,
                    'url' => request()?->fullUrl(),
                    'ip' => request()?->ip(),
                ], 'P0');
            } catch (Throwable) {
                //
            }
        });
    })->create();
