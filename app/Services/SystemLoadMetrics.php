<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
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
        $storage = $this->storageUsage();
        $serverMemory = $this->serverMemoryUsage();
        $cpu = $this->cpuUsage();
        $load = $this->loadAverage();
        $requests = $this->requestsPerMinute();

        return [
            'time' => now()->format('H:i:s'),
            'timestamp' => time(),
            'php_memory_mb' => round($memoryUsage / 1048576, 2),
            'php_memory_percent' => $memoryLimit > 0 ? round(($memoryUsage / $memoryLimit) * 100, 2) : null,
            'php_peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
            'server_memory_total_mb' => $serverMemory['total_mb'],
            'server_memory_free_mb' => $serverMemory['free_mb'],
            'server_memory_used_percent' => $serverMemory['used_percent'],
            'server_memory_source' => $serverMemory['source'],
            'server_cpu_percent' => $cpu['percent'],
            'server_cpu_source' => $cpu['source'],
            'load_1m' => $load['1m'],
            'load_5m' => $load['5m'],
            'load_15m' => $load['15m'],
            'cpu_cores' => $load['cores'],
            'db_ms' => $db['ms'],
            'db_ok' => $db['ok'],
            'redis_ms' => $redis['ms'],
            'redis_ok' => $redis['ok'],
            'cache_store' => config('cache.default'),
            'queue_connection' => config('queue.default'),
            'storage_free_gb' => $storage['free_gb'],
            'storage_used_percent' => $storage['used_percent'],
            'requests_per_minute' => $requests['total'],
            'frontend_requests_per_minute' => $requests['frontend'],
            'admin_requests_per_minute' => $requests['admin'],
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
        $this->alertIfAbnormal($snapshot);

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

    /**
     * @return array{total:int,frontend:int,admin:int}
     */
    private function requestsPerMinute(): array
    {
        $minute = Cache::get('shop:system-load:requests:last-minute', now()->format('YmdHi'));
        $counts = Cache::get('shop:system-load:requests:'.$minute, []);
        $counts = is_array($counts) ? $counts : [];
        $frontend = (int) ($counts['frontend'] ?? 0);
        $admin = (int) ($counts['admin'] ?? 0);

        return [
            'total' => $frontend + $admin,
            'frontend' => $frontend,
            'admin' => $admin,
        ];
    }

    /**
     * @return array{free_gb:float,used_percent:float}
     */
    private function storageUsage(): array
    {
        $path = storage_path();
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        return [
            'free_gb' => $free ? round($free / 1073741824, 2) : 0,
            'used_percent' => ($free && $total) ? round((1 - ($free / $total)) * 100, 2) : 0,
        ];
    }

    /**
     * @return array{total_mb:int|null,free_mb:int|null,used_percent:float|null,source:string}
     */
    private function serverMemoryUsage(): array
    {
        if (is_readable('/proc/meminfo')) {
            $content = (string) @file_get_contents('/proc/meminfo');
            preg_match('/^MemTotal:\s+(\d+)/m', $content, $totalMatch);
            preg_match('/^MemAvailable:\s+(\d+)/m', $content, $freeMatch);
            $totalKb = isset($totalMatch[1]) ? (int) $totalMatch[1] : 0;
            $freeKb = isset($freeMatch[1]) ? (int) $freeMatch[1] : 0;

            if ($totalKb > 0) {
                return $this->formatMemory($totalKb * 1024, $freeKb * 1024, 'linux');
            }
        }

        if (stripos(PHP_OS_FAMILY, 'Windows') !== false && function_exists('shell_exec')) {
            $output = @shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value 2>NUL');

            if (is_string($output) && trim($output) !== '') {
                preg_match('/TotalVisibleMemorySize=(\d+)/', $output, $totalMatch);
                preg_match('/FreePhysicalMemory=(\d+)/', $output, $freeMatch);
                $totalKb = isset($totalMatch[1]) ? (int) $totalMatch[1] : 0;
                $freeKb = isset($freeMatch[1]) ? (int) $freeMatch[1] : 0;

                if ($totalKb > 0) {
                    return $this->formatMemory($totalKb * 1024, $freeKb * 1024, 'windows');
                }
            }
        }

        return ['total_mb' => null, 'free_mb' => null, 'used_percent' => null, 'source' => 'unknown'];
    }

    /**
     * @return array{total_mb:int,free_mb:int,used_percent:float,source:string}
     */
    private function formatMemory(int $totalBytes, int $freeBytes, string $source): array
    {
        $usedBytes = max(0, $totalBytes - $freeBytes);

        return [
            'total_mb' => (int) round($totalBytes / 1048576),
            'free_mb' => (int) round($freeBytes / 1048576),
            'used_percent' => round(($usedBytes / max(1, $totalBytes)) * 100, 2),
            'source' => $source,
        ];
    }

    /**
     * @return array{percent:float|null,source:string}
     */
    private function cpuUsage(): array
    {
        if (stripos(PHP_OS_FAMILY, 'Windows') !== false && function_exists('shell_exec')) {
            $output = @shell_exec('wmic cpu get loadpercentage /Value 2>NUL');

            if (is_string($output) && preg_match('/LoadPercentage=(\d+)/', $output, $match)) {
                return ['percent' => (float) $match[1], 'source' => 'windows'];
            }
        }

        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;
        $cores = $this->cpuCores();

        if (is_array($load) && isset($load[0]) && $cores > 0) {
            return [
                'percent' => round(min(100, ((float) $load[0] / $cores) * 100), 2),
                'source' => 'loadavg',
            ];
        }

        return ['percent' => null, 'source' => 'unknown'];
    }

    /**
     * @return array{1m:float|null,5m:float|null,15m:float|null,cores:int}
     */
    private function loadAverage(): array
    {
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;

        return [
            '1m' => is_array($load) ? round((float) ($load[0] ?? 0), 2) : null,
            '5m' => is_array($load) ? round((float) ($load[1] ?? 0), 2) : null,
            '15m' => is_array($load) ? round((float) ($load[2] ?? 0), 2) : null,
            'cores' => $this->cpuCores(),
        ];
    }

    private function cpuCores(): int
    {
        if (! function_exists('shell_exec')) {
            return 1;
        }

        $command = stripos(PHP_OS_FAMILY, 'Windows') !== false ? 'echo %NUMBER_OF_PROCESSORS%' : 'nproc 2>/dev/null';
        $count = (int) trim((string) @shell_exec($command));

        return max(1, $count);
    }

    /**
     * @param  array<string,mixed>  $snapshot
     */
    private function alertIfAbnormal(array $snapshot): void
    {
        if (! (bool) config('shop.server_monitor.enabled', true)) {
            return;
        }

        $issues = [];
        $checks = [
            'CPU' => ['value' => $snapshot['server_cpu_percent'], 'limit' => (float) config('shop.server_monitor.cpu_percent_warning', 90), 'unit' => '%'],
            '服务器内存' => ['value' => $snapshot['server_memory_used_percent'], 'limit' => (float) config('shop.server_monitor.memory_percent_warning', 90), 'unit' => '%'],
            '磁盘' => ['value' => $snapshot['storage_used_percent'], 'limit' => (float) config('shop.server_monitor.disk_percent_warning', 90), 'unit' => '%'],
            'MySQL 延迟' => ['value' => $snapshot['db_ms'], 'limit' => (float) config('shop.server_monitor.db_ms_warning', 1500), 'unit' => 'ms'],
            'Redis 延迟' => ['value' => $snapshot['redis_ms'], 'limit' => (float) config('shop.server_monitor.redis_ms_warning', 1000), 'unit' => 'ms'],
            '请求耗时' => ['value' => $snapshot['request_ms'], 'limit' => (float) config('shop.server_monitor.request_ms_warning', 3000), 'unit' => 'ms'],
            '请求量' => ['value' => $snapshot['requests_per_minute'], 'limit' => (float) config('shop.server_monitor.rpm_warning', 600), 'unit' => '/min'],
        ];

        foreach ($checks as $label => $check) {
            $value = $check['value'];

            if ($value !== null && (float) $value >= (float) $check['limit']) {
                $issues[] = $label.' '.$value.$check['unit'].' >= '.$check['limit'].$check['unit'];
            }
        }

        if ($issues === []) {
            return;
        }

        $fingerprint = md5(implode('|', $issues));
        $cacheKey = 'shop:system-load:p1-alert:'.$fingerprint;

        if (! Cache::add($cacheKey, true, 300)) {
            return;
        }

        app(AlertBotService::class)->notify(
            'ShopWeb P1 服务器负载异常',
            '服务器资源占用过高，可能影响网站可用性：'.implode('；', $issues),
            [
                'snapshot' => collect($snapshot)
                    ->except(['timestamp'])
                    ->map(fn ($value) => is_string($value) ? Str::limit($value, 120) : $value)
                    ->all(),
            ],
            'P1',
        );
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
