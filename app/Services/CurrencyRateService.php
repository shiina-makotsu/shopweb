<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Support\CurrencyUnit;
use Illuminate\Http\Client\PendingRequest;
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
        $goldPrice = $this->fetchGoldPrice($base, $rates);

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
        $base = CurrencyUnit::normalizeCurrency($base);
        $quotes = $this->fetchOpenExchangeQuotes($base)
            ?: $this->fetchFrankfurterQuotes($base)
            ?: $this->fetchCurrencyApiQuotes($base);

        $rates = $quotes === [] ? [] : $this->ratesFromBaseQuotes($base, $quotes);

        return $this->withFallbackRates($base, $rates);
    }

    public function fetchGoldPrice(string $base, ?array $rates = null): ?float
    {
        $base = CurrencyUnit::normalizeCurrency($base);
        $usdPerOunce = $this->fetchGoldUsdPerOunce();

        if ($usdPerOunce === null || $usdPerOunce <= 0) {
            return null;
        }

        $rates ??= $this->fetchExchangeRates($base);
        $basePerUsd = $base === 'USD' ? 1.0 : (float) ($rates['USD'] ?? 0);

        if ($basePerUsd <= 0) {
            $basePerUsd = (float) ($this->fallbackExchangeRates($base)['USD'] ?? 0);
        }

        if ($basePerUsd <= 0) {
            return null;
        }

        return round(($usdPerOunce * $basePerUsd) / 31.1034768, 4);
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

    /**
     * @return array<string, float>
     */
    private function fetchOpenExchangeQuotes(string $base): array
    {
        try {
            $response = $this->http()->get("https://open.er-api.com/v6/latest/{$base}");

            if (! $response->ok()) {
                return [];
            }

            $rates = $response->json('rates');

            return is_array($rates) ? $this->numericRates($rates) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, float>
     */
    private function fetchFrankfurterQuotes(string $base): array
    {
        try {
            $response = $this->http()->get('https://api.frankfurter.app/latest', ['from' => $base]);

            if (! $response->ok()) {
                return [];
            }

            $rates = $response->json('rates');

            return is_array($rates) ? $this->numericRates($rates) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, float>
     */
    private function fetchCurrencyApiQuotes(string $base): array
    {
        try {
            $lowerBase = strtolower($base);
            $response = $this->http()->get("https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/{$lowerBase}.json");

            if (! $response->ok()) {
                return [];
            }

            $rates = $response->json($lowerBase);

            if (! is_array($rates)) {
                return [];
            }

            return collect($rates)
                ->mapWithKeys(fn ($rate, string $code): array => [strtoupper($code) => (float) $rate])
                ->filter(fn (float $rate): bool => $rate > 0)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function fetchGoldUsdPerOunce(): ?float
    {
        return $this->fetchMetalsLiveGold()
            ?? $this->fetchStooqGold()
            ?? $this->fetchYahooGold()
            ?? $this->fetchGoldApiGold()
            ?? $this->fetchGoldPriceOrgGold()
            ?? $this->fetchCurrencyApiGold()
            ?? $this->fetchCoinbaseGold();
    }

    private function fetchMetalsLiveGold(): ?float
    {
        try {
            $response = $this->http()->get('https://api.metals.live/v1/spot/gold');

            if (! $response->ok()) {
                return null;
            }

            $rows = $response->json();
            $goldRow = is_array($rows)
                ? collect($rows)->first(fn ($row): bool => is_array($row) && array_key_exists('gold', $row))
                : null;
            $usdPerOunce = is_array($goldRow) ? (float) ($goldRow['gold'] ?? 0) : 0;

            return $usdPerOunce > 0 ? $usdPerOunce : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function fetchStooqGold(): ?float
    {
        try {
            $response = $this->http()->get('https://stooq.com/q/l/?s=xauusd&f=sd2t2ohlcv&h&e=csv');

            if (! $response->ok()) {
                return null;
            }

            $lines = preg_split('/\r\n|\r|\n/', trim($response->body()));

            if (! is_array($lines) || count($lines) < 2) {
                return null;
            }

            $headers = str_getcsv($lines[0]);
            $values = str_getcsv($lines[1]);
            $closeIndex = array_search('Close', $headers, true);
            $price = $closeIndex === false ? 0 : (float) ($values[$closeIndex] ?? 0);

            return $price > 0 ? $price : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function fetchCoinbaseGold(): ?float
    {
        try {
            $response = $this->http()->get('https://api.coinbase.com/v2/exchange-rates', ['currency' => 'XAU']);

            if (! $response->ok()) {
                return null;
            }

            $price = (float) ($response->json('data.rates.USD') ?? 0);

            return $price > 0 ? $price : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function fetchCurrencyApiGold(): ?float
    {
        try {
            $response = $this->http()->get('https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/xau.json');

            if (! $response->ok()) {
                return null;
            }

            $price = (float) ($response->json('xau.usd') ?? 0);

            return $price > 0 ? $price : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function fetchYahooGold(): ?float
    {
        try {
            $response = $this->http()
                ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->get('https://query1.finance.yahoo.com/v8/finance/chart/GC%3DF', [
                    'range' => '1d',
                    'interval' => '1d',
                ]);

            if (! $response->ok()) {
                return null;
            }

            $price = (float) ($response->json('chart.result.0.meta.regularMarketPrice') ?? 0);

            return $price > 0 ? $price : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function fetchGoldApiGold(): ?float
    {
        try {
            $response = $this->http()
                ->withHeaders(['User-Agent' => 'ShopWeb currency snapshot'])
                ->get('https://api.gold-api.com/price/XAU');

            if (! $response->ok()) {
                return null;
            }

            $price = (float) ($response->json('price') ?? 0);

            return $price > 0 ? $price : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function fetchGoldPriceOrgGold(): ?float
    {
        try {
            $response = $this->http()
                ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->get('https://data-asg.goldprice.org/dbXRates/USD');

            if (! $response->ok()) {
                return null;
            }

            $price = (float) ($response->json('items.0.xauPrice') ?? 0);

            return $price > 0 ? $price : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $rates
     * @return array<string, float>
     */
    private function numericRates(array $rates): array
    {
        return collect($rates)
            ->mapWithKeys(fn ($rate, string $code): array => [strtoupper($code) => (float) $rate])
            ->filter(fn (float $rate): bool => $rate > 0)
            ->all();
    }

    /**
     * @param  array<string, float>  $quotes
     * @return array<string, float>
     */
    private function ratesFromBaseQuotes(string $base, array $quotes): array
    {
        return collect(CurrencyUnit::currencyOptions())
            ->keys()
            ->mapWithKeys(function (string $code) use ($base, $quotes): array {
                $quote = (float) ($quotes[$code] ?? ($code === $base ? 1 : 0));

                return [$code => $quote > 0 ? round(1 / $quote, 8) : 0.0];
            })
            ->filter(fn (float $rate): bool => $rate > 0)
            ->put($base, 1.0)
            ->all();
    }

    /**
     * @param  array<string, float>  $rates
     * @return array<string, float>
     */
    private function withFallbackRates(string $base, array $rates): array
    {
        return collect($this->fallbackExchangeRates($base))
            ->merge($rates)
            ->put($base, 1.0)
            ->sortKeys()
            ->all();
    }

    /**
     * @return array<string, float>
     */
    private function fallbackExchangeRates(string $base): array
    {
        $usdRates = $this->usdCurrencyRates();
        $usdPerBase = (float) ($usdRates[$base] ?? $usdRates['CNY']);

        return collect(CurrencyUnit::currencyOptions())
            ->keys()
            ->mapWithKeys(function (string $code) use ($usdRates, $usdPerBase): array {
                $currencyPerUsd = (float) ($usdRates[$code] ?? 0);

                return [$code => $currencyPerUsd > 0 ? round($usdPerBase / $currencyPerUsd, 8) : 0.0];
            })
            ->filter(fn (float $rate): bool => $rate > 0)
            ->all();
    }

    /**
     * Approximate USD quote table used only to fill missing currencies when live providers omit or fail a code.
     *
     * @return array<string, float>
     */
    private function usdCurrencyRates(): array
    {
        $eurPerUsd = 0.92;

        return [
            'CNY' => 7.20,
            'HKD' => 7.80,
            'MOP' => 8.03,
            'TWD' => 32.00,
            'USD' => 1.00,
            'EUR' => $eurPerUsd,
            'GBP' => 0.79,
            'JPY' => 155.00,
            'KRW' => 1370.00,
            'SGD' => 1.35,
            'AUD' => 1.50,
            'NZD' => 1.63,
            'CAD' => 1.36,
            'CHF' => 0.90,
            'SEK' => 10.50,
            'NOK' => 10.70,
            'DKK' => 6.86,
            'RUB' => 90.00,
            'INR' => 83.00,
            'BRL' => 5.15,
            'MXN' => 17.00,
            'THB' => 36.00,
            'MYR' => 4.70,
            'IDR' => 16000.00,
            'PHP' => 56.00,
            'VND' => 25000.00,
            'AED' => 3.67,
            'SAR' => 3.75,
            'TRY' => 32.00,
            'ZAR' => 18.50,
            'FRF' => $eurPerUsd * 6.55957,
            'BEF' => $eurPerUsd * 40.3399,
            'ATS' => $eurPerUsd * 13.7603,
            'DEM' => $eurPerUsd * 1.95583,
            'ITL' => $eurPerUsd * 1936.27,
            'ESP' => $eurPerUsd * 166.386,
            'NLG' => $eurPerUsd * 2.20371,
        ];
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

    private function http(): PendingRequest
    {
        $request = Http::timeout(12);
        $options = $this->httpOptions();

        return $options === [] ? $request : $request->withOptions($options);
    }

    /**
     * @return array<string, mixed>
     */
    private function httpOptions(): array
    {
        if (! (bool) config('services.ai_http.verify_ssl', true)) {
            return ['verify' => false];
        }

        $caBundle = trim((string) config('services.ai_http.ca_bundle', ''));

        if ($caBundle !== '') {
            $path = base_path($caBundle);

            if (! is_file($path)) {
                $path = $caBundle;
            }

            if (is_file($path)) {
                return ['verify' => $path];
            }
        }

        if ((bool) config('services.ai_http.use_native_ca', true) && defined('CURLOPT_SSL_OPTIONS') && defined('CURLSSLOPT_NATIVE_CA')) {
            return [
                'curl' => [
                    CURLOPT_SSL_OPTIONS => CURLSSLOPT_NATIVE_CA,
                ],
            ];
        }

        return [];
    }
}
