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
    public const APPLICATION_ONE_TIME = 'one_time';
    public const APPLICATION_RECURRING = 'recurring';
    public const APPLICATION_PROCUREMENT = 'procurement';

    protected $fillable = [
        'procurement_id',
        'order_id',
        'created_by_id',
        'category',
        'application_type',
        'name',
        'amount_cents',
        'currency_code',
        'currency_unit',
        'original_amount',
        'exchange_rate',
        'is_effective',
        'effective_times',
        'effective_quantity',
        'effective_at',
        'country',
        'tax_rate',
        'is_auto',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'tax_rate' => 'decimal:4',
            'original_amount' => 'decimal:4',
            'exchange_rate' => 'decimal:6',
            'is_effective' => 'boolean',
            'effective_at' => 'datetime',
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

    /**
     * @return array<string, string>
     */
    public static function applicationTypeOptions(): array
    {
        return [
            self::APPLICATION_RECURRING => '持续成本',
            self::APPLICATION_PROCUREMENT => '采购触发成本',
            self::APPLICATION_ONE_TIME => '一次性成本',
        ];
    }

    public function effectiveAmountCents(): int
    {
        return $this->is_effective ? (int) $this->amount_cents : 0;
    }
}
