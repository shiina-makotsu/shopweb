<?php

namespace App\Support;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

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

    /**
     * @return array<int, Select|TextInput>
     */
    public static function conversionControls(
        string $name,
        ?string $currencyField = null,
        ?string $unitField = null,
        ?string $rateField = null,
        bool $dehydrated = false,
        ?string $defaultCurrencyField = null,
        ?string $defaultUnitField = null,
    ): array
    {
        $currencyField ??= $name.'_currency_code';
        $unitField ??= $name.'_currency_unit';

        return [
            Select::make($currencyField)
                ->label('货币')
                ->options(CurrencyUnit::currencyOptions())
                ->default(fn (Get $get): string => self::currencyDefault($get, $defaultCurrencyField))
                ->searchable()
                ->dehydrated($dehydrated)
                ->live()
                ->afterStateUpdated(function (Set $set, ?string $state) use ($unitField): void {
                    $set($unitField, CurrencyUnit::defaultUnit($state));
                }),
            Select::make($unitField)
                ->label('金额单位')
                ->options(fn (Get $get): array => CurrencyUnit::unitOptions($get($currencyField) ?: self::currencyDefault($get, $defaultCurrencyField)))
                ->default(fn (Get $get): string => self::unitDefault($get, $defaultCurrencyField, $defaultUnitField))
                ->dehydrated($dehydrated),
        ];
    }

    public static function currencyAmountSection(TextInput $input, bool $nullable = false, ?string $controlName = null, ?string $label = null): Section
    {
        return Section::make($label ?: $input->getLabel() ?: '金额')
            ->schema(self::convertedCents($input, $nullable, $controlName))
            ->columns(3)
            ->columnSpanFull();
    }

    /**
     * @return array<int, Select|TextInput>
     */
    public static function convertedCents(
        TextInput $input,
        bool $nullable = false,
        ?string $controlName = null,
        ?string $defaultCurrencyField = null,
        ?string $defaultUnitField = null,
        bool $includeControls = true,
    ): array
    {
        $name = $controlName ?: $input->getName();
        $currencyField = $name.'_currency_code';
        $unitField = $name.'_currency_unit';
        $controls = $includeControls
            ? self::conversionControls(
                $name,
                defaultCurrencyField: $defaultCurrencyField,
                defaultUnitField: $defaultUnitField,
            )
            : [];

        return [
            ...$controls,
            $input
                ->numeric()
                ->step('0.0001')
                ->formatStateUsing(fn ($state): ?string => CurrencyUnit::fromSettlementCents($state, CurrencyUnit::baseCurrency(), CurrencyUnit::baseUnit(), 1))
                ->dehydrateStateUsing(function ($state, Get $get) use ($nullable, $currencyField, $unitField, $defaultCurrencyField, $defaultUnitField): ?int {
                    if ($state === null || $state === '') {
                        return $nullable ? null : 0;
                    }

                    $currency = CurrencyUnit::normalizeCurrency($get($currencyField) ?: self::currencyDefault($get, $defaultCurrencyField));

                    return CurrencyUnit::toSettlementCents(
                        $state,
                        $currency,
                        $get($unitField) ?: self::unitDefault($get, $defaultCurrencyField, $defaultUnitField),
                        CurrencyUnit::exchangeRateFor($currency),
                    );
                }),
        ];
    }

    private static function currencyDefault(Get $get, ?string $defaultCurrencyField): string
    {
        return CurrencyUnit::normalizeCurrency(
            $defaultCurrencyField
                ? ($get($defaultCurrencyField) ?: CurrencyUnit::baseCurrency())
                : CurrencyUnit::baseCurrency()
        );
    }

    private static function unitDefault(Get $get, ?string $defaultCurrencyField, ?string $defaultUnitField): string
    {
        $currency = self::currencyDefault($get, $defaultCurrencyField);
        $unit = $defaultUnitField ? (string) ($get($defaultUnitField) ?: '') : '';

        return array_key_exists($unit, CurrencyUnit::unitOptions($currency))
            ? $unit
            : CurrencyUnit::defaultUnit($currency);
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
