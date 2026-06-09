<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public function resolve(?string $code, User $user, int $subtotalCents, Collection $cartItems): ?Coupon
    {
        $code = trim((string) $code);

        if ($code === '') {
            return null;
        }

        $coupon = Coupon::query()
            ->where('code', strtoupper($code))
            ->first();

        if (! $coupon) {
            throw ValidationException::withMessages(['coupon_code' => '优惠码不存在。']);
        }

        $this->assertUsable($coupon, $user, $subtotalCents, $cartItems);

        return $coupon;
    }

    public function assertUsable(Coupon $coupon, User $user, int $subtotalCents, Collection $cartItems): void
    {
        $now = now();

        if (! $coupon->is_active) {
            throw ValidationException::withMessages(['coupon_code' => '优惠码未启用。']);
        }

        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            throw ValidationException::withMessages(['coupon_code' => '优惠码尚未开始。']);
        }

        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            throw ValidationException::withMessages(['coupon_code' => '优惠码已过期。']);
        }

        if ($subtotalCents < $coupon->minimum_order_cents) {
            throw ValidationException::withMessages(['coupon_code' => '订单金额未达到优惠码使用门槛。']);
        }

        if (($coupon->scope ?? Coupon::SCOPE_GLOBAL) === Coupon::SCOPE_PRODUCT) {
            if (! $coupon->product_id) {
                throw ValidationException::withMessages(['coupon_code' => '该单商品优惠码尚未绑定商品。']);
            }

            if ($cartItems->count() !== 1) {
                throw ValidationException::withMessages(['coupon_code' => '单商品优惠码只能在购物车只有一个商品时使用。']);
            }

            $productId = $cartItems->first()['product']->id ?? null;

            if ((int) $productId !== (int) $coupon->product_id) {
                throw ValidationException::withMessages(['coupon_code' => '该优惠码不适用于当前商品。']);
            }
        }

        $confirmedStatuses = [CouponRedemption::STATUS_RESERVED, CouponRedemption::STATUS_CONFIRMED];

        if ($coupon->usage_limit !== null) {
            $used = $coupon->redemptions()->whereIn('status', $confirmedStatuses)->count();

            if ($used >= $coupon->usage_limit) {
                throw ValidationException::withMessages(['coupon_code' => '优惠码已被使用完。']);
            }
        }

    }
}
