<?php

namespace App\Filament\Resources\CostEntryResource\Pages;

use App\Filament\Resources\CostEntryResource;
use App\Support\CurrencyUnit;
use Filament\Resources\Pages\CreateRecord;

class CreateCostEntry extends CreateRecord
{
    protected static string $resource = CostEntryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_id'] = auth()->id();
        $data['is_auto'] = false;
        $data['amount_cents'] = CurrencyUnit::toSettlementCents(
            $data['original_amount'] ?? 0,
            $data['currency_code'] ?? 'CNY',
            $data['currency_unit'] ?? 'yuan',
            $data['exchange_rate'] ?? 1,
        );

        return $data;
    }
}
