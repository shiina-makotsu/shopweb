<?php

namespace App\Filament\Resources\CostEntryResource\Pages;

use App\Filament\Resources\CostEntryResource;
use App\Models\CostEntry;
use App\Support\CurrencyUnit;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\Pages\EditRecord;

class EditCostEntry extends EditRecord
{
    protected static string $resource = CostEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn (CostEntry $record): bool => ! $record->is_auto),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['currency_code'] ??= CurrencyUnit::baseCurrency();
        $data['currency_unit'] ??= CurrencyUnit::defaultUnit($data['currency_code']);
        $data['exchange_rate'] ??= 1;
        $data['application_type'] ??= $this->record?->procurement_id ? CostEntry::APPLICATION_PROCUREMENT : CostEntry::APPLICATION_RECURRING;
        $data['is_effective'] ??= true;
        $data['effective_times'] ??= 1;
        $data['effective_quantity'] ??= 0;

        if (! isset($data['original_amount'])) {
            $data['original_amount'] = ((int) ($data['amount_cents'] ?? 0)) / 100;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['amount_cents'] = CurrencyUnit::toSettlementCents(
            $data['original_amount'] ?? 0,
            $currency = ($data['currency_code'] ?? CurrencyUnit::baseCurrency()),
            $data['currency_unit'] ?? CurrencyUnit::defaultUnit($currency),
            CurrencyUnit::exchangeRateFor($currency),
        );
        $data['exchange_rate'] = CurrencyUnit::exchangeRateFor($currency);
        $data['effective_times'] = max(0, (int) ($data['effective_times'] ?? 1));
        $data['effective_quantity'] = max(0, (int) ($data['effective_quantity'] ?? 0));
        $data['effective_at'] = ($data['is_effective'] ?? false)
            ? ($this->record?->effective_at ?? now())
            : null;

        return $data;
    }
}
