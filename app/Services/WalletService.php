<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
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

    public function creditForRechargeOrder(Order $order, ?User $actor = null): ?WalletTransaction
    {
        if (! $order->isWalletRecharge() || (int) $order->wallet_recharge_cents <= 0) {
            return null;
        }

        $order->loadMissing('user');

        $alreadyCredited = WalletTransaction::query()
            ->where('order_id', $order->id)
            ->where('source', WalletTransaction::SOURCE_WALLET_RECHARGE)
            ->exists();

        if ($alreadyCredited) {
            return null;
        }

        return $this->credit(
            $order->user,
            (int) $order->wallet_recharge_cents,
            WalletTransaction::SOURCE_WALLET_RECHARGE,
            '钱包充值订单：'.$order->order_number,
            $actor,
            null,
            $order,
        );
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
}
