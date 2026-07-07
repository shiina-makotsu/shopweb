<?php

namespace App\Models;

use App\Support\Money;
use App\Models\Coupon;
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
        'coupon_reward_rules',
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
            'coupon_reward_rules' => 'array',
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
        return (bool) $this->coupon_reward_enabled && $this->couponRewardRules() !== [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function couponRewardRules(): array
    {
        $rules = collect($this->coupon_reward_rules ?: [])
            ->map(fn (array $rule): array => $this->normalizeCouponRewardRule($rule))
            ->filter(fn (array $rule): bool => (int) $rule['value'] > 0 && (int) $rule['quantity'] > 0)
            ->values()
            ->all();

        if ($rules !== []) {
            return $rules;
        }

        $legacy = $this->normalizeCouponRewardRule([
            'currency_code' => $this->coupon_reward_currency_code,
            'currency_unit' => $this->coupon_reward_currency_unit,
            'type' => $this->coupon_reward_type,
            'value' => $this->coupon_reward_value,
            'valid_days' => $this->coupon_reward_valid_days,
            'scope' => $this->coupon_reward_scope,
            'product_ids' => $this->coupon_reward_product_ids,
            'minimum_order_cents' => $this->coupon_reward_minimum_order_cents,
            'quantity' => $this->coupon_reward_quantity,
            'usage_limit' => $this->coupon_reward_usage_limit,
        ]);

        return (int) $legacy['value'] > 0 && (int) $legacy['quantity'] > 0 ? [$legacy] : [];
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    private function normalizeCouponRewardRule(array $rule): array
    {
        $type = ($rule['type'] ?? Coupon::TYPE_FIXED) === Coupon::TYPE_PERCENT ? Coupon::TYPE_PERCENT : Coupon::TYPE_FIXED;
        $scope = ($rule['scope'] ?? Coupon::SCOPE_GLOBAL) === Coupon::SCOPE_PRODUCT ? Coupon::SCOPE_PRODUCT : Coupon::SCOPE_GLOBAL;

        return [
            'name' => trim((string) ($rule['name'] ?? '')),
            'currency_code' => (string) ($rule['currency_code'] ?? $this->coupon_reward_currency_code ?? 'CNY'),
            'currency_unit' => (string) ($rule['currency_unit'] ?? $this->coupon_reward_currency_unit ?? 'yuan'),
            'type' => $type,
            'value' => $type === Coupon::TYPE_PERCENT
                ? max(0, min(100, (int) ($rule['value'] ?? 0)))
                : max(0, (int) ($rule['value'] ?? 0)),
            'valid_days' => filled($rule['valid_days'] ?? null) ? max(1, (int) $rule['valid_days']) : null,
            'scope' => $scope,
            'product_ids' => array_values(array_filter(array_map('intval', $rule['product_ids'] ?? []))),
            'minimum_order_cents' => max(0, (int) ($rule['minimum_order_cents'] ?? 0)),
            'quantity' => max(1, (int) ($rule['quantity'] ?? 1)),
            'usage_limit' => max(1, (int) ($rule['usage_limit'] ?? 1)),
            'is_stackable' => (bool) ($rule['is_stackable'] ?? false),
        ];
    }
}
