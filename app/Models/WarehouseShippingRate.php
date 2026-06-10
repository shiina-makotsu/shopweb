<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseShippingRate extends Model
{
    protected $fillable = [
        'warehouse_id',
        'name',
        'provinces',
        'fee_cents',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'provinces' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function matchesProvince(?string $province): bool
    {
        if ($this->is_default) {
            return true;
        }

        if (! $province) {
            return false;
        }

        return in_array($province, $this->provinces ?: [], true);
    }
}
