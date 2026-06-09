<?php

namespace App\Filament\Resources\NavigationMenuItemResource\Pages;

use App\Filament\Resources\NavigationMenuItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNavigationMenuItems extends ListRecords
{
    protected static string $resource = NavigationMenuItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('新增菜单项'),
        ];
    }
}
