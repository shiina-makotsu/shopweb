<?php

namespace App\Filament\Resources\FlashSaleCampaignResource\Pages;

use App\Filament\Resources\FlashSaleCampaignResource;
use App\Services\FlashSaleCampaignService;
use App\Filament\Resources\Pages\EditRecord;

class EditFlashSaleCampaign extends EditRecord
{
    protected static string $resource = FlashSaleCampaignResource::class;

    protected function afterSave(): void
    {
        app(FlashSaleCampaignService::class)->syncCampaign($this->record);
    }
}
