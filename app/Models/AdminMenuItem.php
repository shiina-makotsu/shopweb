<?php

namespace App\Models;

use App\Support\AdminMenuRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminMenuItem extends Model
{
    public const TYPE_GROUP = 'group';
    public const TYPE_ITEM = 'item';

    protected $fillable = [
        'parent_id',
        'item_key',
        'type',
        'label',
        'source_class',
        'url',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('label');
    }

    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    protected static function booted(): void
    {
        static::saved(fn (): mixed => app(AdminMenuRegistry::class)->clearCache());
        static::deleted(fn (): mixed => app(AdminMenuRegistry::class)->clearCache());
    }
}
