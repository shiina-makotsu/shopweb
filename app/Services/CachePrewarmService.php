<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

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
            $keys = [
                ...app(StorefrontCache::class)->prewarm(),
            ];

            if ((bool) config('shop.cache_prewarm.include_admin', false)) {
                $keys = [
                    ...$keys,
                    ...app(AdminDashboardCache::class)->prewarm(),
                ];
            }

            return [
                'warmed' => count($keys),
                'keys' => $keys,
            ];
        } finally {
            optional($lock)->release();
        }
    }
}
