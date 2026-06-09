<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductIntentVote extends Model
{
    use HasFactory;

    public const INTENT_WANT = 'want';
    public const INTENT_CONSIDERING = 'considering';
    public const INTENT_NOT_NOW = 'not_now';

    protected $fillable = [
        'product_id',
        'user_id',
        'intent',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
