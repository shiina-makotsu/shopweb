<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseStock extends Model
{
    protected $fillable = [
        'product_id',
        'product_variant_id',
        'procurement_id',
        'name',
        'sku',
        'quantity',
        'reserved_quantity',
        'note',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(WarehouseMovement::class);
    }

    public function availableQuantity(): int
    {
        return (int) $this->quantity - (int) $this->reserved_quantity;
    }
}
