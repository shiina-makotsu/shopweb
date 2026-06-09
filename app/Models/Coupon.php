<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public static function scopeOptions(): array
    {
        return [
            self::SCOPE_GLOBAL => '全场优惠码',
            self::SCOPE_PRODUCT => '单商品优惠码',
        ];
    }
}
