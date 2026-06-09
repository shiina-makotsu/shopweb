<?php

namespace App\Filament\Resources\QuantityUnitResource\Pages;

use App\Filament\Resources\QuantityUnitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageQuantityUnits extends ManageRecords
{
    protected static string $resource = QuantityUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
