<?php

namespace App\Models;

use App\Support\Url;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NavigationMenuItem extends Model
{
    public const PLACEMENT_TOP_NAV = 'top_nav';
    public const PLACEMENT_HOME_INFO = 'home_info';

    protected $fillable = [
        'parent_id',
        'placement',
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

    public function scopePlacement(Builder $query, string $placement): Builder
    {
        return $query->where('placement', $placement);
    }

    public function resolvedUrl(): string
    {
        if ($this->route_name && \Route::has($this->route_name)) {
            return Url::route($this->route_name, $this->route_parameters ?? []);
        }

        return $this->url ?: '#';
    }

    /**
     * @return array<string, string>
     */
    public static function placementOptions(): array
    {
        return [
            self::PLACEMENT_TOP_NAV => '顶部导航',
            self::PLACEMENT_HOME_INFO => '首页商店信息',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function routeOptions(): array
    {
        return [
            'home' => '首页',
            'tags.index' => '标签',
            'products.index' => '全部商品',
            'ai-image.index' => 'AI',
            'friend-links.index' => '友情链接',
            'forum.index' => '论坛',
            'shipments.show' => '物流查询',
            'support.index' => '客服会话',
            'support.demands' => '客服工单',
            'orders.index' => '订单查询',
            'pages.show' => '自定义页面',
        ];
    }
}
