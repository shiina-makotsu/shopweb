<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferralRewardRule extends Model
{
    use HasFactory;

    public const EVENT_REFERRAL_REGISTERED = 'referral_registered';
    public const EVENT_ORDER_PAID_PRODUCT = 'order_paid_product';
    public const EVENT_FORUM_THREAD_CREATED = 'forum_thread_created';
    public const EVENT_WALLET_PAYMENT_USED = 'wallet_payment_used';

    protected $fillable = [
        'name',
        'trigger_events',
        'product_ids',
        'coupon_id',
        'coupon_reward_enabled',
        'coupon_reward_rules',
        'wallet_amount_cents',
        'is_active',
        'sort_order',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'trigger_events' => 'array',
            'product_ids' => 'array',
            'coupon_reward_enabled' => 'boolean',
            'coupon_reward_rules' => 'array',
            'wallet_amount_cents' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function grants(): HasMany
    {
        return $this->hasMany(EventRewardGrant::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return array<string, string>
     */
    public static function eventOptions(): array
    {
        return [
            self::EVENT_REFERRAL_REGISTERED => '邀请注册',
            self::EVENT_ORDER_PAID_PRODUCT => '购买指定商品',
            self::EVENT_FORUM_THREAD_CREATED => '论坛发帖',
            self::EVENT_WALLET_PAYMENT_USED => '使用钱包付款',
        ];
    }

    public static function eventLabel(string $event): string
    {
        return self::eventOptions()[$event] ?? $event;
    }

    /**
     * @return array<int, string>
     */
    public function eventLabels(): array
    {
        return collect($this->normalizedEvents())
            ->map(fn (string $event): string => self::eventLabel($event))
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function getEventLabelsAttribute(): array
    {
        return $this->eventLabels();
    }

    /**
     * @return array<int, string>
     */
    public function normalizedEvents(): array
    {
        $events = $this->trigger_events ?: [self::EVENT_REFERRAL_REGISTERED];

        return collect($events)
            ->map(fn ($event): string => (string) $event)
            ->filter(fn (string $event): bool => array_key_exists($event, self::eventOptions()))
            ->values()
            ->all();
    }

    public function appliesToEvent(string $event, array $context = []): bool
    {
        if (! in_array($event, $this->normalizedEvents(), true)) {
            return false;
        }

        if ($event !== self::EVENT_ORDER_PAID_PRODUCT) {
            return true;
        }

        $productIds = collect($this->product_ids ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->values();

        if ($productIds->isEmpty()) {
            return true;
        }

        $eventProductIds = collect($context['product_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->values();

        return $eventProductIds->intersect($productIds)->isNotEmpty();
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
        return collect($this->coupon_reward_rules ?: [])
            ->map(fn (array $rule): array => $this->normalizeCouponRewardRule($rule))
            ->filter(fn (array $rule): bool => (int) $rule['value'] > 0 && (int) $rule['quantity'] > 0)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function couponRulesForIssuance(): array
    {
        if ($this->couponRewardEnabled()) {
            return $this->couponRewardRules();
        }

        $coupon = $this->coupon;

        if (! $coupon) {
            return [];
        }

        $coupon->loadMissing('products');
        $productIds = $coupon->products->pluck('id')->map(fn ($id): int => (int) $id);

        if ($productIds->isEmpty() && $coupon->product_id) {
            $productIds->push((int) $coupon->product_id);
        }

        return [[
            'name' => '',
            'currency_code' => 'CNY',
            'currency_unit' => 'yuan',
            'type' => $coupon->type,
            'value' => (int) $coupon->value,
            'valid_days' => $coupon->ends_at && $coupon->ends_at->isFuture()
                ? max(1, now()->diffInDays($coupon->ends_at, false))
                : null,
            'scope' => $coupon->scope ?? Coupon::SCOPE_GLOBAL,
            'product_ids' => $productIds->unique()->values()->all(),
            'minimum_order_cents' => (int) $coupon->minimum_order_cents,
            'quantity' => 1,
            'usage_limit' => max(1, (int) ($coupon->usage_limit ?? 1)),
            'per_user_limit' => max(1, (int) ($coupon->per_user_limit ?? 1)),
            'is_stackable' => (bool) $coupon->is_stackable,
        ]];
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    private function normalizeCouponRewardRule(array $rule): array
    {
        $type = ($rule['type'] ?? Coupon::TYPE_FIXED) === Coupon::TYPE_PERCENT ? Coupon::TYPE_PERCENT : Coupon::TYPE_FIXED;
        $scope = ($rule['scope'] ?? Coupon::SCOPE_GLOBAL) === Coupon::SCOPE_PRODUCT ? Coupon::SCOPE_PRODUCT : Coupon::SCOPE_GLOBAL;
        $usageLimit = max(1, (int) ($rule['usage_limit'] ?? 1));

        return [
            'name' => trim((string) ($rule['name'] ?? '')),
            'currency_code' => (string) ($rule['currency_code'] ?? 'CNY'),
            'currency_unit' => (string) ($rule['currency_unit'] ?? 'yuan'),
            'type' => $type,
            'value' => $type === Coupon::TYPE_PERCENT
                ? max(0, min(100, (int) ($rule['value'] ?? 0)))
                : max(0, (int) ($rule['value'] ?? 0)),
            'valid_days' => filled($rule['valid_days'] ?? null) ? max(1, (int) $rule['valid_days']) : null,
            'scope' => $scope,
            'product_ids' => array_values(array_filter(array_map('intval', $rule['product_ids'] ?? []))),
            'minimum_order_cents' => max(0, (int) ($rule['minimum_order_cents'] ?? 0)),
            'quantity' => max(1, (int) ($rule['quantity'] ?? 1)),
            'usage_limit' => $usageLimit,
            'per_user_limit' => max(1, min($usageLimit, (int) ($rule['per_user_limit'] ?? 1))),
            'is_stackable' => (bool) ($rule['is_stackable'] ?? false),
        ];
    }
}
