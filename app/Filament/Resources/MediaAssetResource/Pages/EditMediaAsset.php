<?php

namespace App\Filament\Resources\MediaAssetResource\Pages;

use App\Filament\Resources\MediaAssetResource;
use Filament\Resources\Pages\EditRecord;

class EditMediaAsset extends EditRecord
{
    protected static string $resource = MediaAssetResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return MediaAssetResource::prepareAssetFormData($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return MediaAssetResource::normalizeAssetFormData($data);
    }
}
