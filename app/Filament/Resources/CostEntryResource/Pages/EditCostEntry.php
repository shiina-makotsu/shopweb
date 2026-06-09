<?php

namespace App\Filament\Resources\CostEntryResource\Pages;

use App\Filament\Resources\CostEntryResource;
use App\Models\CostEntry;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCostEntry extends EditRecord
{
    protected static string $resource = CostEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn (CostEntry $record): bool => ! $record->is_auto),
        ];
    }
}
