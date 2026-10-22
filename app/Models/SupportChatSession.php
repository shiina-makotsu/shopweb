<?php

namespace App\Models;

use App\Services\SupportChatService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class SupportChatSession extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    public const STATUS_CLOSED = 'closed';

    public const CUSTOMER_IDLE_MINUTES = 60;

    protected $fillable = [
        'user_id',
        'order_id',
        'assigned_admin_id',
        'telegram_admin_id',
        'telegram_queued_at',
        'guest_id',
        'guest_email',
        'status',
        'last_message_at',
        'ended_at',
        'deleted_by_customer_at',
        'served_count',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'ended_at' => 'datetime',
            'deleted_by_customer_at' => 'datetime',
            'telegram_admin_id' => 'integer',
            'telegram_queued_at' => 'datetime',
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

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportChatMessage::class);
    }

    public function isEnded(): bool
    {
        return $this->status === self::STATUS_ENDED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function endIfIdle(): bool
    {
        if ($this->isEnded() || $this->isClosed() || ! $this->last_message_at) {
            return false;
        }

        if ($this->last_message_at->greaterThan(now()->subMinutes(self::CUSTOMER_IDLE_MINUTES))) {
            return false;
        }

        DB::transaction(function (): void {
            SiteSetting::query()->lockForUpdate()->first();
            $locked = static::query()->lockForUpdate()->findOrFail($this->id);
            if ($locked->isEnded() || $locked->isClosed()
                || ! $locked->last_message_at || $locked->last_message_at->greaterThan(now()->subMinutes(self::CUSTOMER_IDLE_MINUTES))) {
                return;
            }
            app(SupportChatService::class)->end($locked);
        }, 3);
        $this->refresh();

        return $this->isEnded();
    }
}
