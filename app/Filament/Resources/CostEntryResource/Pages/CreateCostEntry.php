<?php

namespace App\Filament\Resources\CostEntryResource\Pages;

use App\Filament\Resources\CostEntryResource;
use App\Models\CostEntry;
use App\Support\CurrencyUnit;
use App\Filament\Resources\Pages\CreateRecord;

class CreateCostEntry extends CreateRecord
{
    protected static string $resource = CostEntryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_id'] = auth()->id();
        $data['is_auto'] = false;

        if (blank($data['application_type'] ?? null)) {
            $data['application_type'] = filled($data['procurement_id'] ?? null)
                ? CostEntry::APPLICATION_PROCUREMENT
                : CostEntry::APPLICATION_RECURRING;
        }

        if (! array_key_exists('is_effective', $data)) {
            $data['is_effective'] = $data['application_type'] !== CostEntry::APPLICATION_PROCUREMENT
                || filled($data['procurement_id'] ?? null);
        }

        $data['is_effective'] = (bool) $data['is_effective'];
        $data['effective_times'] = max(0, (int) ($data['effective_times'] ?? 1));
        $data['effective_quantity'] = max(0, (int) ($data['effective_quantity'] ?? 0));
        $data['effective_at'] = $data['is_effective'] ? now() : null;
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
