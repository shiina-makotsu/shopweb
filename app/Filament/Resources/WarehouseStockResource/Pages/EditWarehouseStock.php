<?php

namespace App\Filament\Resources\WarehouseStockResource\Pages;

use App\Filament\Resources\WarehouseStockResource;
use App\Filament\Resources\Pages\EditRecord;

class EditWarehouseStock extends EditRecord
{
    protected static string $resource = WarehouseStockResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return WarehouseStockResource::normalizeFormData($data);
    }
}
