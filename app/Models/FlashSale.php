<?php

namespace App\Models;

use App\Services\StorefrontCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlashSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'flash_sale_campaign_id',
        'flash_sale_campaign_item_id',
        'product_variant_ids',
        'name',
        'sale_price_cents',
        'quantity_limit',
        'sold_quantity',
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
            'product_variant_ids' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(FlashSaleCampaign::class, 'flash_sale_campaign_id');
    }

    public function campaignItem(): BelongsTo
    {
        return $this->belongsTo(FlashSaleCampaignItem::class, 'flash_sale_campaign_item_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeActiveNow(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where(fn (Builder $query): Builder => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function remainingByPlan(): int
    {
        return max(0, (int) $this->quantity_limit - (int) $this->sold_quantity);
    }

    public function availableQuantity(): int
    {
        if ($this->product?->hasUnlimitedStock()) {
            return $this->remainingByPlan();
        }

        $stock = $this->eligibleVariants()->sum('stock');

        return min($this->remainingByPlan(), $stock);
    }

    public function eligibleVariants()
    {
        $query = ProductVariant::query()
            ->where('product_id', $this->product_id)
            ->where('is_active', true);

        if ($this->product_variant_ids) {
            $query->whereIn('id', $this->product_variant_ids);
        }

        return $query;
    }

    public function isAvailable(): bool
    {
        return $this->is_active
            && $this->starts_at?->isPast()
            && (! $this->ends_at || $this->ends_at->isFuture())
            && $this->availableQuantity() > 0;
    }

    protected static function booted(): void
    {
        static::saved(function (): void {
            app(StorefrontCache::class)->clear();
        });
        static::deleted(function (): void {
            app(StorefrontCache::class)->clear();
        });
    }
}
