<?php

namespace App\Filament\Resources\OrderStatusSettingResource\Pages;

use App\Filament\Resources\OrderStatusSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOrderStatusSettings extends ManageRecords
{
    protected static string $resource = OrderStatusSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
