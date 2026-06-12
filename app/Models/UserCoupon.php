<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserCoupon extends Model
{
    use HasFactory;

    public const SOURCE_CLAIMED = 'claimed';
    public const SOURCE_ADMIN = 'admin';
    public const SOURCE_AFTER_SALES = 'after_sales';

    protected $fillable = [
        'user_id',
        'coupon_id',
        'issued_by_user_id',
        'after_sales_request_id',
        'source',
        'claimed_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function afterSalesRequest(): BelongsTo
    {
        return $this->belongsTo(AfterSalesRequest::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function statusLabel(): string
    {
        $coupon = $this->coupon;

        if (! $coupon?->is_active) {
            return '已停用';
        }

        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            return '未开始';
        }

        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            return '已过期';
        }

        if ($this->redemptions()
            ->whereIn('status', [CouponRedemption::STATUS_RESERVED, CouponRedemption::STATUS_CONFIRMED])
            ->exists()) {
            return '已使用';
        }

        return '可用';
    }
}
