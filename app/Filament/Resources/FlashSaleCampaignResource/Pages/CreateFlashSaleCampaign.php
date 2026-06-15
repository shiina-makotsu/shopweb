<?php

namespace App\Filament\Resources\FlashSaleCampaignResource\Pages;

use App\Filament\Resources\FlashSaleCampaignResource;
use App\Services\FlashSaleCampaignService;
use Filament\Resources\Pages\CreateRecord;

class CreateFlashSaleCampaign extends CreateRecord
{
    protected static string $resource = FlashSaleCampaignResource::class;

    protected function afterCreate(): void
    {
        app(FlashSaleCampaignService::class)->syncCampaign($this->record);
    }
}
