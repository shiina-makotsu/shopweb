<?php

namespace App\Services;

use App\Models\SystemMetricSnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\CarbonInterface;
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
        $samples = $this->rawTimeline(1440);

        return $this->denseTimeline($samples, $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function timelineBetween(CarbonInterface $start, CarbonInterface $end): array
    {
        $start = $start->copy()->startOfMinute();
        $end = $end->copy()->startOfMinute();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        $limit = max(1, $start->diffInMinutes($end) + 1);
        $samples = $this->databaseTimelineBetween($start, $end);

        if ($samples === []) {
            $samples = $this->rawTimeline(max(1440, $limit));
        }

        return $this->denseTimelineBetween($samples, $start, $end);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rawTimeline(int $limit = 60): array
    {
        $limit = max(5, $limit);
        $databaseSamples = $this->databaseTimeline($limit);

        if ($databaseSamples !== []) {
            return $databaseSamples;
        }

        $samples = Cache::get('shop:system-load:samples', []);

        return array_slice(is_array($samples) ? $samples : [], -$limit);
    }

    public function record(): array
    {
        $snapshot = $this->snapshot();
        $minute = now()->copy()->startOfMinute();
        $minuteKey = $minute->format('Y-m-d H:i');
        $snapshot['minute_key'] = $minuteKey;
        $snapshot['time'] = $minute->format('H:i');
        $snapshot['timestamp'] = $minute->getTimestamp();

        $this->persistSnapshot($snapshot, $minute);
        $this->rememberCacheSample($snapshot);
        $this->alertIfAbnormal($snapshot);

        return $snapshot;
    }

    /**
     * @return array<string, mixed>
     */
    public function latestCachedSnapshot(): array
    {
        $samples = Cache::get('shop:system-load:samples', []);
        $samples = is_array($samples) ? $samples : [];
        $latest = is_array(end($samples)) ? end($samples) : null;

        if (is_array($latest)) {
            return $this->withSnapshotDefaults($latest);
        }

        $timeline = $this->databaseTimeline(1);

        if ($timeline !== []) {
            return $this->withSnapshotDefaults($timeline[array_key_last($timeline)]);
        }

        return $this->withSnapshotDefaults($this->emptyTimelineSample(now()->startOfMinute()));
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function rememberCacheSample(array $snapshot): void
    {
        $samples = Cache::get('shop:system-load:samples', []);
        $samples = is_array($samples) ? $samples : [];
        $lastKey = is_array(end($samples)) ? (end($samples)['minute_key'] ?? null) : null;
        $minuteKey = (string) ($snapshot['minute_key'] ?? '');

        if ($lastKey === $minuteKey && $samples !== []) {
            $samples[array_key_last($samples)] = $snapshot;
        } else {
            $samples[] = $snapshot;
        }

        Cache::put('shop:system-load:samples', array_slice($samples, -1440), 90000);
    }

    /**
     * @param  array<int, array<string, mixed>>  $samples
     * @return array<int, array<string, mixed>>
     */
    private function denseTimeline(array $samples, int $limit): array
    {
        $end = now()->copy()->startOfMinute();
        $start = $end->copy()->subMinutes($limit - 1);

        return $this->denseTimelineBetween($samples, $start, $end);
    }

    /**
     * @param  array<int, array<string, mixed>>  $samples
     * @return array<int, array<string, mixed>>
     */
    private function denseTimelineBetween(array $samples, CarbonInterface $start, CarbonInterface $end): array
    {
        $start = $start->copy()->startOfMinute();
        $end = $end->copy()->startOfMinute();
        $limit = max(1, $start->diffInMinutes($end) + 1);
        $byMinute = [];

        foreach ($samples as $sample) {
            if (! is_array($sample)) {
                continue;
            }

            $minuteKey = $this->sampleMinuteKey($sample);

            if ($minuteKey === null) {
                continue;
            }

            $byMinute[$minuteKey] = array_merge($sample, [
                'minute_key' => $minuteKey,
                'sampled' => true,
            ]);
        }

        ksort($byMinute);

        $dense = [];
        $lastSample = null;

        for ($index = 0; $index < $limit; $index++) {
            $minute = $start->copy()->addMinutes($index);
            $minuteKey = $minute->format('Y-m-d H:i');

            if (isset($byMinute[$minuteKey])) {
                $lastSample = $this->normalizeTimelineSample($byMinute[$minuteKey], $minute, true);
                $dense[] = $lastSample;

                continue;
            }

            $dense[] = $lastSample
                ? $this->normalizeTimelineSample($lastSample, $minute, false)
                : $this->emptyTimelineSample($minute);
        }

        return $dense;
    }

    /**
     * @param  array<string, mixed>  $sample
     */
    private function sampleMinuteKey(array $sample): ?string
    {
        $minuteKey = $sample['minute_key'] ?? null;

        if (is_string($minuteKey) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $minuteKey)) {
            return $minuteKey;
        }

        $timestamp = $sample['timestamp'] ?? null;

        if (is_numeric($timestamp)) {
            return now()->setTimestamp((int) $timestamp)->format('Y-m-d H:i');
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $sample
     * @return array<string, mixed>
     */
    private function normalizeTimelineSample(array $sample, CarbonInterface $minute, bool $sampled): array
    {
        return array_merge($sample, [
            'time' => $minute->format('H:i'),
            'timestamp' => $minute->getTimestamp(),
            'minute_key' => $minute->format('Y-m-d H:i'),
            'sampled' => $sampled,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyTimelineSample(CarbonInterface $minute): array
    {
        return [
            'time' => $minute->format('H:i'),
            'timestamp' => $minute->getTimestamp(),
            'minute_key' => $minute->format('Y-m-d H:i'),
            'sampled' => false,
            'db_ms' => 0,
            'db_ok' => false,
            'redis_ms' => 0,
            'redis_ok' => false,
            'php_memory_percent' => 0,
            'php_memory_mb' => 0,
            'php_peak_memory_mb' => 0,
            'server_memory_free_mb' => 0,
            'server_memory_source' => 'none',
            'server_memory_used_percent' => 0,
            'server_cpu_source' => 'none',
            'server_cpu_percent' => 0,
            'cpu_cores' => 1,
            'storage_free_gb' => 0,
            'storage_used_percent' => 0,
            'requests_per_minute' => 0,
            'frontend_requests_per_minute' => 0,
            'admin_requests_per_minute' => 0,
            'queue_connection' => config('queue.default'),
            'cache_store' => config('cache.default'),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function persistSnapshot(array $snapshot, CarbonInterface $minute): void
    {
        try {
            if (! Schema::hasTable('system_metric_snapshots')) {
                return;
            }

            SystemMetricSnapshot::query()->updateOrCreate(
                ['sampled_at' => $minute->copy()->startOfMinute()],
                [
                    'php_memory_mb' => $snapshot['php_memory_mb'] ?? null,
                    'php_memory_percent' => $snapshot['php_memory_percent'] ?? null,
                    'php_peak_memory_mb' => $snapshot['php_peak_memory_mb'] ?? null,
                    'server_memory_total_mb' => $snapshot['server_memory_total_mb'] ?? null,
                    'server_memory_free_mb' => $snapshot['server_memory_free_mb'] ?? null,
                    'server_memory_used_percent' => $snapshot['server_memory_used_percent'] ?? null,
                    'server_memory_source' => $snapshot['server_memory_source'] ?? null,
                    'server_cpu_percent' => $snapshot['server_cpu_percent'] ?? null,
                    'server_cpu_source' => $snapshot['server_cpu_source'] ?? null,
                    'load_1m' => $snapshot['load_1m'] ?? null,
                    'load_5m' => $snapshot['load_5m'] ?? null,
                    'load_15m' => $snapshot['load_15m'] ?? null,
                    'cpu_cores' => $snapshot['cpu_cores'] ?? 1,
                    'db_ms' => $snapshot['db_ms'] ?? null,
                    'db_ok' => (bool) ($snapshot['db_ok'] ?? false),
                    'redis_ms' => $snapshot['redis_ms'] ?? null,
                    'redis_ok' => (bool) ($snapshot['redis_ok'] ?? false),
                    'cache_store' => $snapshot['cache_store'] ?? null,
                    'queue_connection' => $snapshot['queue_connection'] ?? null,
                    'storage_free_gb' => $snapshot['storage_free_gb'] ?? null,
                    'storage_used_percent' => $snapshot['storage_used_percent'] ?? null,
                    'requests_per_minute' => $snapshot['requests_per_minute'] ?? 0,
                    'frontend_requests_per_minute' => $snapshot['frontend_requests_per_minute'] ?? 0,
                    'admin_requests_per_minute' => $snapshot['admin_requests_per_minute'] ?? 0,
                    'request_ms' => $snapshot['request_ms'] ?? null,
                ],
            );

            $this->pruneOldSnapshots($minute);
        } catch (Throwable) {
            //
        }
    }

    private function pruneOldSnapshots(CarbonInterface $now): void
    {
        $days = max(1, (int) config('shop.server_monitor.retention_days', 62));

        SystemMetricSnapshot::query()
            ->where('sampled_at', '<', $now->copy()->subDays($days))
            ->delete();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function databaseTimeline(int $limit): array
    {
        try {
            if (! Schema::hasTable('system_metric_snapshots')) {
                return [];
            }

            return SystemMetricSnapshot::query()
                ->orderByDesc('sampled_at')
                ->limit(max(1, $limit))
                ->get()
                ->reverse()
                ->map(fn (SystemMetricSnapshot $snapshot): array => $this->snapshotModelToTimelineSample($snapshot))
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function databaseTimelineBetween(CarbonInterface $start, CarbonInterface $end): array
    {
        try {
            if (! Schema::hasTable('system_metric_snapshots')) {
                return [];
            }

            return SystemMetricSnapshot::query()
                ->whereBetween('sampled_at', [$start->copy()->startOfMinute(), $end->copy()->startOfMinute()])
                ->orderBy('sampled_at')
                ->get()
                ->map(fn (SystemMetricSnapshot $snapshot): array => $this->snapshotModelToTimelineSample($snapshot))
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotModelToTimelineSample(SystemMetricSnapshot $snapshot): array
    {
        $minute = $snapshot->sampled_at?->copy()->startOfMinute() ?? now()->startOfMinute();

        return [
            'time' => $minute->format('H:i'),
            'timestamp' => $minute->getTimestamp(),
            'minute_key' => $minute->format('Y-m-d H:i'),
            'sampled' => true,
            'php_memory_mb' => $snapshot->php_memory_mb ?? 0,
            'php_memory_percent' => $snapshot->php_memory_percent ?? 0,
            'php_peak_memory_mb' => $snapshot->php_peak_memory_mb ?? 0,
            'server_memory_total_mb' => $snapshot->server_memory_total_mb,
            'server_memory_free_mb' => $snapshot->server_memory_free_mb,
            'server_memory_used_percent' => $snapshot->server_memory_used_percent ?? 0,
            'server_memory_source' => $snapshot->server_memory_source ?? 'snapshot',
            'server_cpu_percent' => $snapshot->server_cpu_percent ?? 0,
            'server_cpu_source' => $snapshot->server_cpu_source ?? 'snapshot',
            'load_1m' => $snapshot->load_1m,
            'load_5m' => $snapshot->load_5m,
            'load_15m' => $snapshot->load_15m,
            'cpu_cores' => $snapshot->cpu_cores,
            'db_ms' => $snapshot->db_ms ?? 0,
            'db_ok' => $snapshot->db_ok,
            'redis_ms' => $snapshot->redis_ms ?? 0,
            'redis_ok' => $snapshot->redis_ok,
            'cache_store' => $snapshot->cache_store ?? config('cache.default'),
            'queue_connection' => $snapshot->queue_connection ?? config('queue.default'),
            'storage_free_gb' => $snapshot->storage_free_gb ?? 0,
            'storage_used_percent' => $snapshot->storage_used_percent ?? 0,
            'requests_per_minute' => $snapshot->requests_per_minute,
            'frontend_requests_per_minute' => $snapshot->frontend_requests_per_minute,
            'admin_requests_per_minute' => $snapshot->admin_requests_per_minute,
            'request_ms' => $snapshot->request_ms ?? 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function withSnapshotDefaults(array $snapshot): array
    {
        return array_merge([
            'server_cpu_percent' => null,
            'server_cpu_source' => 'snapshot',
            'cpu_cores' => 1,
            'server_memory_used_percent' => null,
            'server_memory_free_mb' => 0,
            'server_memory_source' => 'snapshot',
            'db_ok' => false,
            'db_ms' => 0,
            'redis_ok' => false,
            'redis_ms' => 0,
            'requests_per_minute' => 0,
            'frontend_requests_per_minute' => 0,
            'admin_requests_per_minute' => 0,
            'storage_free_gb' => 0,
            'storage_used_percent' => 0,
            'php_memory_mb' => 0,
            'php_memory_percent' => null,
            'php_peak_memory_mb' => 0,
            'queue_connection' => config('queue.default'),
            'cache_store' => config('cache.default'),
        ], $snapshot);
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
