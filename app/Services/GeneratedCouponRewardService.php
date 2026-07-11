<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\User;
use App\Models\UserCoupon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GeneratedCouponRewardService
{
    /**
     * @param  iterable<int, array<string, mixed>>  $rules
     * @param  callable(array<string, mixed>, int, int, int): string  $nameResolver
     */
    public function issueToUser(
        User $user,
        iterable $rules,
        string $source,
        string $note,
        callable $nameResolver,
        ?User $actor = null,
        string $codePrefix = 'RW',
    ): int {
        return count($this->issueToUserWithDetails(
            $user,
            $rules,
            $source,
            $note,
            $nameResolver,
            $actor,
            $codePrefix,
        ));
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $rules
     * @param  callable(array<string, mixed>, int, int, int): string  $nameResolver
     * @return array<int, Coupon>
     */
    public function issueToUserWithDetails(
        User $user,
        iterable $rules,
        string $source,
        string $note,
        callable $nameResolver,
        ?User $actor = null,
        string $codePrefix = 'RW',
    ): array {
        $issuedCoupons = [];

        foreach ($rules as $ruleIndex => $rule) {
            $quantity = max(1, (int) ($rule['quantity'] ?? 1));

            for ($index = 1; $index <= $quantity; $index++) {
                $issuedCoupons[] = DB::transaction(function () use ($rule, $nameResolver, $ruleIndex, $index, $quantity, $codePrefix, $user, $source, $actor, $note): Coupon {
                    $coupon = $this->createCoupon(
                        $rule,
                        $nameResolver($rule, $ruleIndex + 1, $index, $quantity),
                        $codePrefix,
                    );

                    app(CouponService::class)->issueToUser(
                        $coupon,
                        $user,
                        $source,
                        $actor,
                        null,
                        $note,
                    );

                    return $coupon;
                });
            }
        }

        return $issuedCoupons;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    public function createCoupon(array $rule, string $name, string $codePrefix = 'RW'): Coupon
    {
        $type = ($rule['type'] ?? Coupon::TYPE_FIXED) === Coupon::TYPE_PERCENT ? Coupon::TYPE_PERCENT : Coupon::TYPE_FIXED;
        $scope = ($rule['scope'] ?? Coupon::SCOPE_GLOBAL) === Coupon::SCOPE_PRODUCT ? Coupon::SCOPE_PRODUCT : Coupon::SCOPE_GLOBAL;
        $value = max(1, (int) ($rule['value'] ?? 0));

        if ($type === Coupon::TYPE_PERCENT) {
            $value = min(100, $value);
        }

        $usageLimit = max(1, (int) ($rule['usage_limit'] ?? 1));
        $perUserLimit = max(1, min($usageLimit, (int) ($rule['per_user_limit'] ?? 1)));

        $coupon = Coupon::query()->create([
            'code' => $this->nextCouponCode($codePrefix),
            'name' => $name,
            'type' => $type,
            'value' => $value,
            'scope' => $scope,
            'minimum_order_cents' => max(0, (int) ($rule['minimum_order_cents'] ?? 0)),
            'usage_limit' => $usageLimit,
            'per_user_limit' => $perUserLimit,
            'is_stackable' => (bool) ($rule['is_stackable'] ?? false),
            'starts_at' => now(),
            'ends_at' => (int) ($rule['valid_days'] ?? 0) > 0 ? now()->addDays((int) $rule['valid_days']) : null,
            'is_active' => true,
        ]);

        if ($scope === Coupon::SCOPE_PRODUCT) {
            $productIds = collect($rule['product_ids'] ?? [])
                ->map(fn ($id): int => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();

            $coupon->products()->sync($productIds);
            $coupon->forceFill(['product_id' => $productIds[0] ?? null])->save();
        }

        return $coupon;
    }

    private function nextCouponCode(string $prefix): string
    {
        $prefix = Str::upper(preg_replace('/[^A-Z0-9]+/i', '', $prefix) ?: 'RW');

        do {
            $code = $prefix.now()->format('ymd').Str::upper(Str::random(8));
        } while (Coupon::query()->where('code', $code)->exists());

        return $code;
    }
}
