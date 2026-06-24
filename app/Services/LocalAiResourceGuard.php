<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class LocalAiResourceGuard
{
    /**
     * @return array<string,mixed>
     */
    public function snapshot(): array
    {
        $memory = $this->memorySnapshot();
        $blockedUntil = (int) Cache::get('shop:local-ai:blocked-until', 0);
        $blocked = $blockedUntil > time();
        $maxPercent = (float) config('services.local_ai.max_memory_percent', 85);
        $minFreeMb = (int) config('services.local_ai.min_free_memory_mb', 1024);
        $enabled = (bool) config('services.local_ai.memory_guard_enabled', true);
        $overLimit = $memory['used_percent'] !== null && $memory['used_percent'] >= $maxPercent;
        $lowFree = $memory['free_mb'] !== null && $memory['free_mb'] <= $minFreeMb;
        $shouldBlock = $enabled && ($blocked || $overLimit || $lowFree);

        return [
            'enabled' => $enabled,
            'blocked' => $shouldBlock,
            'blocked_until' => $blockedUntil ?: null,
            'blocked_seconds' => $blocked ? max(0, $blockedUntil - time()) : 0,
            'reason' => $this->reason($memory, $blocked, $overLimit, $lowFree, $maxPercent, $minFreeMb),
            'stop_url_configured' => filled(config('services.local_ai.stop_url')),
            'max_memory_percent' => $maxPercent,
            'min_free_memory_mb' => $minFreeMb,
            ...$memory,
        ];
    }

    public function assertCanRun(string $feature): void
    {
        $snapshot = $this->snapshot();

        if (! $snapshot['enabled'] || ! $snapshot['blocked']) {
            $this->record($snapshot, $feature, false);

            return;
        }

        $this->trip($snapshot, $feature);

        throw new RuntimeException('本地 AI 已被资源保护闸停止：'.$snapshot['reason']);
    }

    /**
     * @param  array<string,mixed>  $snapshot
     */
    public function trip(array $snapshot, string $feature): void
    {
        $cooldown = max(60, (int) config('services.local_ai.cooldown_seconds', 600));
        Cache::put('shop:local-ai:blocked-until', time() + $cooldown, $cooldown);

        $stopResult = $this->stopRunner();
        $snapshot['stop_result'] = $stopResult;
        $this->record($snapshot, $feature, true);
    }

    /**
     * @return array{ok:bool,message:string}
     */
    private function stopRunner(): array
    {
        $url = trim((string) config('services.local_ai.stop_url', ''));

        if ($url === '') {
            return ['ok' => false, 'message' => '未配置 LOCAL_AI_STOP_URL'];
        }

        try {
            $response = Http::timeout((int) config('services.local_ai.stop_timeout', 5))
                ->post($url);

            return [
                'ok' => $response->successful(),
                'message' => $response->successful()
                    ? '已请求停止本地 AI 服务'
                    : '停止接口返回 HTTP '.$response->status(),
            ];
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => $exception->getMessage()];
        }
    }

    /**
     * @param  array<string,mixed>  $snapshot
     */
    private function record(array $snapshot, string $feature, bool $blocked): void
    {
        $samples = Cache::get('shop:local-ai:samples', []);
        $samples = is_array($samples) ? $samples : [];
        $samples[] = [
            'time' => now()->format('H:i:s'),
            'timestamp' => time(),
            'feature' => $feature,
            'total_mb' => $snapshot['total_mb'],
            'free_mb' => $snapshot['free_mb'],
            'used_percent' => $snapshot['used_percent'],
            'blocked' => $blocked,
            'reason' => $snapshot['reason'],
        ];

        Cache::put('shop:local-ai:samples', array_slice($samples, -120), 900);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function timeline(int $limit = 60): array
    {
        $samples = Cache::get('shop:local-ai:samples', []);

        return array_slice(is_array($samples) ? $samples : [], -max(5, $limit));
    }

    /**
     * @return array{total_mb:int|null,free_mb:int|null,used_mb:int|null,used_percent:float|null,source:string}
     */
    private function memorySnapshot(): array
    {
        $linux = $this->linuxMemory();

        if ($linux['total_mb'] !== null) {
            return $linux;
        }

        return $this->windowsMemory();
    }

    /**
     * @return array{total_mb:int|null,free_mb:int|null,used_mb:int|null,used_percent:float|null,source:string}
     */
    private function linuxMemory(): array
    {
        if (! is_readable('/proc/meminfo')) {
            return ['total_mb' => null, 'free_mb' => null, 'used_mb' => null, 'used_percent' => null, 'source' => 'unknown'];
        }

        $content = (string) @file_get_contents('/proc/meminfo');
        preg_match('/^MemTotal:\s+(\d+)/m', $content, $totalMatch);
        preg_match('/^MemAvailable:\s+(\d+)/m', $content, $freeMatch);

        $totalKb = isset($totalMatch[1]) ? (int) $totalMatch[1] : 0;
        $freeKb = isset($freeMatch[1]) ? (int) $freeMatch[1] : 0;

        return $this->formatMemory($totalKb * 1024, $freeKb * 1024, 'linux');
    }

    /**
     * @return array{total_mb:int|null,free_mb:int|null,used_mb:int|null,used_percent:float|null,source:string}
     */
    private function windowsMemory(): array
    {
        if (stripos(PHP_OS_FAMILY, 'Windows') === false || ! function_exists('shell_exec')) {
            return ['total_mb' => null, 'free_mb' => null, 'used_mb' => null, 'used_percent' => null, 'source' => 'unknown'];
        }

        $output = @shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value 2>NUL');

        if (! is_string($output) || trim($output) === '') {
            return ['total_mb' => null, 'free_mb' => null, 'used_mb' => null, 'used_percent' => null, 'source' => 'unknown'];
        }

        preg_match('/TotalVisibleMemorySize=(\d+)/', $output, $totalMatch);
        preg_match('/FreePhysicalMemory=(\d+)/', $output, $freeMatch);

        $totalKb = isset($totalMatch[1]) ? (int) $totalMatch[1] : 0;
        $freeKb = isset($freeMatch[1]) ? (int) $freeMatch[1] : 0;

        return $this->formatMemory($totalKb * 1024, $freeKb * 1024, 'windows');
    }

    /**
     * @return array{total_mb:int|null,free_mb:int|null,used_mb:int|null,used_percent:float|null,source:string}
     */
    private function formatMemory(int $totalBytes, int $freeBytes, string $source): array
    {
        if ($totalBytes <= 0 || $freeBytes < 0) {
            return ['total_mb' => null, 'free_mb' => null, 'used_mb' => null, 'used_percent' => null, 'source' => 'unknown'];
        }

        $usedBytes = max(0, $totalBytes - $freeBytes);

        return [
            'total_mb' => (int) round($totalBytes / 1048576),
            'free_mb' => (int) round($freeBytes / 1048576),
            'used_mb' => (int) round($usedBytes / 1048576),
            'used_percent' => round(($usedBytes / $totalBytes) * 100, 2),
            'source' => $source,
        ];
    }

    /**
     * @param  array<string,mixed>  $memory
     */
    private function reason(array $memory, bool $blocked, bool $overLimit, bool $lowFree, float $maxPercent, int $minFreeMb): string
    {
        if ($blocked) {
            return '保护冷却中';
        }

        if ($overLimit) {
            return '内存占用 '.$memory['used_percent'].'%，超过 '.$maxPercent.'%';
        }

        if ($lowFree) {
            return '可用内存 '.$memory['free_mb'].' MB，低于 '.$minFreeMb.' MB';
        }

        if ($memory['used_percent'] === null) {
            return '无法读取服务器内存';
        }

        return '资源正常';
    }
}
