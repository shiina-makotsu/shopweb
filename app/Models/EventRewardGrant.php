<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRewardGrant extends Model
{
    protected $fillable = [
        'referral_reward_rule_id',
        'user_id',
        'event',
        'subject_type',
        'subject_id',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ReferralRewardRule::class, 'referral_reward_rule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
