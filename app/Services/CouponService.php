<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\User;
use App\Models\UserCoupon;
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

    public function claimByCode(?string $code, User $user): UserCoupon
    {
        $code = strtoupper(trim((string) $code));

        if ($code === '') {
            throw ValidationException::withMessages(['coupon_code' => '请输入优惠码。']);
        }

        $coupon = Coupon::query()
            ->where('code', $code)
            ->first();

        if (! $coupon) {
            throw ValidationException::withMessages(['coupon_code' => '优惠码不存在。']);
        }

        if ($user->coupons()->where('coupon_id', $coupon->id)->exists()) {
            throw ValidationException::withMessages(['coupon_code' => '你已经添加过这个优惠码。']);
        }

        $this->assertCouponCanBeClaimed($coupon, $user);

        return $this->issueToUser($coupon, $user, UserCoupon::SOURCE_CLAIMED);
    }

    public function issueToUser(
        Coupon $coupon,
        User $user,
        string $source = UserCoupon::SOURCE_ADMIN,
        ?User $issuer = null,
        ?int $afterSalesRequestId = null,
        ?string $note = null,
    ): UserCoupon {
        $existing = $user->coupons()->where('coupon_id', $coupon->id)->first();

        if (! $existing) {
            $this->assertCouponCanBeClaimed($coupon, $user);
        }

        return UserCoupon::query()->updateOrCreate([
            'user_id' => $user->id,
            'coupon_id' => $coupon->id,
        ], [
            'issued_by_user_id' => $issuer?->id,
            'after_sales_request_id' => $afterSalesRequestId,
            'source' => $source,
            'claimed_at' => now(),
            'note' => $note,
        ]);
    }

    /**
     * @return array<int, array<int, UserCoupon>>
     */
    public function availableForCart(User $user, Collection $cartItems): array
    {
        $userCoupons = $user->coupons()
            ->with(['coupon.products', 'coupon.product'])
            ->latest()
            ->get()
            ->filter(fn (UserCoupon $userCoupon): bool => $this->isUserCouponUsable($userCoupon, $user));

        $available = [];

        foreach ($cartItems as $cartItem) {
            $variantId = (int) $cartItem['variant']->id;
            $productId = (int) $cartItem['product']->id;
            $lineTotal = (int) $cartItem['line_total_cents'];

            $available[$variantId] = $userCoupons
                ->filter(fn (UserCoupon $userCoupon): bool => $this->couponMatchesLine($userCoupon->coupon, $user, $productId, $lineTotal))
                ->values()
                ->all();
        }

        return $available;
    }

    /**
     * @param  array<int|string, mixed>  $selectedUserCouponIds
     * @return array<int, array{user_coupon:UserCoupon,coupon:Coupon,discount_cents:int}>
     */
    public function resolveForCart(User $user, Collection $cartItems, array $selectedUserCouponIds): array
    {
        $selectedUserCouponIds = collect($selectedUserCouponIds)
            ->mapWithKeys(fn ($value, $key): array => [(int) $key => (int) $value])
            ->filter(fn (int $value): bool => $value > 0);

        if ($selectedUserCouponIds->isEmpty()) {
            return [];
        }

        $available = $this->availableForCart($user, $cartItems);
        $resolved = [];
        $usedUserCouponIds = [];

        foreach ($cartItems as $cartItem) {
            $variantId = (int) $cartItem['variant']->id;
            $selectedId = (int) ($selectedUserCouponIds[$variantId] ?? 0);

            if ($selectedId <= 0) {
                continue;
            }

            if (in_array($selectedId, $usedUserCouponIds, true)) {
                throw ValidationException::withMessages(['coupon_items' => '同一张优惠码不能同时用于多个 SKU。']);
            }

            $userCoupon = collect($available[$variantId] ?? [])->first(fn (UserCoupon $candidate): bool => $candidate->id === $selectedId);

            if (! $userCoupon) {
                throw ValidationException::withMessages(['coupon_items' => '选择的优惠码不适用于当前商品。']);
            }

            $coupon = $userCoupon->coupon;
            $lineTotal = (int) $cartItem['line_total_cents'];

            $resolved[$variantId] = [
                'user_coupon' => $userCoupon,
                'coupon' => $coupon,
                'discount_cents' => $coupon->discountFor($lineTotal),
            ];

            $usedUserCouponIds[] = $selectedId;
        }

        if (count($resolved) > 1 && collect($resolved)->contains(fn (array $item): bool => ! (bool) $item['coupon']->is_stackable)) {
            throw ValidationException::withMessages(['coupon_items' => '所选优惠码不允许在同一笔订单中叠加使用。']);
        }

        return $resolved;
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
            $coupon->loadMissing('products');

            if (! $coupon->product_id && $coupon->products->isEmpty()) {
                throw ValidationException::withMessages(['coupon_code' => '该单商品优惠码尚未绑定商品。']);
            }

            if ($cartItems->count() !== 1) {
                throw ValidationException::withMessages(['coupon_code' => '单商品优惠码只能在购物车只有一个商品时使用。']);
            }

            $productId = $cartItems->first()['product']->id ?? null;

            if (! $productId || ! $coupon->appliesToProduct((int) $productId)) {
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

    private function assertCouponCanBeClaimed(Coupon $coupon, User $user): void
    {
        if (! $coupon->is_active) {
            throw ValidationException::withMessages(['coupon_code' => '优惠码未启用。']);
        }

        if ($coupon->usage_limit !== null) {
            $used = $coupon->redemptions()
                ->whereIn('status', [CouponRedemption::STATUS_RESERVED, CouponRedemption::STATUS_CONFIRMED])
                ->count();

            if ($used >= $coupon->usage_limit) {
                throw ValidationException::withMessages(['coupon_code' => '优惠码已被使用完。']);
            }
        }

    }

    private function isUserCouponUsable(UserCoupon $userCoupon, User $user): bool
    {
        $coupon = $userCoupon->coupon;

        if (! $coupon) {
            return false;
        }

        if ($userCoupon->user_id !== $user->id || ! $coupon->is_active) {
            return false;
        }

        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            return false;
        }

        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            return false;
        }

        if ($userCoupon->redemptions()
            ->whereIn('status', [CouponRedemption::STATUS_RESERVED, CouponRedemption::STATUS_CONFIRMED])
            ->exists()) {
            return false;
        }

        return true;
    }

    private function couponMatchesLine(Coupon $coupon, User $user, int $productId, int $lineTotalCents): bool
    {
        try {
            $this->assertUsageLimits($coupon);
        } catch (ValidationException) {
            return false;
        }

        if ($lineTotalCents < (int) $coupon->minimum_order_cents) {
            return false;
        }

        if (! $coupon->appliesToProduct($productId)) {
            return false;
        }

        return true;
    }

    private function assertUsageLimits(Coupon $coupon): void
    {
        if ($coupon->usage_limit === null) {
            return;
        }

        $used = $coupon->redemptions()
            ->whereIn('status', [CouponRedemption::STATUS_RESERVED, CouponRedemption::STATUS_CONFIRMED])
            ->count();

        if ($used >= $coupon->usage_limit) {
            throw ValidationException::withMessages(['coupon_code' => '优惠码已被使用完。']);
        }
    }
}
