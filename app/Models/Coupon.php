<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\Money;

class Coupon extends Model
{
    use HasFactory;

    public const TYPE_FIXED = 'fixed';
    public const TYPE_PERCENT = 'percent';
    public const SCOPE_GLOBAL = 'global';
    public const SCOPE_PRODUCT = 'product';

    protected $fillable = [
        'code',
        'name',
        'type',
        'value',
        'scope',
        'product_id',
        'minimum_order_cents',
        'usage_limit',
        'per_user_limit',
        'is_stackable',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'is_stackable' => 'boolean',
        ];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withTimestamps();
    }

    public function userCoupons(): HasMany
    {
        return $this->hasMany(UserCoupon::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function discountFor(int $subtotalCents): int
    {
        if ($this->type === self::TYPE_PERCENT) {
            return min($subtotalCents, (int) floor($subtotalCents * $this->value / 100));
        }

        return min($subtotalCents, $this->value);
    }

    public function appliesToProduct(int $productId): bool
    {
        if (($this->scope ?? self::SCOPE_GLOBAL) === self::SCOPE_GLOBAL) {
            return true;
        }

        if ((int) $this->product_id === $productId) {
            return true;
        }

        if ($this->relationLoaded('products')) {
            return $this->products->contains('id', $productId);
        }

        return $this->products()->whereKey($productId)->exists();
    }

    public function scopeLabel(): string
    {
        if (($this->scope ?? self::SCOPE_GLOBAL) === self::SCOPE_GLOBAL) {
            return '全场可用';
        }

        $products = $this->relationLoaded('products') ? $this->products : $this->products()->limit(4)->get();

        if ($products->isEmpty() && $this->product) {
            return $this->product->title;
        }

        return $products->pluck('title')->implode('、') ?: '未绑定商品';
    }

    public function discountLabel(): string
    {
        if ($this->type === self::TYPE_PERCENT) {
            return ((int) $this->value).'%';
        }

        return '减 '.Money::format((int) $this->value);
    }

    public static function scopeOptions(): array
    {
        return [
            self::SCOPE_GLOBAL => '全场优惠码',
            self::SCOPE_PRODUCT => '单商品优惠码',
        ];
    }
}
