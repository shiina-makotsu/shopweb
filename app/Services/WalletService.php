<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use App\Models\UserCoupon;
use App\Models\WalletRechargeOption;
use App\Models\WalletRedeemCode;
use App\Models\WalletTransaction;
use App\Support\MoneyInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public function redeemCode(User $user, ?string $code): WalletTransaction
    {
        $code = strtoupper(trim((string) $code));

        if ($code === '') {
            throw ValidationException::withMessages(['wallet_code' => '请输入钱包兑换码。']);
        }

        return DB::transaction(function () use ($user, $code): WalletTransaction {
            /** @var WalletRedeemCode|null $redeemCode */
            $redeemCode = WalletRedeemCode::query()
                ->where('code', $code)
                ->lockForUpdate()
                ->first();

            if (! $redeemCode) {
                throw ValidationException::withMessages(['wallet_code' => '钱包兑换码不存在。']);
            }

            if (! $redeemCode->isRedeemable()) {
                throw ValidationException::withMessages(['wallet_code' => '钱包兑换码不可用或已兑换完。']);
            }

            $alreadyRedeemed = WalletTransaction::query()
                ->whereBelongsTo($user)
                ->whereBelongsTo($redeemCode, 'redeemCode')
                ->exists();

            if ($alreadyRedeemed) {
                throw ValidationException::withMessages(['wallet_code' => '你已经兑换过这个钱包兑换码。']);
            }

            $redeemCode->increment('redeemed_count');

            return $this->credit(
                $user,
                (int) $redeemCode->amount_cents,
                WalletTransaction::SOURCE_REDEEM_CODE,
                '兑换码充值：'.$redeemCode->code,
                null,
                $redeemCode,
            );
        });
    }

    public function createRechargeOrder(User $user, mixed $amount): Order
    {
        $amountCents = MoneyInput::toCents($amount);

        if ($amountCents <= 0) {
            throw ValidationException::withMessages(['wallet_recharge_amount' => '钱包充值金额必须大于 0。']);
        }

        return Order::query()->create([
            'user_id' => $user->id,
            'order_number' => $this->nextOrderNumber(),
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_status' => Order::PAYMENT_PENDING,
            'subtotal_cents' => 0,
            'discount_cents' => 0,
            'shipping_fee_cents' => 0,
            'wallet_payment_cents' => 0,
            'wallet_recharge_cents' => $amountCents,
            'is_wallet_recharge' => true,
            'total_cents' => $amountCents,
            'contact_name' => $user->displayName(),
            'contact_phone' => (string) ($user->phone ?? $user->public_id ?? $user->id),
            'contact_email' => $user->email,
            'requires_shipping' => false,
            'customer_note' => '钱包充值',
        ]);
    }

    public function createRechargeOptionOrder(User $user, WalletRechargeOption $option): Order
    {
        if (! $option->is_active || $option->payableCents() <= 0 || $option->creditCents() <= 0) {
            throw ValidationException::withMessages(['wallet_recharge_option_id' => '该充值选项不可用。']);
        }

        return Order::query()->create([
            'user_id' => $user->id,
            'order_number' => $this->nextOrderNumber(),
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_status' => Order::PAYMENT_PENDING,
            'subtotal_cents' => 0,
            'discount_cents' => max(0, (int) $option->amount_cents - $option->payableCents()),
            'shipping_fee_cents' => 0,
            'wallet_payment_cents' => 0,
            'wallet_recharge_cents' => $option->creditCents(),
            'is_wallet_recharge' => true,
            'wallet_recharge_option_id' => $option->id,
            'total_cents' => $option->payableCents(),
            'contact_name' => $user->displayName(),
            'contact_phone' => (string) ($user->phone ?? $user->public_id ?? $user->id),
            'contact_email' => $user->email,
            'requires_shipping' => false,
            'customer_note' => '钱包充值选项：'.$option->displayName(),
        ]);
    }

    public function applyAvailableBalanceToOrder(User $user, Order $order, int $payableCents, ?User $actor = null): ?WalletTransaction
    {
        if ($payableCents <= 0) {
            return null;
        }

        return DB::transaction(function () use ($user, $order, $payableCents, $actor): ?WalletTransaction {
            /** @var User $lockedUser */
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $amount = min((int) $lockedUser->wallet_balance_cents, $payableCents);

            if ($amount <= 0) {
                return null;
            }

            return $this->debit(
                $lockedUser,
                $amount,
                WalletTransaction::SOURCE_ORDER_PAYMENT,
                '订单支付：'.$order->order_number,
                $order,
                $actor,
            );
        });
    }

    public function applyAvailableBalanceAndUpdateOrder(User $user, Order $order, int $payableCents, ?User $actor = null): ?WalletTransaction
    {
        $walletPayment = $this->applyAvailableBalanceToOrder($user, $order, $payableCents, $actor);

        if (! $walletPayment) {
            return null;
        }

        $walletPaymentCents = abs((int) $walletPayment->amount_cents);
        $order->forceFill([
            'wallet_payment_cents' => (int) $order->wallet_payment_cents + $walletPaymentCents,
            'total_cents' => max(0, (int) $order->total_cents - $walletPaymentCents),
        ])->save();

        return $walletPayment;
    }

    public function refundOrderPayment(Order $order, int $refundAmountCents, ?User $actor = null, ?string $note = null): ?WalletTransaction
    {
        $walletPaidCents = (int) $order->wallet_payment_cents;

        if ($walletPaidCents <= 0 || $refundAmountCents <= 0) {
            return null;
        }

        $alreadyRefunded = (int) WalletTransaction::query()
            ->where('order_id', $order->id)
            ->where('source', WalletTransaction::SOURCE_ORDER_REFUND)
            ->sum('amount_cents');
        $refundableCents = max(0, $walletPaidCents - $alreadyRefunded);
        $amountCents = min($refundableCents, $refundAmountCents);

        if ($amountCents <= 0) {
            return null;
        }

        $order->loadMissing('user');

        return $this->credit(
            $order->user,
            $amountCents,
            WalletTransaction::SOURCE_ORDER_REFUND,
            $note ?: '订单退款退回钱包：'.$order->order_number,
            $actor,
            null,
            $order,
        );
    }

    public function creditForRechargeOrder(Order $order, ?User $actor = null): ?WalletTransaction
    {
        if (! $order->isWalletRecharge() || (int) $order->wallet_recharge_cents <= 0) {
            return null;
        }

        $order->loadMissing('user', 'walletRechargeOption');

        $alreadyCredited = WalletTransaction::query()
            ->where('order_id', $order->id)
            ->where('source', WalletTransaction::SOURCE_WALLET_RECHARGE)
            ->exists();

        if ($alreadyCredited) {
            return null;
        }

        $transaction = $this->credit(
            $order->user,
            (int) $order->wallet_recharge_cents,
            WalletTransaction::SOURCE_WALLET_RECHARGE,
            '钱包充值订单：'.$order->order_number,
            $actor,
            null,
            $order,
        );

        $this->issueRechargeCoupons($order, $actor);

        return $transaction;
    }

    public function issueRechargeCoupons(Order $order, ?User $actor = null): int
    {
        $order->loadMissing('user', 'walletRechargeOption');

        $option = $order->walletRechargeOption;

        if (! $order->user || ! $option?->couponRewardEnabled()) {
            return 0;
        }

        $alreadyIssued = $order->user->coupons()
            ->where('source', UserCoupon::SOURCE_WALLET_RECHARGE)
            ->where('note', '钱包充值赠券：'.$order->order_number)
            ->exists();

        if ($alreadyIssued) {
            return 0;
        }

        $issued = 0;

        foreach ($option->couponRewardRules() as $ruleIndex => $rule) {
            $quantity = max(1, (int) $rule['quantity']);

            for ($index = 1; $index <= $quantity; $index++) {
                $coupon = $this->createRechargeRewardCoupon($option, $order, $rule, $ruleIndex + 1, $index, $quantity);

                app(CouponService::class)->issueToUser(
                    $coupon,
                    $order->user,
                    UserCoupon::SOURCE_WALLET_RECHARGE,
                    $actor,
                    null,
                    '钱包充值赠券：'.$order->order_number,
                );

                $issued++;
            }
        }

        return $issued;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function createRechargeRewardCoupon(WalletRechargeOption $option, Order $order, array $rule, int $ruleNumber, int $index, int $quantity): Coupon
    {
        $type = ($rule['type'] ?? Coupon::TYPE_FIXED) === Coupon::TYPE_PERCENT ? Coupon::TYPE_PERCENT : Coupon::TYPE_FIXED;
        $scope = ($rule['scope'] ?? Coupon::SCOPE_GLOBAL) === Coupon::SCOPE_PRODUCT ? Coupon::SCOPE_PRODUCT : Coupon::SCOPE_GLOBAL;
        $value = max(1, (int) ($rule['value'] ?? 0));

        if ($type === Coupon::TYPE_PERCENT) {
            $value = min(100, $value);
        }

        $coupon = Coupon::query()->create([
            'code' => $this->nextCouponCode(),
            'name' => $this->rechargeRewardCouponName($option, $order, $rule, $ruleNumber, $index, $quantity),
            'type' => $type,
            'value' => $value,
            'scope' => $scope,
            'minimum_order_cents' => max(0, (int) ($rule['minimum_order_cents'] ?? 0)),
            'usage_limit' => max(1, (int) ($rule['usage_limit'] ?? 1)),
            'per_user_limit' => 1,
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

    /**
     * @param  array<string, mixed>  $rule
     */
    private function rechargeRewardCouponName(WalletRechargeOption $option, Order $order, array $rule, int $ruleNumber, int $index, int $quantity): string
    {
        $suffix = $quantity > 1 ? " {$index}/{$quantity}" : '';
        $ruleLabel = $ruleNumber > 1 ? " 规则{$ruleNumber}" : '';
        $name = trim((string) ($rule['name'] ?? ''));
        $name = $name !== '' ? ' '.$name : '';

        return trim(($option->name ?: $option->displayName()).' 充值赠券'.$ruleLabel.$name.$suffix.' '.$order->order_number);
    }

    public function credit(
        User $user,
        int $amountCents,
        string $source,
        ?string $note = null,
        ?User $actor = null,
        ?WalletRedeemCode $redeemCode = null,
        ?Order $order = null,
    ): WalletTransaction {
        if ($amountCents <= 0) {
            throw ValidationException::withMessages(['amount' => '钱包金额必须大于 0。']);
        }

        return DB::transaction(function () use ($user, $amountCents, $source, $note, $actor, $redeemCode, $order): WalletTransaction {
            /** @var User $lockedUser */
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $balance = (int) $lockedUser->wallet_balance_cents + $amountCents;

            $lockedUser->forceFill(['wallet_balance_cents' => $balance])->save();

            return WalletTransaction::query()->create([
                'user_id' => $lockedUser->id,
                'order_id' => $order?->id,
                'wallet_redeem_code_id' => $redeemCode?->id,
                'created_by_user_id' => $actor?->id,
                'type' => WalletTransaction::TYPE_CREDIT,
                'amount_cents' => $amountCents,
                'balance_after_cents' => $balance,
                'source' => $source,
                'note' => $note,
            ]);
        });
    }

    public function debit(User $user, int $amountCents, string $source, ?string $note = null, ?Order $order = null, ?User $actor = null): WalletTransaction
    {
        if ($amountCents <= 0) {
            throw ValidationException::withMessages(['amount' => '钱包金额必须大于 0。']);
        }

        return DB::transaction(function () use ($user, $amountCents, $source, $note, $order, $actor): WalletTransaction {
            /** @var User $lockedUser */
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $current = (int) $lockedUser->wallet_balance_cents;

            if ($current < $amountCents) {
                throw ValidationException::withMessages(['wallet' => '钱包余额不足。']);
            }

            $balance = $current - $amountCents;
            $lockedUser->forceFill(['wallet_balance_cents' => $balance])->save();

            return WalletTransaction::query()->create([
                'user_id' => $lockedUser->id,
                'order_id' => $order?->id,
                'created_by_user_id' => $actor?->id,
                'type' => WalletTransaction::TYPE_DEBIT,
                'amount_cents' => -$amountCents,
                'balance_after_cents' => $balance,
                'source' => $source,
                'note' => $note,
            ]);
        });
    }

    private function nextOrderNumber(): string
    {
        do {
            $number = 'SW'.now()->format('YmdHis').str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }

    private function nextCouponCode(): string
    {
        do {
            $code = 'WR'.now()->format('ymd').Str::upper(Str::random(8));
        } while (Coupon::query()->where('code', $code)->exists());

        return $code;
    }
}
