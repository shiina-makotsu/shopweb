<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentProofFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'disk_path',
        'original_name',
        'mime_type',
        'size',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
