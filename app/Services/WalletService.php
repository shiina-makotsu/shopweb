<?php

namespace App\Services;

use App\Models\AfterSalesRequest;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\UserCoupon;
use App\Models\WalletRechargeOption;
use App\Models\WalletRedeemCode;
use App\Models\WalletTransaction;
use App\Support\AdminAccess;
use App\Support\Money;
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
        return DB::transaction(function () use ($order, $refundAmountCents, $actor, $note): ?WalletTransaction {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $amountCents = min(
                max(0, (int) $order->wallet_payment_cents - $this->refundedOrderCents($order, true)),
                $refundAmountCents,
            );

            if ($amountCents <= 0) {
                return null;
            }

            return $this->credit(
                $order->user,
                $amountCents,
                WalletTransaction::SOURCE_ORDER_REFUND,
                $note ?: '订单退款退回钱包：'.$order->order_number,
                $actor,
                null,
                $order,
            );
        });
    }

    public function refundableOrderCents(Order $order, bool $lock = false): int
    {
        $paidCents = (int) $order->wallet_payment_cents;

        if ($order->payment_status === Order::PAYMENT_CONFIRMED) {
            $paidCents += (int) $order->total_cents;
        }

        return max(0, $paidCents - $this->refundedOrderCents($order, $lock));
    }

    public function refundAfterSalesRequest(AfterSalesRequest $request, User $actor): WalletTransaction
    {
        abort_unless(AdminAccess::canAction('after_sales.refund', $actor), 403);

        return DB::transaction(function () use ($request, $actor): WalletTransaction {
            $request = AfterSalesRequest::query()->lockForUpdate()->findOrFail($request->id);
            $order = Order::query()->lockForUpdate()->findOrFail($request->order_id);
            $amountCents = (int) $request->refund_amount_cents;

            if ((int) $request->user_id !== (int) $order->user_id
                || $request->refund_status !== AfterSalesRequest::REFUND_APPROVED
                || $request->resolution_type !== AfterSalesRequest::RESOLUTION_REFUND) {
                throw ValidationException::withMessages(['order_id' => '退款审批与订单用户不匹配。']);
            }

            $existing = WalletTransaction::query()->where('after_sales_request_id', $request->id)->lockForUpdate()->first();

            if ($existing) {
                if ((int) $existing->order_id !== (int) $order->id
                    || (int) $existing->user_id !== (int) $request->user_id
                    || (int) $existing->amount_cents !== $amountCents) {
                    throw ValidationException::withMessages(['refund_amount_cents' => '已入账退款不可修改。']);
                }

                return $existing;
            }

            $this->validateOrderRefund($order, $amountCents);

            $transaction = $this->credit(
                $order->user,
                $amountCents,
                WalletTransaction::SOURCE_ORDER_REFUND,
                '售后退款到钱包：'.$order->order_number.PHP_EOL.$request->admin_note,
                $actor,
                null,
                $order,
                $request,
                'after-sales:'.$request->id,
            );

            return $transaction;
        });
    }

    public function validateOrderRefund(Order $order, int $amountCents): void
    {
        if ($order->payment_status !== Order::PAYMENT_CONFIRMED || $order->isWalletRecharge()) {
            throw ValidationException::withMessages(['order_id' => '仅已确认付款的商品订单可以退款到钱包。']);
        }

        if ($amountCents <= 0 || $amountCents > $this->refundableOrderCents($order, true)) {
            throw ValidationException::withMessages(['refund_amount_cents' => '退款金额必须大于 0 且不超过订单剩余可退款金额。']);
        }
    }

    public function adjustBalance(User $user, int $amountCents, string $type, User $actor, string $reason, string $idempotencyKey): WalletTransaction
    {
        abort_unless(AdminAccess::canAction('wallet.adjust', $actor), 403);
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 3000) {
            throw ValidationException::withMessages(['reason' => '请输入调整原因（不超过 3000 字）。']);
        }

        if ($amountCents <= 0 || $amountCents > 2147483647
            || ! in_array($type, [WalletTransaction::TYPE_CREDIT, WalletTransaction::TYPE_DEBIT], true)) {
            throw ValidationException::withMessages(['amount_cents' => '请选择有效操作并输入大于 0 的金额。']);
        }

        if (! Str::isUuid($idempotencyKey)) {
            throw ValidationException::withMessages(['idempotency_key' => '无效的钱包操作编号。']);
        }

        return DB::transaction(function () use ($user, $amountCents, $type, $actor, $reason, $idempotencyKey): WalletTransaction {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $existing = WalletTransaction::query()->where('idempotency_key', 'admin:'.$idempotencyKey)->lockForUpdate()->first();

            if ($existing) {
                if ((int) $existing->user_id !== (int) $user->id || $existing->type !== $type
                    || abs((int) $existing->amount_cents) !== $amountCents
                    || (int) $existing->created_by_user_id !== (int) $actor->id || $existing->note !== $reason) {
                    throw ValidationException::withMessages(['idempotency_key' => '操作编号已用于其他钱包调整。']);
                }

                return $existing;
            }

            if ($type === WalletTransaction::TYPE_DEBIT && (int) $user->wallet_balance_cents < $amountCents) {
                throw ValidationException::withMessages(['amount_cents' => '钱包余额不足，不能透支。']);
            }

            $transaction = $type === WalletTransaction::TYPE_CREDIT
                ? $this->credit($user, $amountCents, WalletTransaction::SOURCE_ADMIN, $reason, $actor, idempotencyKey: 'admin:'.$idempotencyKey)
                : $this->debit($user, $amountCents, WalletTransaction::SOURCE_ADMIN, $reason, null, $actor, 'admin:'.$idempotencyKey);

            app(AdminActivityLogger::class)->log(
                $type === WalletTransaction::TYPE_CREDIT ? 'wallet_balance_credited' : 'wallet_balance_debited',
                $transaction,
                sprintf('后台%s钱包余额：用户 %s，金额 %s，调整后余额 %s。原因：%s',
                    $type === WalletTransaction::TYPE_CREDIT ? '增加' : '扣减',
                    $user->displayName(), Money::format($amountCents), Money::format((int) $transaction->balance_after_cents), $reason),
                [
                    'customer_user_id' => $user->id,
                    'wallet_transaction_id' => $transaction->id,
                    'amount_cents' => (int) $transaction->amount_cents,
                    'balance_before_cents' => (int) $transaction->balance_after_cents - (int) $transaction->amount_cents,
                    'balance_after_cents' => (int) $transaction->balance_after_cents,
                    'reason' => $reason,
                ],
                $actor,
            );

            return $transaction;
        });
    }

    private function refundedOrderCents(Order $order, bool $lock = false): int
    {
        $query = WalletTransaction::query()
            ->where('order_id', $order->id)
            ->where('source', WalletTransaction::SOURCE_ORDER_REFUND);

        // Current reads avoid stale refund totals under MySQL repeatable-read transactions.
        return (int) ($lock
            ? $query->lockForUpdate()->get(['amount_cents'])->sum('amount_cents')
            : $query->sum('amount_cents'));
    }

    public function creditForRechargeOrder(Order $order, ?User $actor = null): ?WalletTransaction
    {
        if (! $order->isWalletRecharge() || (int) $order->wallet_recharge_cents <= 0) {
            return null;
        }

        $order->loadMissing('user', 'walletRechargeOption');

        $netCreditedCents = (int) WalletTransaction::query()
            ->where('order_id', $order->id)
            ->whereIn('source', [
                WalletTransaction::SOURCE_WALLET_RECHARGE,
                WalletTransaction::SOURCE_WALLET_RECHARGE_REVERSAL,
            ])
            ->sum('amount_cents');

        if ($netCreditedCents > 0) {
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
            ->whereNull('exhausted_at')
            ->whereHas('coupon', fn ($query) => $query->where('is_active', true))
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

    /** @return array{wallet_reversed_cents:int,coupon_count:int} */
    public function reverseRechargeBenefits(Order $order, ?User $actor = null): array
    {
        if (! $order->isWalletRecharge()) {
            return ['wallet_reversed_cents' => 0, 'coupon_count' => 0];
        }

        return DB::transaction(function () use ($order, $actor): array {
            $order->loadMissing('user');

            if (! $order->user) {
                return ['wallet_reversed_cents' => 0, 'coupon_count' => 0];
            }

            $creditedCents = (int) WalletTransaction::query()
                ->where('order_id', $order->id)
                ->where('source', WalletTransaction::SOURCE_WALLET_RECHARGE)
                ->sum('amount_cents');
            $reversedCents = abs((int) WalletTransaction::query()
                ->where('order_id', $order->id)
                ->where('source', WalletTransaction::SOURCE_WALLET_RECHARGE_REVERSAL)
                ->sum('amount_cents'));
            $amountToReverse = max(0, $creditedCents - $reversedCents);

            if ($amountToReverse > 0) {
                /** @var User $lockedUser */
                $lockedUser = User::query()->whereKey($order->user_id)->lockForUpdate()->firstOrFail();
                $balance = (int) $lockedUser->wallet_balance_cents - $amountToReverse;

                $lockedUser->forceFill(['wallet_balance_cents' => $balance])->save();

                WalletTransaction::query()->create([
                    'user_id' => $lockedUser->id,
                    'order_id' => $order->id,
                    'created_by_user_id' => $actor?->id,
                    'type' => WalletTransaction::TYPE_DEBIT,
                    'amount_cents' => -$amountToReverse,
                    'balance_after_cents' => $balance,
                    'source' => WalletTransaction::SOURCE_WALLET_RECHARGE_REVERSAL,
                    'note' => '自动确认充值驳回追回：'.$order->order_number,
                ]);
            }

            $note = '钱包充值赠券：'.$order->order_number;
            $holdings = UserCoupon::query()
                ->where('user_id', $order->user_id)
                ->where('source', UserCoupon::SOURCE_WALLET_RECHARGE)
                ->where('note', $note)
                ->lockForUpdate()
                ->get();
            $couponIds = $holdings->pluck('coupon_id')->filter()->unique()->values();

            if ($holdings->isNotEmpty()) {
                UserCoupon::query()
                    ->whereKey($holdings->modelKeys())
                    ->update(['exhausted_at' => now()]);
            }

            if ($couponIds->isNotEmpty()) {
                Coupon::query()->whereKey($couponIds->all())->update(['is_active' => false]);
            }

            return [
                'wallet_reversed_cents' => $amountToReverse,
                'coupon_count' => $holdings->count(),
            ];
        });
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
        ?AfterSalesRequest $afterSalesRequest = null,
        ?string $idempotencyKey = null,
    ): WalletTransaction {
        if ($amountCents <= 0) {
            throw ValidationException::withMessages(['amount' => '钱包金额必须大于 0。']);
        }

        return DB::transaction(function () use ($user, $amountCents, $source, $note, $actor, $redeemCode, $order, $afterSalesRequest, $idempotencyKey): WalletTransaction {
            /** @var User $lockedUser */
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $balance = (int) $lockedUser->wallet_balance_cents + $amountCents;

            if ($balance > 2147483647) {
                throw ValidationException::withMessages(['amount_cents' => '钱包余额超过允许上限。']);
            }

            $lockedUser->forceFill(['wallet_balance_cents' => $balance])->save();

            return WalletTransaction::query()->create([
                'user_id' => $lockedUser->id,
                'order_id' => $order?->id,
                'wallet_redeem_code_id' => $redeemCode?->id,
                'created_by_user_id' => $actor?->id,
                'after_sales_request_id' => $afterSalesRequest?->id,
                'idempotency_key' => $idempotencyKey,
                'type' => WalletTransaction::TYPE_CREDIT,
                'amount_cents' => $amountCents,
                'balance_after_cents' => $balance,
                'source' => $source,
                'note' => $note,
            ]);
        });
    }

    public function debit(User $user, int $amountCents, string $source, ?string $note = null, ?Order $order = null, ?User $actor = null, ?string $idempotencyKey = null): WalletTransaction
    {
        if ($amountCents <= 0) {
            throw ValidationException::withMessages(['amount' => '钱包金额必须大于 0。']);
        }

        return DB::transaction(function () use ($user, $amountCents, $source, $note, $order, $actor, $idempotencyKey): WalletTransaction {
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
                'idempotency_key' => $idempotencyKey,
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
