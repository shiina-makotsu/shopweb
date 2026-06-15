<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use App\Services\CurrencyRateService;
use Illuminate\Console\Command;

class RefreshCurrencySnapshotCommand extends Command
{
    protected $signature = 'shop:currency-refresh {--force : Refresh even if today already has a snapshot}';

    protected $description = 'Refresh the currency exchange-rate and gold-price snapshot.';

    public function handle(CurrencyRateService $rates): int
    {
        $settings = SiteSetting::query()->firstOrCreate([], ['site_name' => config('app.name', 'ShopWeb')]);

        if (! $this->option('force') && $settings->currency_rates_updated_at?->isToday()) {
            $this->info('Currency snapshot already refreshed today.');

            return self::SUCCESS;
        }

        $fresh = $rates->refresh($settings->fresh());
        $rateCount = is_array($fresh->currency_exchange_rates) ? count($fresh->currency_exchange_rates) : 0;
        $gold = $fresh->currency_gold_price ?: 'unavailable';

        $this->info(sprintf(
            'Currency snapshot refreshed: %d rates, gold %s/%s.',
            $rateCount,
            $gold,
            $fresh->currency_gold_unit ?: 'gram',
        ));

        return self::SUCCESS;
    }
}
