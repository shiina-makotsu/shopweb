<?php

namespace App\Filament\Resources\CostEntryResource\Pages;

use App\Filament\Resources\CostEntryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCostEntry extends CreateRecord
{
    protected static string $resource = CostEntryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_id'] = auth()->id();
        $data['is_auto'] = false;

        return $data;
    }
}
