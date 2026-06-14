<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'template',
        'cover_media_asset_id',
        'editor_mode',
        'body',
        'blocks',
        'excerpt',
        'seo_title',
        'seo_description',
        'is_published',
        'sort_order',
        'views_count',
        'comments_enabled',
        'reward_qr_path',
    ];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'is_published' => 'boolean',
            'comments_enabled' => 'boolean',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeMenuable(Builder $query): Builder
    {
        return $query
            ->where('slug', '!=', '404')
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('template')
                ->orWhere('template', '!=', 'not_found'));
    }

    public function coverMediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_asset_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PageComment::class);
    }

    public function topLevelComments(): HasMany
    {
        return $this->comments()->whereNull('parent_id')->visible()->latest();
    }

    public function isArticle(): bool
    {
        return $this->template === \App\Support\PageTemplate::ARTICLE;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
