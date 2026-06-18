<?php

namespace App\Filament\Resources\CostEntryResource\Pages;

use App\Filament\Resources\CostEntryResource;
use App\Support\CurrencyUnit;
use App\Filament\Resources\Pages\CreateRecord;

class CreateCostEntry extends CreateRecord
{
    protected static string $resource = CostEntryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_id'] = auth()->id();
        $data['is_auto'] = false;
        $data['amount_cents'] = CurrencyUnit::toSettlementCents(
            $data['original_amount'] ?? 0,
            $currency = ($data['currency_code'] ?? CurrencyUnit::baseCurrency()),
            $data['currency_unit'] ?? CurrencyUnit::defaultUnit($currency),
            CurrencyUnit::exchangeRateFor($currency),
        );
        $data['exchange_rate'] = CurrencyUnit::exchangeRateFor($currency);

        return $data;
    }
}
