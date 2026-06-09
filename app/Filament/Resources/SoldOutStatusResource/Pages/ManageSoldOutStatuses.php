<?php

namespace App\Filament\Resources\SoldOutStatusResource\Pages;

use App\Filament\Resources\SoldOutStatusResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSoldOutStatuses extends ManageRecords
{
    protected static string $resource = SoldOutStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
