<?php

namespace App\Filament\Resources\ResourceAssetResource\Pages;

use App\Filament\Resources\ResourceAssetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListResourceAssets extends ListRecords
{
    protected static string $resource = ResourceAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('上传资源文件'),
        ];
    }
}
