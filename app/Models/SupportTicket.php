<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicket extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_REPLIED = 'replied';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'user_id',
        'order_id',
        'guest_id',
        'guest_email',
        'category',
        'subject',
        'message',
        'status',
        'admin_reply',
        'customer_read_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
            'customer_read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (SupportTicket $ticket): void {
            if ($ticket->isDirty('admin_reply') && filled($ticket->admin_reply)) {
                $ticket->customer_read_at = null;
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
}
