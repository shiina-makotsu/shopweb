<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentVerificationLog extends Model
{
    public const AUTO_PENDING = 'pending';
    public const AUTO_PASSED = 'passed';
    public const AUTO_FAILED = 'failed';

    public const MANUAL_CONFIRMED = 'confirmed';
    public const MANUAL_REJECTED = 'rejected';

    protected $fillable = [
        'order_id',
        'user_id',
        'actor_user_id',
        'payment_proof_path',
        'expected_order_number',
        'detected_order_number',
        'expected_amount_cents',
        'detected_amount_cents',
        'detected_paid_at',
        'auto_result',
        'manual_result',
        'note',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'detected_paid_at' => 'datetime',
            'metadata' => 'array',
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

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
