<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Procurement extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_INCOMING = 'incoming';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'product_id',
        'incoming_product_id',
        'created_by_id',
        'name',
        'quantity',
        'purchase_amount_cents',
        'shipping_amount_cents',
        'shipping_country',
        'customs_tax_rate',
        'customs_tax_cents',
        'international_tracking_number',
        'tracking_url',
        'note',
        'status',
        'received_at',
        'warehouse_note',
    ];

    protected function casts(): array
    {
        return [
            'customs_tax_rate' => 'decimal:4',
            'received_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function incomingProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'incoming_product_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ProcurementUserAllocation::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(CostEntry::class);
    }

    public function totalCostCents(): int
    {
        return (int) $this->costs()->sum('amount_cents');
    }
}
