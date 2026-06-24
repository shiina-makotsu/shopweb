<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CachePrewarmService
{
    /**
     * @return array{warmed:int, keys:array<int, string>}
     */
    public function warm(): array
    {
        if (! (bool) config('shop.cache_prewarm.enabled', true)) {
            return ['warmed' => 0, 'keys' => []];
        }

        $lock = Cache::lock('shop:cache-prewarm:lock', 180);

        if (! $lock->get()) {
            return ['warmed' => 0, 'keys' => []];
        }

        try {
            $keys = [];
            $ttl = max(60, (int) config('shop.cache_prewarm.ttl_seconds', 600));

            foreach ($this->payloads() as $key => $payload) {
                Cache::put('shop:prewarm:'.$key, $payload, $ttl);
                $keys[] = $key;
            }

            return [
                'warmed' => count($keys),
                'keys' => $keys,
            ];
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payloads(): array
    {
        $payloads = [
            'home:categories' => fn () => Category::query()->active()->orderBy('sort_order')->orderBy('name')->limit(80)->get(['id', 'name', 'slug']),
            'home:featured-products' => fn () => Product::query()->active()->recommended()->with('variants')->orderByDesc('updated_at')->limit(24)->get(),
            'products:latest' => fn () => Product::query()->active()->with('variants')->latest('updated_at')->limit(48)->get(),
            'routes:critical' => fn () => collect((array) config('shop.cache_prewarm.urls', []))
                ->map(fn (string $url): string => $url)
                ->values()
                ->all(),
        ];

        return collect($payloads)
            ->map(function (callable $resolver) {
                try {
                    return $resolver();
                } catch (Throwable $exception) {
                    return [
                        'error' => $exception->getMessage(),
                    ];
                }
            })
            ->all();
    }
}
