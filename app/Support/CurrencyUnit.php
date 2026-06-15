<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Schema;

class CurrencyUnit
{
    /**
     * @return array<string, array{name:string,units:array<string, array{label:string,factor:float}>}>
     */
    public static function currencies(): array
    {
        return [
            'CNY' => ['name' => '人民币', 'units' => ['yuan' => ['label' => '元', 'factor' => 1.0], 'jiao' => ['label' => '角', 'factor' => 0.1], 'fen' => ['label' => '分', 'factor' => 0.01]]],
            'HKD' => ['name' => '港币', 'units' => ['dollar' => ['label' => '港元', 'factor' => 1.0], 'cent' => ['label' => '仙', 'factor' => 0.01]]],
            'MOP' => ['name' => '澳门元', 'units' => ['pataca' => ['label' => '澳门元', 'factor' => 1.0], 'avo' => ['label' => '仙', 'factor' => 0.01]]],
            'TWD' => ['name' => '新台币', 'units' => ['dollar' => ['label' => '新台币元', 'factor' => 1.0], 'cent' => ['label' => '分', 'factor' => 0.01]]],
            'USD' => ['name' => '美元', 'units' => ['dollar' => ['label' => '美元', 'factor' => 1.0], 'cent' => ['label' => '美分', 'factor' => 0.01]]],
            'EUR' => ['name' => '欧元', 'units' => ['euro' => ['label' => '欧元', 'factor' => 1.0], 'cent' => ['label' => '欧分', 'factor' => 0.01]]],
            'GBP' => ['name' => '英镑', 'units' => ['pound' => ['label' => '英镑', 'factor' => 1.0], 'penny' => ['label' => '便士', 'factor' => 0.01]]],
            'JPY' => ['name' => '日元', 'units' => ['yen' => ['label' => '日元', 'factor' => 1.0]]],
            'KRW' => ['name' => '韩元', 'units' => ['won' => ['label' => '韩元', 'factor' => 1.0]]],
            'SGD' => ['name' => '新加坡元', 'units' => ['dollar' => ['label' => '新加坡元', 'factor' => 1.0], 'cent' => ['label' => '分', 'factor' => 0.01]]],
            'AUD' => ['name' => '澳大利亚元', 'units' => ['dollar' => ['label' => '澳元', 'factor' => 1.0], 'cent' => ['label' => '分', 'factor' => 0.01]]],
            'NZD' => ['name' => '新西兰元', 'units' => ['dollar' => ['label' => '新西兰元', 'factor' => 1.0], 'cent' => ['label' => '分', 'factor' => 0.01]]],
            'CAD' => ['name' => '加拿大元', 'units' => ['dollar' => ['label' => '加元', 'factor' => 1.0], 'cent' => ['label' => '分', 'factor' => 0.01]]],
            'CHF' => ['name' => '瑞士法郎', 'units' => ['franc' => ['label' => '瑞士法郎', 'factor' => 1.0], 'rappen' => ['label' => '拉彭', 'factor' => 0.01]]],
            'SEK' => ['name' => '瑞典克朗', 'units' => ['krona' => ['label' => '克朗', 'factor' => 1.0], 'ore' => ['label' => '欧尔', 'factor' => 0.01]]],
            'NOK' => ['name' => '挪威克朗', 'units' => ['krone' => ['label' => '克朗', 'factor' => 1.0], 'ore' => ['label' => '欧尔', 'factor' => 0.01]]],
            'DKK' => ['name' => '丹麦克朗', 'units' => ['krone' => ['label' => '克朗', 'factor' => 1.0], 'ore' => ['label' => '欧尔', 'factor' => 0.01]]],
            'RUB' => ['name' => '俄罗斯卢布', 'units' => ['ruble' => ['label' => '卢布', 'factor' => 1.0], 'kopeck' => ['label' => '戈比', 'factor' => 0.01]]],
            'INR' => ['name' => '印度卢比', 'units' => ['rupee' => ['label' => '卢比', 'factor' => 1.0], 'paise' => ['label' => '派士', 'factor' => 0.01]]],
            'BRL' => ['name' => '巴西雷亚尔', 'units' => ['real' => ['label' => '雷亚尔', 'factor' => 1.0], 'centavo' => ['label' => '分', 'factor' => 0.01]]],
            'MXN' => ['name' => '墨西哥比索', 'units' => ['peso' => ['label' => '比索', 'factor' => 1.0], 'centavo' => ['label' => '分', 'factor' => 0.01]]],
            'THB' => ['name' => '泰铢', 'units' => ['baht' => ['label' => '泰铢', 'factor' => 1.0], 'satang' => ['label' => '萨当', 'factor' => 0.01]]],
            'MYR' => ['name' => '马来西亚林吉特', 'units' => ['ringgit' => ['label' => '林吉特', 'factor' => 1.0], 'sen' => ['label' => '仙', 'factor' => 0.01]]],
            'IDR' => ['name' => '印尼盾', 'units' => ['rupiah' => ['label' => '盾', 'factor' => 1.0], 'sen' => ['label' => '仙', 'factor' => 0.01]]],
            'PHP' => ['name' => '菲律宾比索', 'units' => ['peso' => ['label' => '比索', 'factor' => 1.0], 'sentimo' => ['label' => '分', 'factor' => 0.01]]],
            'VND' => ['name' => '越南盾', 'units' => ['dong' => ['label' => '盾', 'factor' => 1.0]]],
            'AED' => ['name' => '阿联酋迪拉姆', 'units' => ['dirham' => ['label' => '迪拉姆', 'factor' => 1.0], 'fils' => ['label' => '费尔', 'factor' => 0.01]]],
            'SAR' => ['name' => '沙特里亚尔', 'units' => ['riyal' => ['label' => '里亚尔', 'factor' => 1.0], 'halala' => ['label' => '哈拉拉', 'factor' => 0.01]]],
            'TRY' => ['name' => '土耳其里拉', 'units' => ['lira' => ['label' => '里拉', 'factor' => 1.0], 'kurus' => ['label' => '库鲁什', 'factor' => 0.01]]],
            'ZAR' => ['name' => '南非兰特', 'units' => ['rand' => ['label' => '兰特', 'factor' => 1.0], 'cent' => ['label' => '分', 'factor' => 0.01]]],
            'FRF' => ['name' => '法国法郎', 'units' => ['franc' => ['label' => '法郎', 'factor' => 1.0], 'centime' => ['label' => '生丁', 'factor' => 0.01]]],
            'BEF' => ['name' => '比利时法郎', 'units' => ['franc' => ['label' => '比利时法郎', 'factor' => 1.0], 'centime' => ['label' => '生丁', 'factor' => 0.01]]],
            'ATS' => ['name' => '奥地利先令', 'units' => ['schilling' => ['label' => '先令', 'factor' => 1.0], 'groschen' => ['label' => '格罗申', 'factor' => 0.01]]],
            'DEM' => ['name' => '德国马克', 'units' => ['mark' => ['label' => '马克', 'factor' => 1.0], 'pfennig' => ['label' => '芬尼', 'factor' => 0.01]]],
            'ITL' => ['name' => '意大利里拉', 'units' => ['lira' => ['label' => '里拉', 'factor' => 1.0]]],
            'ESP' => ['name' => '西班牙比塞塔', 'units' => ['peseta' => ['label' => '比塞塔', 'factor' => 1.0], 'centimo' => ['label' => '分', 'factor' => 0.01]]],
            'NLG' => ['name' => '荷兰盾', 'units' => ['guilder' => ['label' => '盾', 'factor' => 1.0], 'cent' => ['label' => '分', 'factor' => 0.01]]],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function currencyOptions(): array
    {
        return collect(self::currencies())
            ->mapWithKeys(fn (array $currency, string $code): array => [$code => $currency['name'].' '.$code])
            ->all();
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function unitsByCurrency(): array
    {
        return collect(self::currencies())
            ->map(fn (array $currency): array => collect($currency['units'])->mapWithKeys(fn (array $unit, string $key): array => [$key => $unit['label']])->all())
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function unitOptions(?string $currency): array
    {
        return self::unitsByCurrency()[$currency ?: 'CNY'] ?? self::unitsByCurrency()['CNY'];
    }

    public static function defaultUnit(?string $currency): string
    {
        return array_key_first(self::unitOptions($currency)) ?: 'yuan';
    }

    public static function baseCurrency(): string
    {
        $settings = self::settings();

        if (self::hasSettingColumn('currency_base_locked') && $settings?->currency_base_locked) {
            return self::normalizeCurrency($settings->store_currency ?: 'CNY');
        }

        return self::currencyForLocale(self::defaultLocale($settings));
    }

    public static function baseUnit(): string
    {
        $currency = self::baseCurrency();
        $settings = self::settings();
        $unit = self::hasSettingColumn('currency_base_locked') && self::hasSettingColumn('currency_base_unit') && $settings?->currency_base_locked
            ? (string) ($settings->currency_base_unit ?: self::defaultUnit($currency))
            : self::defaultUnit($currency);

        return array_key_exists($unit, self::unitOptions($currency)) ? $unit : self::defaultUnit($currency);
    }

    public static function exchangeRateFor(?string $currency): float
    {
        $currency = self::normalizeCurrency($currency ?: self::baseCurrency());

        if ($currency === self::baseCurrency()) {
            return 1.0;
        }

        if (! self::hasSettingColumn('currency_exchange_rates')) {
            return 1.0;
        }

        $rates = SiteSetting::query()->value('currency_exchange_rates');

        if (is_string($rates)) {
            $rates = json_decode($rates, true);
        }

        if (! is_array($rates)) {
            return 1.0;
        }

        return max(0.0, (float) ($rates[$currency] ?? 1));
    }

    public static function unitFactor(?string $currency, ?string $unit): float
    {
        $currency = $currency ?: 'CNY';
        $unit = $unit ?: self::defaultUnit($currency);

        return self::currencies()[$currency]['units'][$unit]['factor'] ?? 1.0;
    }

    public static function toSettlementCents(mixed $amount, ?string $currency, ?string $unit, mixed $exchangeRate = 1): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        $rate = max(0.0, (float) ($exchangeRate ?: 1));
        $baseAmount = (float) $amount * self::unitFactor($currency, $unit);

        return max(0, (int) round($baseAmount * $rate * 100));
    }

    public static function fromSettlementCents(mixed $cents, ?string $currency, ?string $unit, mixed $exchangeRate = 1): ?string
    {
        if ($cents === null || $cents === '') {
            return null;
        }

        $rate = max(0.000001, (float) ($exchangeRate ?: 1));
        $factor = max(0.000001, self::unitFactor($currency, $unit));
        $amount = ((int) $cents) / 100 / $rate / $factor;

        return number_format($amount, 4, '.', '');
    }

    public static function formatOriginal(mixed $amount, ?string $currency, ?string $unit): string
    {
        if ($amount === null || $amount === '') {
            return '-';
        }

        $currencyLabel = self::currencyOptions()[$currency ?: 'CNY'] ?? ($currency ?: '人民币');
        $unitLabel = self::unitOptions($currency)[$unit ?: self::defaultUnit($currency)] ?? ($unit ?: '');

        return rtrim(rtrim(number_format((float) $amount, 4, '.', ''), '0'), '.').' '.$currencyLabel.' '.$unitLabel;
    }

    public static function normalizeCurrency(?string $currency): string
    {
        $currency = strtoupper(trim((string) $currency));

        return array_key_exists($currency, self::currencies()) ? $currency : 'CNY';
    }

    public static function currencyForLocale(?string $locale): string
    {
        $locale = str_replace('-', '_', strtolower((string) $locale));

        return match (true) {
            str_starts_with($locale, 'zh_hk'), str_starts_with($locale, 'zh_mo') => 'HKD',
            str_starts_with($locale, 'zh_tw') => 'TWD',
            str_starts_with($locale, 'zh') => 'CNY',
            str_starts_with($locale, 'en_us') => 'USD',
            str_starts_with($locale, 'en_gb') => 'GBP',
            str_starts_with($locale, 'en_au') => 'AUD',
            str_starts_with($locale, 'en_ca') => 'CAD',
            str_starts_with($locale, 'en_nz') => 'NZD',
            str_starts_with($locale, 'en_sg') => 'SGD',
            str_starts_with($locale, 'ja') => 'JPY',
            str_starts_with($locale, 'ko') => 'KRW',
            str_starts_with($locale, 'fr_ch'), str_starts_with($locale, 'de_ch'), str_starts_with($locale, 'it_ch') => 'CHF',
            str_starts_with($locale, 'pt_br') => 'BRL',
            str_starts_with($locale, 'es_mx') => 'MXN',
            str_starts_with($locale, 'fr') || str_starts_with($locale, 'de') || str_starts_with($locale, 'it') || str_starts_with($locale, 'es') || str_starts_with($locale, 'nl') || str_starts_with($locale, 'pt') => 'EUR',
            str_starts_with($locale, 'ru') => 'RUB',
            str_starts_with($locale, 'hi') => 'INR',
            str_starts_with($locale, 'th') => 'THB',
            str_starts_with($locale, 'ms') => 'MYR',
            str_starts_with($locale, 'id') => 'IDR',
            str_starts_with($locale, 'vi') => 'VND',
            str_starts_with($locale, 'tr') => 'TRY',
            default => 'USD',
        };
    }

    private static function defaultLocale(?SiteSetting $settings): string
    {
        if (! self::hasSettingColumn('default_locale_mode')) {
            return (string) config('app.locale', 'zh_CN');
        }

        $mode = (string) ($settings?->default_locale_mode ?: 'system');

        if ($mode !== 'system') {
            return $mode;
        }

        $enabled = self::hasSettingColumn('enabled_locales') ? $settings?->enabled_locales : null;

        if (is_array($enabled) && count($enabled) > 0) {
            return (string) $enabled[0];
        }

        return (string) config('app.locale', 'zh_CN');
    }

    private static function settings(): ?SiteSetting
    {
        if (! self::hasSettingColumn('store_currency')) {
            return null;
        }

        return SiteSetting::query()->first();
    }

    private static function hasSettingColumn(string $column): bool
    {
        try {
            return Schema::hasTable('site_settings') && Schema::hasColumn('site_settings', $column);
        } catch (\Throwable) {
            return false;
        }
    }
}
