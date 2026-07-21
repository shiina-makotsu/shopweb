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
    public const REFUND_REQUESTED = 'requested';
    public const REFUND_APPROVED = 'approved';
    public const REFUND_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'order_id',
        'support_ticket_id',
        'type',
        'status',
        'subject',
        'message',
        'admin_note',
        'customer_read_at',
        'resolution_type',
        'refund_amount_cents',
        'refund_status',
        'refund_requested_by_id',
        'refund_requested_at',
        'refund_reviewed_by_id',
        'refund_reviewed_at',
        'coupon_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'refund_requested_at' => 'datetime',
            'refund_reviewed_at' => 'datetime',
            'resolved_at' => 'datetime',
            'customer_read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (AfterSalesRequest $request): void {
            if ($request->isDirty('admin_note') && filled($request->admin_note)) {
                $request->customer_read_at = null;
            }
        });
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

    public function refundRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refund_requested_by_id');
    }

    public function refundReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refund_reviewed_by_id');
    }
}
