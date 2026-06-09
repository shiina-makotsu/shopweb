<?php

namespace App\Filament\Resources\WarehouseStockResource\Pages;

use App\Filament\Resources\WarehouseStockResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWarehouseStocks extends ListRecords
{
    protected static string $resource = WarehouseStockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('新增仓库条目'),
        ];
    }
}
