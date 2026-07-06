<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WalletRechargeOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'currency_code',
        'currency_unit',
        'amount_cents',
        'discount_percent',
        'bonus_cents',
        'is_active',
        'sort_order',
        'coupon_reward_enabled',
        'coupon_reward_currency_code',
        'coupon_reward_currency_unit',
        'coupon_reward_type',
        'coupon_reward_value',
        'coupon_reward_valid_days',
        'coupon_reward_scope',
        'coupon_reward_product_ids',
        'coupon_reward_minimum_order_cents',
        'coupon_reward_quantity',
        'coupon_reward_usage_limit',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'discount_percent' => 'integer',
            'bonus_cents' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'coupon_reward_enabled' => 'boolean',
            'coupon_reward_value' => 'integer',
            'coupon_reward_valid_days' => 'integer',
            'coupon_reward_product_ids' => 'array',
            'coupon_reward_minimum_order_cents' => 'integer',
            'coupon_reward_quantity' => 'integer',
            'coupon_reward_usage_limit' => 'integer',
        ];
    }

    public function rechargeOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'wallet_recharge_option_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function payableCents(): int
    {
        $discount = $this->discount_percent === null ? 100 : max(0, min(100, (int) $this->discount_percent));

        return (int) round((int) $this->amount_cents * $discount / 100);
    }

    public function creditCents(): int
    {
        return (int) $this->amount_cents + (int) $this->bonus_cents;
    }

    public function displayName(): string
    {
        return $this->name ?: Money::format((int) $this->amount_cents).' 充值';
    }

    public function couponRewardEnabled(): bool
    {
        return (bool) $this->coupon_reward_enabled
            && (int) $this->coupon_reward_value > 0
            && (int) $this->coupon_reward_quantity > 0;
    }
}
