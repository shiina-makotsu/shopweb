<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class SystemLoadMetrics
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $startedAt = microtime(true);
        $db = $this->pingDatabase();
        $redis = $this->pingRedis();
        $memoryLimit = $this->bytesFromIni((string) ini_get('memory_limit'));
        $memoryUsage = memory_get_usage(true);

        return [
            'time' => now()->format('H:i:s'),
            'timestamp' => time(),
            'php_memory_mb' => round($memoryUsage / 1048576, 2),
            'php_memory_percent' => $memoryLimit > 0 ? round(($memoryUsage / $memoryLimit) * 100, 2) : null,
            'db_ms' => $db['ms'],
            'db_ok' => $db['ok'],
            'redis_ms' => $redis['ms'],
            'redis_ok' => $redis['ok'],
            'cache_store' => config('cache.default'),
            'requests_per_minute' => $this->requestsPerMinute(),
            'request_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ];
    }

    public function recordRequest(string $scope): void
    {
        try {
            $minute = now()->format('YmdHi');
            $key = 'shop:system-load:requests:'.$minute;
            $counts = Cache::get($key, []);
            $counts = is_array($counts) ? $counts : [];
            $counts[$scope] = (int) ($counts[$scope] ?? 0) + 1;
            Cache::put('shop:system-load:requests:last-minute', $minute, 180);
            Cache::put($key, $counts, 180);
        } catch (Throwable) {
            //
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function timeline(int $limit = 60): array
    {
        $limit = max(5, $limit);
        $samples = Cache::get('shop:system-load:samples', []);

        return array_slice(is_array($samples) ? $samples : [], -$limit);
    }

    public function record(): array
    {
        $snapshot = $this->snapshot();
        $samples = $this->timeline(120);
        $samples[] = $snapshot;
        $samples = array_slice($samples, -120);

        Cache::put('shop:system-load:samples', $samples, 900);

        return $snapshot;
    }

    /**
     * @return array{ok:bool,ms:float|null}
     */
    private function pingDatabase(): array
    {
        $startedAt = microtime(true);

        try {
            DB::select('select 1');

            return [
                'ok' => true,
                'ms' => round((microtime(true) - $startedAt) * 1000, 2),
            ];
        } catch (Throwable) {
            return [
                'ok' => false,
                'ms' => null,
            ];
        }
    }

    /**
     * @return array{ok:bool,ms:float|null}
     */
    private function pingRedis(): array
    {
        if (config('cache.default') !== 'redis' && config('database.redis.client') === null) {
            return ['ok' => false, 'ms' => null];
        }

        $startedAt = microtime(true);

        try {
            Redis::connection(config('cache.stores.redis.connection', 'cache'))->ping();

            return [
                'ok' => true,
                'ms' => round((microtime(true) - $startedAt) * 1000, 2),
            ];
        } catch (Throwable) {
            return [
                'ok' => false,
                'ms' => null,
            ];
        }
    }

    private function requestsPerMinute(): int
    {
        $minute = Cache::get('shop:system-load:requests:last-minute', now()->format('YmdHi'));
        $counts = Cache::get('shop:system-load:requests:'.$minute, []);
        $counts = is_array($counts) ? $counts : [];

        return (int) ($counts['frontend'] ?? 0) + (int) ($counts['admin'] ?? 0);
    }

    private function bytesFromIni(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
