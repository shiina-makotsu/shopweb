<?php

namespace App\Filament\Resources\FlashSaleCampaignResource\Pages;

use App\Filament\Resources\FlashSaleCampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFlashSaleCampaigns extends ListRecords
{
    protected static string $resource = FlashSaleCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('创建秒杀计划'),
        ];
    }
}
