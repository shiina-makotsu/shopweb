<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class TokenBucketLimiter
{
    public function allow(string $key, int $capacity, int $refillPerSecond): array
    {
        $capacity = max(1, $capacity);
        $refillPerSecond = max(1, $refillPerSecond);
        $cacheKey = 'shop:token-bucket:'.sha1($key);
        $now = microtime(true);
        $state = Cache::get($cacheKey);

        if (! is_array($state)) {
            $state = [
                'tokens' => $capacity,
                'updated_at' => $now,
                'denied' => 0,
            ];
        }

        $tokens = min($capacity, (float) ($state['tokens'] ?? $capacity) + max(0, $now - (float) ($state['updated_at'] ?? $now)) * $refillPerSecond);
        $denied = (int) ($state['denied'] ?? 0);

        if ($tokens >= 1) {
            Cache::put($cacheKey, [
                'tokens' => $tokens - 1,
                'updated_at' => $now,
                'denied' => 0,
            ], 120);

            return [
                'allowed' => true,
                'retry_after' => 0,
                'denied' => 0,
            ];
        }

        $denied++;
        Cache::put($cacheKey, [
            'tokens' => $tokens,
            'updated_at' => $now,
            'denied' => $denied,
        ], 120);

        return [
            'allowed' => false,
            'retry_after' => $this->retryAfter($denied),
            'denied' => $denied,
        ];
    }

    private function retryAfter(int $denied): int
    {
        $base = max(1, (int) config('shop.load_shedding.retry_base_seconds', 3));
        $max = max($base, (int) config('shop.load_shedding.retry_max_seconds', 192));
        $exponent = min(10, max(0, $denied - 1));
        $delay = min($max, $base * (2 ** $exponent));
        $jitter = random_int(0, max(1, (int) floor($delay * 0.25)));

        return min($max, $delay + $jitter);
    }
}
