<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use App\Support\Money;

class Product extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONCEPT = 'concept';
    public const STATUS_PRESALE = 'presale';
    public const STATUS_INCOMING = 'incoming';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_SOLD_OUT = 'sold_out';
    public const FULFILLMENT_ONLINE = 'online';
    public const FULFILLMENT_LOGISTICS = 'logistics';
    public const FULFILLMENT_IN_PERSON = 'in_person';
    public const FULFILLMENT_SHIPPING_LEGACY = 'shipping_required';
    public const FULFILLMENT_CONTACT_LEGACY = 'contact_only';
    public const FULFILLMENT_SHIPPING = self::FULFILLMENT_LOGISTICS;
    public const FULFILLMENT_CONTACT = self::FULFILLMENT_ONLINE;

    protected $fillable = [
        'category_id',
        'manufacturer_id',
        'supplier_id',
        'title',
        'slug',
        'summary',
        'description',
        'status',
        'is_featured',
        'fulfillment_type',
        'delivery_status_id',
        'sold_out_status_id',
        'quantity_unit_id',
        'source_product_id',
        'incoming_quantity',
        'incoming_note',
        'shipping_carrier_id',
        'tracking_number',
        'tracking_url',
        'crowdfunding_enabled',
        'comments_enabled',
        'crowdfunding_goal_cents',
        'crowdfunding_reward',
        'crowdfunding_cancelled_at',
        'shipping_extra_fee_cents',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'comments_enabled' => 'boolean',
            'crowdfunding_enabled' => 'boolean',
            'crowdfunding_cancelled_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function deliveryStatus(): BelongsTo
    {
        return $this->belongsTo(DeliveryStatus::class);
    }

    public function soldOutStatus(): BelongsTo
    {
        return $this->belongsTo(SoldOutStatus::class);
    }

    public function quantityUnit(): BelongsTo
    {
        return $this->belongsTo(QuantityUnit::class);
    }

    public function sourceProduct(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_product_id');
    }

    public function incomingProducts(): HasMany
    {
        return $this->hasMany(self::class, 'source_product_id');
    }

    public function procurements(): HasMany
    {
        return $this->hasMany(Procurement::class);
    }

    public function shippingCarrier(): BelongsTo
    {
        return $this->belongsTo(ShippingCarrier::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ProductTag::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class)->withTimestamps();
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('sort_order');
    }

    public function coverMedia(): HasOne
    {
        return $this->hasOne(ProductMedia::class)->orderBy('sort_order');
    }

    public function priceVoteOptions(): HasMany
    {
        return $this->hasMany(PriceVoteOption::class)->orderBy('sort_order');
    }

    public function intentVotes(): HasMany
    {
        return $this->hasMany(ProductIntentVote::class);
    }

    public function priceVotes(): HasMany
    {
        return $this->hasMany(ProductPriceVote::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProductComment::class);
    }

    public function flashSales(): HasMany
    {
        return $this->hasMany(FlashSale::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_CONCEPT,
            self::STATUS_PRESALE,
            self::STATUS_INCOMING,
            self::STATUS_PUBLISHED,
            self::STATUS_SOLD_OUT,
        ]);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function requiresShipping(): bool
    {
        return in_array($this->fulfillment_type, [self::FULFILLMENT_LOGISTICS, self::FULFILLMENT_SHIPPING_LEGACY], true);
    }

    public function hasUnlimitedStock(): bool
    {
        return in_array($this->fulfillment_type, [self::FULFILLMENT_ONLINE, self::FULFILLMENT_CONTACT_LEGACY], true)
            || $this->status === self::STATUS_PRESALE;
    }

    public function usesStockLimit(): bool
    {
        return ! $this->hasUnlimitedStock();
    }

    public function isPresale(): bool
    {
        return $this->status === self::STATUS_PRESALE;
    }

    public function isConcept(): bool
    {
        return $this->status === self::STATUS_CONCEPT;
    }

    public function isIncoming(): bool
    {
        return $this->status === self::STATUS_INCOMING;
    }

    public function isSoldOut(): bool
    {
        return $this->status === self::STATUS_SOLD_OUT;
    }

    public function isPurchasable(): bool
    {
        return in_array($this->status, [self::STATUS_CONCEPT, self::STATUS_PUBLISHED, self::STATUS_PRESALE], true)
            && ($this->status === self::STATUS_CONCEPT || $this->hasUnlimitedStock() || (int) $this->activeVariants()->sum('stock') > 0);
    }

    public function isDirectlyPurchasable(): bool
    {
        return in_array($this->status, [self::STATUS_PUBLISHED, self::STATUS_PRESALE], true)
            && ($this->hasUnlimitedStock() || (int) $this->activeVariants()->sum('stock') > 0);
    }

    public function allowsCrowdfunding(): bool
    {
        return $this->isConcept();
    }

    public function allowsVoting(): bool
    {
        return $this->isConcept();
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? $this->status;
    }

    public function fulfillmentLabel(): string
    {
        return self::fulfillmentOptions()[$this->fulfillment_type] ?? match ($this->fulfillment_type) {
            self::FULFILLMENT_SHIPPING_LEGACY => '物流交付',
            self::FULFILLMENT_CONTACT_LEGACY => '线上交付',
            default => $this->fulfillment_type,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => '草稿',
            self::STATUS_CONCEPT => '概念',
            self::STATUS_PRESALE => '预售',
            self::STATUS_INCOMING => '进货中',
            self::STATUS_PUBLISHED => '现货',
            self::STATUS_SOLD_OUT => '售罄',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function editableStatusOptions(): array
    {
        return [
            self::STATUS_DRAFT => '草稿',
            self::STATUS_CONCEPT => '概念',
            self::STATUS_PRESALE => '预售',
            self::STATUS_INCOMING => '进货中',
            self::STATUS_PUBLISHED => '现货',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function fulfillmentOptions(): array
    {
        return [
            self::FULFILLMENT_ONLINE => '线上交付',
            self::FULFILLMENT_LOGISTICS => '物流交付',
            self::FULFILLMENT_IN_PERSON => '当面交付',
        ];
    }

    public function getStartingPriceCentsAttribute(): ?int
    {
        $variants = $this->variants->where('is_active', true);

        if ($variants->isEmpty()) {
            return null;
        }

        return $variants->map(fn (ProductVariant $variant): int => $variant->effectivePriceCents())->min();
    }

    public function getEndingPriceCentsAttribute(): ?int
    {
        $variants = $this->variants->where('is_active', true);

        if ($variants->isEmpty()) {
            return null;
        }

        return $variants->map(fn (ProductVariant $variant): int => $variant->effectivePriceCents())->max();
    }

    public function priceRangeLabel(): string
    {
        $min = $this->starting_price_cents;
        $max = $this->ending_price_cents;

        if ($min === null || $max === null) {
            return Money::format(null);
        }

        if ((int) $min === (int) $max) {
            return Money::format((int) $min);
        }

        return Money::format((int) $min).'~'.Money::format((int) $max);
    }

    public function hasActiveDiscount(): bool
    {
        return $this->variants->where('is_active', true)->contains(fn (ProductVariant $variant): bool => $variant->hasActiveDiscount());
    }

    protected static function booted(): void
    {
        static::saving(function (self $product): void {
            $product->slug = static::uniqueSlug($product);
        });

        static::updated(function (self $product): void {
            if (! $product->wasChanged('status')) {
                return;
            }

            if ($product->getOriginal('status') !== self::STATUS_INCOMING || $product->status !== self::STATUS_PUBLISHED) {
                return;
            }

            OrderItem::query()
                ->where('incoming_product_id', $product->id)
                ->where('status', Order::STATUS_INCOMING)
                ->update(['status' => Order::STATUS_PENDING_SHIPMENT]);

            Order::query()
                ->whereIn('status', [Order::STATUS_PENDING_SHIPMENT, Order::STATUS_INCOMING])
                ->whereHas('items', fn ($query) => $query->where('incoming_product_id', $product->id))
                ->whereDoesntHave('items', fn ($query) => $query->where('status', Order::STATUS_INCOMING))
                ->update(['status' => Order::STATUS_PENDING_SHIPMENT]);
        });
    }

    private static function uniqueSlug(self $product): string
    {
        $current = trim((string) $product->slug);

        if (
            $product->exists
            && $current !== ''
            && $product->getOriginal('slug') === $current
            && preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $current)
        ) {
            return $current;
        }

        $base = Str::slug($current !== '' ? $current : (string) $product->title);

        if ($base === '') {
            $base = 'product-'.Str::lower(Str::random(8));
        }

        $slug = $base;
        $index = 2;

        while (static::query()
            ->where('slug', $slug)
            ->when($product->getKey(), fn (Builder $query) => $query->whereKeyNot($product->getKey()))
            ->exists()) {
            $suffix = '-'.$index++;
            $slug = substr($base, 0, 255 - strlen($suffix)).$suffix;
        }

        return $slug;
    }
}
