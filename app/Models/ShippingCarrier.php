<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingCarrier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'tracking_url_template',
        'api_endpoint',
        'api_notes',
        'is_international',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_international' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function trackingUrl(?string $trackingNumber): ?string
    {
        if (! $trackingNumber) {
            return null;
        }

        if (! $this->tracking_url_template) {
            return null;
        }

        return str_replace('{tracking_number}', rawurlencode($trackingNumber), $this->tracking_url_template);
    }
}
