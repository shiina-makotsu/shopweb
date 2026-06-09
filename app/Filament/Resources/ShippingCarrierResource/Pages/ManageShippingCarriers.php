<?php

namespace App\Filament\Resources\ShippingCarrierResource\Pages;

use App\Filament\Resources\ShippingCarrierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageShippingCarriers extends ManageRecords
{
    protected static string $resource = ShippingCarrierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('新增物流承运商'),
        ];
    }
}
