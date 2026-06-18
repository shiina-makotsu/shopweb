<?php

namespace App\Filament\Resources\WarehouseStockResource\Pages;

use App\Filament\Resources\WarehouseStockResource;
use App\Filament\Resources\Pages\CreateRecord;

class CreateWarehouseStock extends CreateRecord
{
    protected static string $resource = WarehouseStockResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return WarehouseStockResource::normalizeFormData($data);
    }
}
