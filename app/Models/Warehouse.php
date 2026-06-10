<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $fillable = [
        'name',
        'contact_name',
        'phone',
        'country',
        'province',
        'city',
        'district',
        'street',
        'address',
        'is_active',
        'sort_order',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(WarehouseMovement::class);
    }

    public function shippingRates(): HasMany
    {
        return $this->hasMany(WarehouseShippingRate::class)->orderBy('sort_order');
    }

    public function displayAddress(): string
    {
        return trim(implode(' ', array_filter([
            $this->country,
            $this->province,
            $this->city,
            $this->district,
            $this->street ?: $this->address,
        ])));
    }
}
