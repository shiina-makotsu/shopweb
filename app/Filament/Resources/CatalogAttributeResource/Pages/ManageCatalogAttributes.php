<?php

namespace App\Filament\Resources\CatalogAttributeResource\Pages;

use App\Filament\Resources\CatalogAttributeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCatalogAttributes extends ManageRecords
{
    protected static string $resource = CatalogAttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
