<?php

namespace App\Filament\Resources\ResourceAssetResource\Pages;

use App\Filament\Resources\ResourceAssetResource;
use App\Models\MediaAsset;
use Filament\Resources\Pages\CreateRecord;

class CreateResourceAsset extends CreateRecord
{
    protected static string $resource = ResourceAssetResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['usage'] = $data['usage'] ?? MediaAsset::USAGE_RESOURCE;

        if (($data['usage'] ?? MediaAsset::USAGE_GENERAL) === MediaAsset::USAGE_GENERAL) {
            $data['usage'] = MediaAsset::USAGE_RESOURCE;
        }

        return $data;
    }
}
