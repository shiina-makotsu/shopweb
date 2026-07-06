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
}
