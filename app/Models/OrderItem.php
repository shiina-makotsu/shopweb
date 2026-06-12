<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'product_title',
        'product_status',
        'variant_sku',
        'variant_specs',
        'unit_price_cents',
        'quantity',
        'line_total_cents',
        'status',
        'incoming_product_id',
        'flash_sale_id',
        'coupon_id',
        'coupon_code',
        'discount_cents',
    ];

    protected function casts(): array
    {
        return [
            'variant_specs' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function incomingProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'incoming_product_id');
    }

    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function procurementAllocations()
    {
        return $this->hasMany(ProcurementUserAllocation::class);
    }
}
