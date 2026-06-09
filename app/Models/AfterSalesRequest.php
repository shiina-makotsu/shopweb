<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AfterSalesRequest extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CONTACTING = 'contacting';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';
    public const RESOLUTION_REFUND = 'refund';
    public const RESOLUTION_COUPON = 'coupon';
    public const RESOLUTION_MESSAGE = 'message';

    protected $fillable = [
        'user_id',
        'order_id',
        'support_ticket_id',
        'type',
        'status',
        'subject',
        'message',
        'admin_note',
        'resolution_type',
        'refund_amount_cents',
        'coupon_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function supportTicket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
