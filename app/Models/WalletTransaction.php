<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use HasFactory;

    public const TYPE_CREDIT = 'credit';
    public const TYPE_DEBIT = 'debit';
    public const SOURCE_REDEEM_CODE = 'redeem_code';
    public const SOURCE_ORDER_PAYMENT = 'order_payment';
    public const SOURCE_ORDER_REFUND = 'order_refund';
    public const SOURCE_WALLET_RECHARGE = 'wallet_recharge';
    public const SOURCE_ADMIN = 'admin';
    public const SOURCE_SYSTEM = 'system';
    public const SOURCE_REFERRAL = 'referral';
    public const SOURCE_EVENT_REWARD = 'event_reward';

    protected $fillable = [
        'user_id',
        'order_id',
        'wallet_redeem_code_id',
        'created_by_user_id',
        'type',
        'amount_cents',
        'balance_after_cents',
        'source',
        'note',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function redeemCode(): BelongsTo
    {
        return $this->belongsTo(WalletRedeemCode::class, 'wallet_redeem_code_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
