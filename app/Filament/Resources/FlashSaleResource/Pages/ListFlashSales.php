<?php

namespace App\Filament\Resources\FlashSaleResource\Pages;

use App\Filament\Resources\FlashSaleResource;
use App\Filament\Support\AdminPageTabs;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFlashSales extends ListRecords
{
    protected static string $resource = FlashSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...AdminPageTabs::actions(FlashSaleResource::tabs(), 'sales'),
            CreateAction::make()->label('创建秒杀'),
        ];
    }
}
