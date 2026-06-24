<?php

namespace App\Http\Middleware;

use App\Services\AlertBotService;
use App\Services\SystemLoadMetrics;
use App\Services\TokenBucketLimiter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ProtectAgainstRequestAvalanche
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('shop.load_shedding.enabled', true) || $this->shouldSkip($request)) {
            return $next($request);
        }

        try {
            $scope = $request->is('admin*') ? 'admin' : 'frontend';
            app(SystemLoadMetrics::class)->recordRequest($scope);
            $capacity = (int) config("shop.load_shedding.{$scope}_capacity", $scope === 'admin' ? 90 : 180);
            $refill = (int) config("shop.load_shedding.{$scope}_refill_per_second", $scope === 'admin' ? 20 : 45);
            $identity = $request->user()?->getAuthIdentifier() ?: $request->ip();
            $bucket = app(TokenBucketLimiter::class)->allow($scope.':'.$identity, $capacity, $refill);

            if (! $bucket['allowed']) {
                app(AlertBotService::class)->notify('ShopWeb P0 请求限流触发', '请求量超过令牌桶容量，已返回系统繁忙兜底页。', [
                    'scope' => $scope,
                    'ip' => $request->ip(),
                    'path' => $request->path(),
                    'retry_after' => $bucket['retry_after'],
                ], 'P0');

                return $this->busyResponse($request, (int) $bucket['retry_after']);
            }
        } catch (Throwable) {
            return $next($request);
        }

        return $next($request);
    }

    private function shouldSkip(Request $request): bool
    {
        return $request->is(
            'build/*',
            'favicon.ico',
            'robots.txt',
            'install*',
            'up',
            'livewire/*',
        );
    }

    private function busyResponse(Request $request, int $retryAfter): Response
    {
        $retryAfter = max(1, $retryAfter);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => '系统繁忙，预计十分钟内恢复。',
                'retry_after' => $retryAfter,
                'backoff' => 'exponential-jitter',
            ], 503)->header('Retry-After', (string) $retryAfter);
        }

        return response()
            ->view('errors.busy', [
                'retryAfter' => $retryAfter,
                'recoveryMinutes' => (int) config('shop.load_shedding.busy_recovery_minutes', 10),
            ], 503)
            ->header('Retry-After', (string) $retryAfter);
    }
}
