<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class FriendLink extends Model
{
    protected $fillable = [
        'site_name',
        'url',
        'image_path',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        if (MediaAsset::isExternalUrl($this->image_path)) {
            return $this->image_path;
        }

        return Storage::disk('public_uploads')->url($this->image_path);
    }
}
