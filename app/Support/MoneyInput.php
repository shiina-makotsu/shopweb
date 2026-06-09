<?php

namespace App\Support;

use Filament\Forms\Components\TextInput;

class MoneyInput
{
    public static function cents(TextInput $input, bool $nullable = false): TextInput
    {
        return $input
            ->numeric()
            ->step('0.01')
            ->formatStateUsing(fn ($state): ?string => self::fromCents($state))
            ->dehydrateStateUsing(fn ($state): ?int => self::toCents($state, $nullable));
    }

    public static function fromCents(mixed $state): ?string
    {
        if ($state === null || $state === '') {
            return null;
        }

        return number_format(((int) $state) / 100, 2, '.', '');
    }

    public static function toCents(mixed $state, bool $nullable = false): ?int
    {
        if ($state === null || $state === '') {
            return $nullable ? null : 0;
        }

        return max(0, (int) round(((float) $state) * 100));
    }
}
