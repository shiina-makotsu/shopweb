<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'body',
        'is_published',
        'is_pinned',
        'comments_enabled',
        'popup_when_unread',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_pinned' => 'boolean',
            'comments_enabled' => 'boolean',
            'popup_when_unread' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(AnnouncementComment::class);
    }

    public function topLevelComments(): HasMany
    {
        return $this->hasMany(AnnouncementComment::class)
            ->whereNull('parent_id')
            ->whereNull('deleted_at')
            ->latest();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function normalizePublicationData(array $data): array
    {
        if (($data['is_published'] ?? false) && blank($data['published_at'] ?? null)) {
            $data['published_at'] = now();
        }

        return $data;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
