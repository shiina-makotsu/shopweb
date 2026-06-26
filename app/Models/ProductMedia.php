<?php

namespace App\Models;

use App\Support\MediaPath;
use App\Services\StorefrontCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMedia extends Model
{
    use HasFactory;

    public const TYPE_CONCEPT = 'concept';
    public const TYPE_PREVIEW = 'preview';
    public const KIND_IMAGE = 'image';
    public const KIND_VIDEO = 'video';

    protected $fillable = [
        'product_id',
        'type',
        'media_kind',
        'path',
        'mime_type',
        'alt',
        'sort_order',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function url(): string
    {
        return MediaPath::url($this->path) ?? '';
    }

    public function isVideo(): bool
    {
        return $this->media_kind === self::KIND_VIDEO || str_starts_with((string) $this->mime_type, 'video/');
    }

    protected static function booted(): void
    {
        static::saving(function (ProductMedia $media): void {
            $media->media_kind = $media->media_kind ?: (str_starts_with((string) $media->mime_type, 'video/') ? self::KIND_VIDEO : self::KIND_IMAGE);
        });

        static::saved(function (): void {
            app(StorefrontCache::class)->clear();
        });
        static::deleted(function (): void {
            app(StorefrontCache::class)->clear();
        });
    }
}
