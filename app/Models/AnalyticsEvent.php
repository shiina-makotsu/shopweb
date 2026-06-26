<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsEvent extends Model
{
    use HasFactory;

    public const PAGE_VIEW = 'page_view';
    public const PRODUCT_IMPRESSION = 'product_impression';
    public const PRODUCT_VIEW = 'product_view';
    public const ADD_TO_CART = 'add_to_cart';
    public const BUY_NOW = 'buy_now';
    public const CHECKOUT_VIEW = 'checkout_view';
    public const ORDER_CREATED = 'order_created';

    protected $fillable = [
        'event',
        'user_id',
        'session_id',
        'product_id',
        'product_variant_id',
        'order_id',
        'source',
        'surface',
        'visitor_type',
        'device_type',
        'path',
        'referrer',
        'ip_hash',
        'ip_region',
        'ip_country',
        'user_agent',
        'quantity',
        'amount_cents',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
