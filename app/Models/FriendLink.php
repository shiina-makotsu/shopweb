<?php

namespace App\Models;

use App\Support\MediaPath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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

        return MediaPath::url($this->image_path);
    }
}
