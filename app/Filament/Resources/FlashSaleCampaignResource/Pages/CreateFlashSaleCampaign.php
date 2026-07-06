<?php

namespace App\Filament\Resources\FlashSaleCampaignResource\Pages;

use App\Filament\Resources\FlashSaleResource;
use App\Filament\Resources\FlashSaleCampaignResource;
use App\Filament\Support\AdminPageTabs;
use App\Filament\Resources\Pages\CreateRecord;
use App\Services\FlashSaleCampaignService;

class CreateFlashSaleCampaign extends CreateRecord
{
    protected static string $resource = FlashSaleCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return AdminPageTabs::actions(FlashSaleResource::tabs(), 'campaigns');
    }

    protected function afterCreate(): void
    {
        app(FlashSaleCampaignService::class)->syncCampaign($this->record);
    }
}
