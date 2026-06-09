<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NavigationMenuItem extends Model
{
    protected $fillable = [
        'parent_id',
        'label',
        'url',
        'route_name',
        'route_parameters',
        'sort_order',
        'is_active',
        'opens_new_tab',
    ];

    protected function casts(): array
    {
        return [
            'route_parameters' => 'array',
            'is_active' => 'boolean',
            'opens_new_tab' => 'boolean',
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function resolvedUrl(): string
    {
        if ($this->route_name && \Route::has($this->route_name)) {
            return route($this->route_name, $this->route_parameters ?? []);
        }

        return $this->url ?: '#';
    }
}
