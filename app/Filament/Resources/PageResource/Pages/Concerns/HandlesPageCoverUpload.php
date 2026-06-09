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
        unset($data['cover_upload']);

        if (blank($path)) {
            return $data;
        }

        $path = is_array($path) ? reset($path) : $path;

        if (! is_string($path) || blank($path)) {
            return $data;
        }

        $asset = MediaAsset::query()->create([
            'name' => pathinfo($path, PATHINFO_FILENAME),
            'path' => $path,
            'disk' => 'public_uploads',
            'usage' => MediaAsset::USAGE_PAGE,
        ]);

        $data['cover_media_asset_id'] = $asset->id;

        return $data;
    }
}
