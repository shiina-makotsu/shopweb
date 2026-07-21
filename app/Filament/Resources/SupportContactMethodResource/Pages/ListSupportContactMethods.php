<?php

namespace App\Filament\Resources\SupportContactMethodResource\Pages;

use App\Filament\Resources\SupportContactMethodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSupportContactMethods extends ListRecords
{
    protected static string $resource = SupportContactMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
