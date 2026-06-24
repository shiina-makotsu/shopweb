<?php

namespace App\Console\Commands;

use App\Services\CachePrewarmService;
use Illuminate\Console\Command;

class PrewarmShopCacheCommand extends Command
{
    protected $signature = 'shop:cache-prewarm';

    protected $description = 'Prewarm critical storefront cache entries to reduce database pressure.';

    public function handle(CachePrewarmService $prewarm): int
    {
        $result = $prewarm->warm();

        $this->info('Prewarmed '.$result['warmed'].' cache entries.');

        foreach ($result['keys'] as $key) {
            $this->line('- '.$key);
        }

        return self::SUCCESS;
    }
}
