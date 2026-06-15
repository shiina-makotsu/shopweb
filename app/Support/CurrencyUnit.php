<?php

namespace App\Support;

class CurrencyUnit
{
    /**
     * @return array<string, string>
     */
    public static function currencyOptions(): array
    {
        return [
            'CNY' => '人民币',
            'USD' => '美元',
            'EUR' => '欧元',
            'FRF' => '法国法郎',
            'BEF' => '比利时法郎',
            'ATS' => '奥地利先令',
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function unitsByCurrency(): array
    {
        return [
            'CNY' => [
                'yuan' => '元',
                'jiao' => '角',
                'fen' => '分',
            ],
            'USD' => [
                'dollar' => '美元',
                'cent' => '美分',
            ],
            'EUR' => [
                'euro' => '欧元',
                'cent' => '欧分',
            ],
            'FRF' => [
                'franc' => '法郎',
                'centime' => '生丁',
            ],
            'BEF' => [
                'franc' => '比利时法郎',
                'centime' => '生丁',
            ],
            'ATS' => [
                'schilling' => '先令',
                'groschen' => '格罗申',
            ],
        ];
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

    public static function unitFactor(?string $currency, ?string $unit): float
    {
        $currency = $currency ?: 'CNY';
        $unit = $unit ?: self::defaultUnit($currency);

        return match ($currency) {
            'CNY' => match ($unit) {
                'fen' => 0.01,
                'jiao' => 0.1,
                default => 1.0,
            },
            'USD', 'EUR' => $unit === 'cent' ? 0.01 : 1.0,
            'FRF', 'BEF' => $unit === 'centime' ? 0.01 : 1.0,
            'ATS' => $unit === 'groschen' ? 0.01 : 1.0,
            default => 1.0,
        };
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

    public static function formatOriginal(mixed $amount, ?string $currency, ?string $unit): string
    {
        if ($amount === null || $amount === '') {
            return '-';
        }

        $currencyLabel = self::currencyOptions()[$currency ?: 'CNY'] ?? ($currency ?: '人民币');
        $unitLabel = self::unitOptions($currency)[$unit ?: self::defaultUnit($currency)] ?? ($unit ?: '');

        return rtrim(rtrim(number_format((float) $amount, 4, '.', ''), '0'), '.').' '.$currencyLabel.' '.$unitLabel;
    }
}
