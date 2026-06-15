<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlashSaleCampaignItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'flash_sale_campaign_id',
        'product_id',
        'product_variant_ids',
        'sale_price_cents',
        'quantity_limit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'product_variant_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(FlashSaleCampaign::class, 'flash_sale_campaign_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function flashSales(): HasMany
    {
        return $this->hasMany(FlashSale::class);
    }
}
