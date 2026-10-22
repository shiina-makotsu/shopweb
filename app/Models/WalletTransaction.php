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

    public const SOURCE_WALLET_RECHARGE_REVERSAL = 'wallet_recharge_reversal';

    public const SOURCE_ADMIN = 'admin';

    public const SOURCE_SYSTEM = 'system';

    public const SOURCE_REFERRAL = 'referral';

    public const SOURCE_EVENT_REWARD = 'event_reward';

    protected $fillable = [
        'user_id',
        'order_id',
        'wallet_redeem_code_id',
        'created_by_user_id',
        'after_sales_request_id',
        'idempotency_key',
        'type',
        'amount_cents',
        'balance_after_cents',
        'source',
        'note',
    ];

    /** @return array<string, string> */
    public static function sourceLabels(): array
    {
        return [
            self::SOURCE_REDEEM_CODE => '兑换码充值',
            self::SOURCE_ORDER_PAYMENT => '订单支付',
            self::SOURCE_ORDER_REFUND => '订单退款',
            self::SOURCE_WALLET_RECHARGE => '钱包充值',
            self::SOURCE_WALLET_RECHARGE_REVERSAL => '充值撤销',
            self::SOURCE_ADMIN => '后台调整',
            self::SOURCE_SYSTEM => '系统操作',
            self::SOURCE_REFERRAL => '邀请奖励',
            self::SOURCE_EVENT_REWARD => '活动奖励',
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

    public function redeemCode(): BelongsTo
    {
        return $this->belongsTo(WalletRedeemCode::class, 'wallet_redeem_code_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function afterSalesRequest(): BelongsTo
    {
        return $this->belongsTo(AfterSalesRequest::class);
    }
}
