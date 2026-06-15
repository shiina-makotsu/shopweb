<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Support\CurrencyUnit;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class CurrencyRateService
{
    public function refresh(SiteSetting $settings): SiteSetting
    {
        if (! $this->hasCurrencyColumns()) {
            return $settings;
        }

        $base = CurrencyUnit::normalizeCurrency($settings->currency_base_locked ? $settings->store_currency : CurrencyUnit::baseCurrency());
        $rates = $this->fetchExchangeRates($base);
        $goldPrice = $this->fetchGoldPrice($base);

        $settings->forceFill([
            'store_currency' => $base,
            'currency_base_unit' => $settings->currency_base_unit ?: CurrencyUnit::defaultUnit($base),
            'currency_exchange_rates' => $rates ?: ($settings->currency_exchange_rates ?: [$base => 1]),
            'currency_gold_price' => $goldPrice ?? $settings->currency_gold_price,
            'currency_gold_unit' => 'gram',
            'currency_rates_updated_at' => now(),
        ])->save();

        return $settings->fresh();
    }

    /**
     * @return array<string, float>
     */
    public function fetchExchangeRates(string $base): array
    {
        try {
            $response = Http::timeout(12)->get("https://open.er-api.com/v6/latest/{$base}");

            if (! $response->ok()) {
                return [];
            }

            $rates = $response->json('rates');

            if (! is_array($rates)) {
                return [];
            }

            return collect(CurrencyUnit::currencyOptions())
                ->keys()
                ->mapWithKeys(function (string $code) use ($base, $rates): array {
                    $quote = (float) ($rates[$code] ?? ($code === $base ? 1 : 0));

                    return [$code => $quote > 0 ? round(1 / $quote, 8) : 0.0];
                })
                ->filter(fn (float $rate): bool => $rate > 0)
                ->put($base, 1.0)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function fetchGoldPrice(string $base): ?float
    {
        try {
            $response = Http::timeout(12)->get('https://api.metals.live/v1/spot/gold');

            if (! $response->ok()) {
                return null;
            }

            $rows = $response->json();
            $goldRow = is_array($rows)
                ? collect($rows)->first(fn ($row): bool => is_array($row) && array_key_exists('gold', $row))
                : null;
            $usdPerOunce = is_array($goldRow) ? (float) ($goldRow['gold'] ?? 0) : 0;

            if ($usdPerOunce <= 0) {
                return null;
            }

            $rates = $this->fetchExchangeRates('USD');
            $usdPerBase = (float) ($rates[$base] ?? ($base === 'USD' ? 1 : 0));
            $basePerUsd = $base === 'USD' ? 1.0 : ($usdPerBase > 0 ? 1 / $usdPerBase : 0);

            if ($basePerUsd <= 0) {
                return null;
            }

            return round(($usdPerOunce * $basePerUsd) / 31.1034768, 4);
        } catch (\Throwable) {
            return null;
        }
    }

    public function convert(float $amount, string $from, string $to, array $rates): ?float
    {
        $from = CurrencyUnit::normalizeCurrency($from);
        $to = CurrencyUnit::normalizeCurrency($to);

        $fromRate = (float) ($rates[$from] ?? 0);
        $toRate = (float) ($rates[$to] ?? 0);

        if ($fromRate <= 0 || $toRate <= 0) {
            return null;
        }

        return $amount * $fromRate / $toRate;
    }

    private function hasCurrencyColumns(): bool
    {
        try {
            return Schema::hasTable('site_settings')
                && Schema::hasColumn('site_settings', 'currency_base_locked')
                && Schema::hasColumn('site_settings', 'currency_base_unit')
                && Schema::hasColumn('site_settings', 'currency_exchange_rates')
                && Schema::hasColumn('site_settings', 'currency_gold_price')
                && Schema::hasColumn('site_settings', 'currency_gold_unit')
                && Schema::hasColumn('site_settings', 'currency_rates_updated_at');
        } catch (\Throwable) {
            return false;
        }
    }
}
