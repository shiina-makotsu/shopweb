<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostEntry extends Model
{
    public const CATEGORY_PURCHASE = 'purchase';
    public const CATEGORY_SHIPPING = 'shipping';
    public const CATEGORY_CUSTOMS = 'customs';
    public const CATEGORY_OTHER = 'other';

    protected $fillable = [
        'procurement_id',
        'order_id',
        'created_by_id',
        'category',
        'name',
        'amount_cents',
        'country',
        'tax_rate',
        'is_auto',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'tax_rate' => 'decimal:4',
            'is_auto' => 'boolean',
        ];
    }

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return array<string, string>
     */
    public static function categoryOptions(): array
    {
        return [
            self::CATEGORY_PURCHASE => '采购成本',
            self::CATEGORY_SHIPPING => '运输成本',
            self::CATEGORY_CUSTOMS => '海关税务成本',
            self::CATEGORY_OTHER => '其他成本',
        ];
    }
}
