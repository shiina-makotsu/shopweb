<?php

namespace App\Filament\Resources\PageResource\Pages\Concerns;

use App\Models\MediaAsset;

trait HandlesPageCoverUpload
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function attachUploadedCover(array $data): array
    {
        $path = $data['cover_upload'] ?? null;
        $externalUrl = $data['cover_external_url'] ?? null;
        unset($data['cover_upload']);
        unset($data['cover_external_url']);

        if (blank($path) && blank($externalUrl)) {
            return $data;
        }

        $asset = MediaAsset::createImageFromUploadOrUrl([
            'path' => $path,
            'external_url' => $externalUrl,
        ], MediaAsset::USAGE_PAGE);

        $data['cover_media_asset_id'] = $asset->id;

        return $data;
    }
}
