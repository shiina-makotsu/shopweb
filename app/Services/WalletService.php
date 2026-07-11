<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\UserCoupon;
use App\Models\WalletRechargeOption;
use App\Models\WalletRedeemCode;
use App\Models\WalletTransaction;
use App\Support\MoneyInput;
use Illuminate\Support\Facades\DB;
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

        $matchedOption = $this->matchingRechargeOptionForPayableAmount($amountCents);

        if ($matchedOption) {
            return $this->createRechargeOptionOrder($user, $matchedOption);
        }

        return $this->createRechargeOrderSnapshot(
            $user,
            null,
            $this->customRechargeProductTitle($amountCents),
            $amountCents,
            $amountCents,
            0,
            '钱包充值',
        );
    }

    public function createRechargeOptionOrder(User $user, WalletRechargeOption $option): Order
    {
        if (! $option->is_active || $option->payableCents() <= 0 || $option->creditCents() <= 0) {
            throw ValidationException::withMessages(['wallet_recharge_option_id' => '该充值选项不可用。']);
        }

        return $this->createRechargeOrderSnapshot(
            $user,
            $option,
            $option->displayName(),
            $option->payableCents(),
            $option->creditCents(),
            max(0, (int) $option->amount_cents - $option->payableCents()),
            '钱包充值选项：'.$option->displayName(),
        );
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

        $note = '钱包充值赠券：'.$order->order_number;
        $alreadyIssued = $order->user->coupons()
            ->where('source', UserCoupon::SOURCE_WALLET_RECHARGE)
            ->where('note', $note)
            ->exists();

        if ($alreadyIssued) {
            return 0;
        }

        return app(GeneratedCouponRewardService::class)->issueToUser(
            $order->user,
            $option->couponRewardRules(),
            UserCoupon::SOURCE_WALLET_RECHARGE,
            $note,
            fn (array $rule, int $ruleNumber, int $index, int $quantity): string => $this->rechargeRewardCouponName($option, $order, $rule, $ruleNumber, $index, $quantity),
            $actor,
            'WR',
        );
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

    private function createRechargeOrderSnapshot(
        User $user,
        ?WalletRechargeOption $option,
        string $productTitle,
        int $payableCents,
        int $creditCents,
        int $discountCents,
        string $customerNote,
    ): Order {
        return DB::transaction(function () use ($user, $option, $productTitle, $payableCents, $creditCents, $discountCents, $customerNote): Order {
            $order = Order::query()->create([
                'user_id' => $user->id,
                'order_number' => $this->nextOrderNumber(),
                'status' => Order::STATUS_PENDING_PAYMENT,
                'payment_status' => Order::PAYMENT_PENDING,
                'subtotal_cents' => $payableCents,
                'discount_cents' => $discountCents,
                'shipping_fee_cents' => 0,
                'wallet_payment_cents' => 0,
                'wallet_recharge_cents' => $creditCents,
                'is_wallet_recharge' => true,
                'wallet_recharge_option_id' => $option?->id,
                'total_cents' => $payableCents,
                'contact_name' => $user->displayName(),
                'contact_phone' => (string) ($user->phone ?? $user->public_id ?? $user->id),
                'contact_email' => $user->email,
                'requires_shipping' => false,
                'customer_note' => $customerNote,
            ]);

            OrderItem::query()->create([
                'order_id' => $order->id,
                'product_title' => $productTitle,
                'product_status' => 'wallet_recharge',
                'variant_sku' => $option ? 'WALLET-RECHARGE-'.$option->id : 'WALLET-RECHARGE-CUSTOM',
                'variant_specs' => [],
                'unit_price_cents' => $payableCents,
                'quantity' => 1,
                'line_total_cents' => $payableCents,
                'discount_cents' => $discountCents,
            ]);

            return $order;
        });
    }

    private function customRechargeProductTitle(int $amountCents): string
    {
        return '钱包充值'.rtrim(rtrim(number_format($amountCents / 100, 2, '.', ''), '0'), '.').'元';
    }

    private function nextOrderNumber(): string
    {
        do {
            $number = 'SW'.now()->format('YmdHis').str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }

    private function matchingRechargeOptionForPayableAmount(int $amountCents): ?WalletRechargeOption
    {
        return WalletRechargeOption::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('amount_cents')
            ->get()
            ->first(fn (WalletRechargeOption $option): bool => $option->payableCents() === $amountCents);
    }

}
