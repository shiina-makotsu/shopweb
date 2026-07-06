<?php

namespace App\Models;

use App\Support\MediaPath;
use App\Services\StorefrontCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $touches = ['product'];

    protected $fillable = [
        'product_id',
        'sku',
        'spec_name',
        'specs',
        'image_path',
        'price_cents',
        'compare_at_price_cents',
        'discount_price_cents',
        'discount_starts_at',
        'discount_ends_at',
        'stock',
        'low_stock_threshold',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'specs' => 'array',
            'is_active' => 'boolean',
            'discount_starts_at' => 'datetime',
            'discount_ends_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function flashSales(): HasMany
    {
        return $this->hasMany(FlashSale::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function specLabel(): string
    {
        return $this->displayName();
    }

    public function displayName(): string
    {
        $name = trim((string) $this->spec_name);

        if ($name !== '') {
            return $name;
        }

        return $this->specsLabel($this->specs ?? []);
    }

    public function detailSpecLabel(): string
    {
        $specs = $this->specs ?? [];

        if ($specs === []) {
            return '默认规格';
        }

        return self::specsLabel($specs);
    }

    /**
     * @return array<int, array{name: string, value: string, label: string}>
     */
    public function specItems(): array
    {
        return self::formatSpecItems($this->specs ?? []);
    }

    /**
     * @param  array<mixed>  $specs
     * @return array<int, array{name: string, value: string, label: string}>
     */
    public static function formatSpecItems(array $specs): array
    {
        $items = [];

        foreach ($specs as $key => $value) {
            $name = trim((string) $key);
            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            if ($name === '' || is_int($key)) {
                $name = '规格';
            }

            $items[] = [
                'name' => $name,
                'value' => $value,
                'label' => $value.$name,
            ];
        }

        return $items;
    }

    /**
     * @param  array<mixed>|null  $specs
     */
    public static function specsLabel(?array $specs): string
    {
        $items = self::formatSpecItems($specs ?? []);

        if ($items === []) {
            return '默认规格';
        }

        return collect($items)->pluck('label')->implode(' * ');
    }

    public function imageUrl(): ?string
    {
        return MediaPath::url($this->image_path);
    }

    public function effectivePriceCents(): int
    {
        if (
            $this->discount_price_cents !== null
            && $this->discount_price_cents > 0
            && (! $this->discount_starts_at || $this->discount_starts_at->isPast())
            && (! $this->discount_ends_at || $this->discount_ends_at->isFuture())
        ) {
            return min((int) $this->price_cents, (int) $this->discount_price_cents);
        }

        return (int) $this->price_cents;
    }

    public function hasActiveDiscount(): bool
    {
        return $this->effectivePriceCents() < (int) $this->price_cents;
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
