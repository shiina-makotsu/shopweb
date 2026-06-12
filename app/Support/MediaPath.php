<?php

namespace App\Support;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;

class MediaPath
{
    public static function url(?string $path, string $disk = 'public_uploads'): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (MediaAsset::isExternalUrl($path) || str_starts_with($path, '/')) {
            return $path;
        }

        if (str_starts_with($path, 'uploads/')) {
            return '/'.$path;
        }

        return Storage::disk($disk)->url($path);
    }
}
