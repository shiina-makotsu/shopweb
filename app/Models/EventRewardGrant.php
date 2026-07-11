<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRewardGrant extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'referral_reward_rule_id',
        'user_id',
        'event',
        'subject_type',
        'subject_id',
        'status',
        'coupon_ids',
        'wallet_amount_cents',
        'reward_snapshot',
        'error_message',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'coupon_ids' => 'array',
            'wallet_amount_cents' => 'integer',
            'reward_snapshot' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ReferralRewardRule::class, 'referral_reward_rule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
