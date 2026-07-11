<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\User;
use App\Models\UserCoupon;
use App\Support\Money;
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
            $this->assertHolderCapacity($coupon);
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
     * @param  iterable<int, int>  $couponIds
     */
    public function markExhaustedHoldings(User $user, iterable $couponIds): void
    {
        $this->syncHoldingStates($user, $couponIds, false);
    }

    /**
     * @param  iterable<int, int>  $couponIds
     */
    public function restoreReleasedHoldings(User $user, iterable $couponIds): void
    {
        $this->syncHoldingStates($user, $couponIds, true);
    }

    /**
     * @return array<int, array{user_coupon:UserCoupon,coupon:?Coupon,available:bool,reason:?string,discount_cents:int}>
     */
    public function choicesForOrder(User $user, Collection $cartItems): array
    {
        $userCoupons = $user->coupons()
            ->visibleToCustomer()
            ->with(['coupon.products', 'coupon.product'])
            ->latest()
            ->get();

        return $userCoupons
            ->map(function (UserCoupon $userCoupon) use ($user, $cartItems): array {
                $coupon = $userCoupon->coupon;
                $reason = $this->unavailableReasonForOrder($userCoupon, $user, $cartItems);
                $baseCents = $reason === null && $coupon
                    ? $this->discountBaseForOrder($coupon, $cartItems)
                    : 0;

                return [
                    'user_coupon' => $userCoupon,
                    'coupon' => $coupon,
                    'available' => $reason === null,
                    'reason' => $reason,
                    'discount_cents' => $reason === null && $coupon ? $coupon->discountFor($baseCents) : 0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int|string, mixed>  $selectedUserCouponIds
     * @return array{order:array<int,array{user_coupon:?UserCoupon,coupon:Coupon,discount_cents:int}>,items:array<int,array{discount_cents:int,coupon_ids:array<int,int>,coupon_codes:array<int,string>,allocations:array<int,array{user_coupon:?UserCoupon,coupon:Coupon,discount_cents:int}>}>,discount_cents:int}
     */
    public function resolveForOrder(User $user, Collection $cartItems, array $selectedUserCouponIds): array
    {
        $selectedIds = collect($selectedUserCouponIds)
            ->flatten()
            ->map(fn ($value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->values();

        $empty = ['order' => [], 'items' => [], 'discount_cents' => 0];

        if ($selectedIds->isEmpty()) {
            return $empty;
        }

        $choices = collect($this->choicesForOrder($user, $cartItems))->keyBy(fn (array $choice): int => (int) $choice['user_coupon']->id);
        $selected = [];

        foreach ($selectedIds as $selectedId) {
            $choice = $choices->get($selectedId);

            if (! $choice || ! $choice['available'] || ! $choice['coupon']) {
                throw ValidationException::withMessages(['coupon_selections' => '选择的优惠码当前不可用。']);
            }

            $selected[] = $choice;
        }

        if (count($selected) > 1 && collect($selected)->contains(fn (array $choice): bool => ! (bool) $choice['coupon']->is_stackable)) {
            throw ValidationException::withMessages(['coupon_selections' => '所选优惠码不允许在同一笔订单中叠加使用。']);
        }

        $orderAllocations = [];
        $itemDiscounts = [];
        $orderLevelDiscount = 0;

        foreach ($selected as $choice) {
            /** @var Coupon $coupon */
            $coupon = $choice['coupon'];
            /** @var UserCoupon $userCoupon */
            $userCoupon = $choice['user_coupon'];

            if (($coupon->scope ?? Coupon::SCOPE_GLOBAL) === Coupon::SCOPE_GLOBAL) {
                $base = max(0, (int) $cartItems->sum('line_total_cents') - $orderLevelDiscount);
                $discount = $coupon->discountFor($base);

                if ($discount <= 0) {
                    continue;
                }

                $orderAllocations[] = [
                    'user_coupon' => $userCoupon,
                    'coupon' => $coupon,
                    'discount_cents' => $discount,
                ];
                $orderLevelDiscount += $discount;

                continue;
            }

            $this->applyProductCouponToItems($coupon, $userCoupon, $cartItems, $itemDiscounts);
        }

        return [
            'order' => $orderAllocations,
            'items' => $itemDiscounts,
            'discount_cents' => $orderLevelDiscount + collect($itemDiscounts)->sum('discount_cents'),
        ];
    }

    /**
     * @return array{order:array<int,array{user_coupon:?UserCoupon,coupon:Coupon,discount_cents:int}>,items:array<int,array{discount_cents:int,coupon_ids:array<int,int>,coupon_codes:array<int,string>,allocations:array<int,array{user_coupon:?UserCoupon,coupon:Coupon,discount_cents:int}>}>,discount_cents:int}
     */
    public function resolveCouponForOrder(Coupon $coupon, Collection $cartItems): array
    {
        if (($coupon->scope ?? Coupon::SCOPE_GLOBAL) === Coupon::SCOPE_GLOBAL) {
            $discount = $coupon->discountFor((int) $cartItems->sum('line_total_cents'));

            return [
                'order' => $discount > 0 ? [[
                    'user_coupon' => null,
                    'coupon' => $coupon,
                    'discount_cents' => $discount,
                ]] : [],
                'items' => [],
                'discount_cents' => $discount,
            ];
        }

        $itemDiscounts = [];
        $this->applyProductCouponToItems($coupon, null, $cartItems, $itemDiscounts);

        return [
            'order' => [],
            'items' => $itemDiscounts,
            'discount_cents' => collect($itemDiscounts)->sum('discount_cents'),
        ];
    }

    /**
     * @param  array<int|string, mixed>  $selectedUserCouponIds
     * @return array<int, array{user_coupon:UserCoupon,coupon:Coupon,discount_cents:int}>
     */
    public function resolveForCart(User $user, Collection $cartItems, array $selectedUserCouponIds): array
    {
        $resolved = $this->resolveForOrder($user, $cartItems, $selectedUserCouponIds);

        return collect($resolved['items'])
            ->map(fn (array $item): array => [
                'user_coupon' => $item['allocations'][0]['user_coupon'],
                'coupon' => $item['allocations'][0]['coupon'],
                'discount_cents' => (int) $item['discount_cents'],
            ])
            ->all();
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

        $discountBaseCents = $this->discountBaseForOrder($coupon, $cartItems);

        if ($discountBaseCents < (int) $coupon->minimum_order_cents) {
            throw ValidationException::withMessages(['coupon_code' => '订单金额未达到优惠码使用门槛。']);
        }

        if (($coupon->scope ?? Coupon::SCOPE_GLOBAL) === Coupon::SCOPE_PRODUCT) {
            $coupon->loadMissing('products');

            if (! $coupon->product_id && $coupon->products->isEmpty()) {
                throw ValidationException::withMessages(['coupon_code' => '该单商品优惠码尚未绑定商品。']);
            }

            if ($discountBaseCents <= 0) {
                throw ValidationException::withMessages(['coupon_code' => '该优惠码不适用于当前商品。']);
            }
        }

        $usedByUser = $coupon->redemptions()
            ->where('user_id', $user->id)
            ->whereIn('status', [CouponRedemption::STATUS_RESERVED, CouponRedemption::STATUS_CONFIRMED])
            ->count();

        if ($usedByUser >= (int) $coupon->per_user_limit) {
            throw ValidationException::withMessages(['coupon_code' => '已达到单用户使用次数上限。']);
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

    private function assertHolderCapacity(Coupon $coupon): void
    {
        $holderCount = $coupon->userCoupons()->whereNull('exhausted_at')->count();

        if (((int) $coupon->per_user_limit * ($holderCount + 1)) > (int) $coupon->usage_limit) {
            throw ValidationException::withMessages([
                'coupon_code' => '优惠码剩余总次数不足以分配给新的持有用户。',
            ]);
        }
    }

    /**
     * @param  iterable<int, int>  $couponIds
     */
    private function syncHoldingStates(User $user, iterable $couponIds, bool $allowRestore): void
    {
        $ids = collect($couponIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return;
        }

        $holdings = $user->coupons()->with('coupon')->whereIn('coupon_id', $ids)->get();

        foreach ($holdings as $holding) {
            $coupon = $holding->coupon;

            if (! $coupon) {
                continue;
            }

            $usedByUser = $coupon->redemptions()
                ->where('user_id', $user->id)
                ->whereIn('status', [CouponRedemption::STATUS_RESERVED, CouponRedemption::STATUS_CONFIRMED])
                ->count();
            $isExhausted = $usedByUser >= (int) $coupon->per_user_limit;

            if ($isExhausted && ! $holding->exhausted_at) {
                $holding->forceFill(['exhausted_at' => now()])->save();

                continue;
            }

            if (! $isExhausted && $allowRestore && $holding->exhausted_at) {
                $activeHolderCount = $coupon->userCoupons()->whereNull('exhausted_at')->count();

                if (((int) $coupon->per_user_limit * ($activeHolderCount + 1)) <= (int) $coupon->usage_limit) {
                    $holding->forceFill(['exhausted_at' => null])->save();
                }
            }
        }
    }

    private function isUserCouponUsable(UserCoupon $userCoupon, User $user): bool
    {
        return $this->baseUnavailableReason($userCoupon, $user) === null;
    }

    private function couponMatchesLine(Coupon $coupon, User $user, int $productId, int $lineTotalCents): bool
    {
        return $this->lineUnavailableReason($coupon, $productId, $lineTotalCents) === null;
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

    private function unavailableReasonForLine(UserCoupon $userCoupon, User $user, int $productId, int $lineTotalCents): ?string
    {
        $baseReason = $this->baseUnavailableReason($userCoupon, $user);

        if ($baseReason !== null) {
            return $baseReason;
        }

        return $this->lineUnavailableReason($userCoupon->coupon, $productId, $lineTotalCents);
    }

    private function unavailableReasonForOrder(UserCoupon $userCoupon, User $user, Collection $cartItems): ?string
    {
        $baseReason = $this->baseUnavailableReason($userCoupon, $user);

        if ($baseReason !== null) {
            return $baseReason;
        }

        $coupon = $userCoupon->coupon;

        if (! $coupon) {
            return '优惠码不存在';
        }

        try {
            $this->assertUsageLimits($coupon);
        } catch (ValidationException) {
            return '优惠码已被使用完';
        }

        $baseCents = $this->discountBaseForOrder($coupon, $cartItems);

        if ($baseCents <= 0) {
            return '不适用于本订单商品';
        }

        if ($baseCents < (int) $coupon->minimum_order_cents) {
            return '未满 '.Money::format((int) $coupon->minimum_order_cents);
        }

        return null;
    }

    private function baseUnavailableReason(UserCoupon $userCoupon, User $user): ?string
    {
        $coupon = $userCoupon->coupon;

        if (! $coupon) {
            return '优惠码不存在';
        }

        if ($userCoupon->user_id !== $user->id) {
            return '不属于当前用户';
        }

        if (! $coupon->is_active) {
            return '优惠码已停用';
        }

        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            return '尚未开始';
        }

        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            return '已过期';
        }

        $usedByUser = $coupon->redemptions()
            ->where('user_id', $user->id)
            ->whereIn('status', [CouponRedemption::STATUS_RESERVED, CouponRedemption::STATUS_CONFIRMED])
            ->count();

        if ($usedByUser >= (int) $coupon->per_user_limit) {
            return '已达到单用户使用次数上限';
        }

        return null;
    }

    private function lineUnavailableReason(?Coupon $coupon, int $productId, int $lineTotalCents): ?string
    {
        if (! $coupon) {
            return '优惠码不存在';
        }

        try {
            $this->assertUsageLimits($coupon);
        } catch (ValidationException) {
            return '优惠码已被使用完';
        }

        if ($lineTotalCents < (int) $coupon->minimum_order_cents) {
            return '未满 '.Money::format((int) $coupon->minimum_order_cents);
        }

        if (! $coupon->appliesToProduct($productId)) {
            return '不适用于该商品';
        }

        return null;
    }

    private function discountBaseForOrder(Coupon $coupon, Collection $cartItems): int
    {
        if (($coupon->scope ?? Coupon::SCOPE_GLOBAL) === Coupon::SCOPE_GLOBAL) {
            return (int) $cartItems->sum('line_total_cents');
        }

        return (int) $cartItems
            ->filter(fn (array $cartItem): bool => $coupon->appliesToProduct((int) $cartItem['product']->id))
            ->sum('line_total_cents');
    }

    /**
     * @param  array<int, array{discount_cents:int,coupon_ids:array<int,int>,coupon_codes:array<int,string>,allocations:array<int,array{user_coupon:?UserCoupon,coupon:Coupon,discount_cents:int}>}>  $itemDiscounts
     */
    private function applyProductCouponToItems(Coupon $coupon, ?UserCoupon $userCoupon, Collection $cartItems, array &$itemDiscounts): void
    {
        $eligibleItems = $cartItems
            ->filter(fn (array $cartItem): bool => $coupon->appliesToProduct((int) $cartItem['product']->id))
            ->map(function (array $cartItem) use ($itemDiscounts): array {
                $variantId = (int) $cartItem['variant']->id;
                $remaining = max(0, (int) $cartItem['line_total_cents'] - (int) ($itemDiscounts[$variantId]['discount_cents'] ?? 0));

                return [
                    'variant_id' => $variantId,
                    'line_total_cents' => $remaining,
                ];
            })
            ->filter(fn (array $cartItem): bool => (int) $cartItem['line_total_cents'] > 0)
            ->values();

        $discount = $coupon->discountFor((int) $eligibleItems->sum('line_total_cents'));

        if ($discount <= 0) {
            return;
        }

        foreach ($this->allocateDiscountAcrossItems($discount, $eligibleItems) as $variantId => $allocatedDiscount) {
            if ($allocatedDiscount <= 0) {
                continue;
            }

            $itemDiscounts[$variantId] ??= [
                'discount_cents' => 0,
                'coupon_ids' => [],
                'coupon_codes' => [],
                'allocations' => [],
            ];
            $itemDiscounts[$variantId]['discount_cents'] += $allocatedDiscount;
            $itemDiscounts[$variantId]['coupon_ids'][] = (int) $coupon->id;
            $itemDiscounts[$variantId]['coupon_codes'][] = (string) $coupon->code;
            $itemDiscounts[$variantId]['allocations'][] = [
                'user_coupon' => $userCoupon,
                'coupon' => $coupon,
                'discount_cents' => $allocatedDiscount,
            ];
        }
    }

    /**
     * @param  Collection<int, array{variant_id:int,line_total_cents:int}>  $items
     * @return array<int, int>
     */
    private function allocateDiscountAcrossItems(int $discountCents, Collection $items): array
    {
        $total = (int) $items->sum('line_total_cents');

        if ($discountCents <= 0 || $total <= 0) {
            return [];
        }

        $remaining = min($discountCents, $total);
        $allocations = [];

        foreach ($items as $item) {
            $variantId = (int) $item['variant_id'];
            $lineTotal = (int) $item['line_total_cents'];
            $allocated = min($lineTotal, (int) floor($discountCents * $lineTotal / $total));
            $allocations[$variantId] = $allocated;
            $remaining -= $allocated;
        }

        while ($remaining > 0) {
            $changed = false;

            foreach ($items as $item) {
                $variantId = (int) $item['variant_id'];
                $capacity = max(0, (int) $item['line_total_cents'] - (int) ($allocations[$variantId] ?? 0));

                if ($capacity <= 0) {
                    continue;
                }

                $add = min($capacity, $remaining);
                $allocations[$variantId] = (int) ($allocations[$variantId] ?? 0) + $add;
                $remaining -= $add;
                $changed = true;

                if ($remaining <= 0) {
                    break;
                }
            }

            if (! $changed) {
                break;
            }
        }

        return $allocations;
    }
}
