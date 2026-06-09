<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseMovement extends Model
{
    public const TYPE_RECEIVED = 'received';
    public const TYPE_SHIPPED = 'shipped';
    public const TYPE_RETURNED = 'returned';
    public const TYPE_MANUAL_IN = 'manual_in';
    public const TYPE_MANUAL_OUT = 'manual_out';
    public const TYPE_PROCESSING_IN = 'processing_in';
    public const TYPE_PROCESSING_OUT = 'processing_out';
    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'warehouse_stock_id',
        'procurement_id',
        'order_id',
        'order_item_id',
        'product_id',
        'product_variant_id',
        'user_id',
        'type',
        'delta',
        'quantity_after',
        'note',
    ];

    public function stock(): BelongsTo
    {
        return $this->belongsTo(WarehouseStock::class, 'warehouse_stock_id');
    }

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_RECEIVED => '采购入库',
            self::TYPE_SHIPPED => '订单出库',
            self::TYPE_RETURNED => '退回入库',
            self::TYPE_MANUAL_IN => '人工入库',
            self::TYPE_MANUAL_OUT => '人工出库',
            self::TYPE_PROCESSING_IN => '加工入库',
            self::TYPE_PROCESSING_OUT => '加工出库',
            self::TYPE_ADJUSTMENT => '数量校准',
        ];
    }
}
