<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Throwable;

class UserOrderSummary
{
    /**
     * @return array{pending_payment:int,pending_shipment:int,awaiting_receipt:int,fulfilled:int,notice:int}
     */
    public function forUser(?User $user): array
    {
        if (! $user || ! $this->ordersTableExists()) {
            return $this->empty();
        }

        try {
            $orderCounts = Order::query()
                ->whereBelongsTo($user)
                ->whereNull('user_deleted_at')
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $pendingPayment = $this->pendingPaymentCount($user);
            $awaitingReceipt = (int) ($orderCounts[Order::STATUS_AWAITING_RECEIPT] ?? 0);

            return [
                'pending_payment' => $pendingPayment,
                'pending_shipment' => (int) ($orderCounts[Order::STATUS_PENDING_SHIPMENT] ?? 0) + (int) ($orderCounts[Order::STATUS_INCOMING] ?? 0),
                'awaiting_receipt' => (int) ($orderCounts[Order::STATUS_SHIPPED] ?? 0) + $awaitingReceipt,
                'fulfilled' => (int) ($orderCounts[Order::STATUS_FULFILLED] ?? 0),
                'notice' => $pendingPayment + $awaitingReceipt,
            ];
        } catch (Throwable) {
            return $this->empty();
        }
    }

    public function pendingPaymentCount(User $user): int
    {
        if (! $this->ordersTableExists()) {
            return 0;
        }

        try {
            return Order::query()
                ->whereBelongsTo($user)
                ->whereNull('user_deleted_at')
                ->where('status', Order::STATUS_PENDING_PAYMENT)
                ->whereIn('payment_status', [Order::PAYMENT_PENDING, Order::PAYMENT_REJECTED])
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @return array{pending_payment:int,pending_shipment:int,awaiting_receipt:int,fulfilled:int,notice:int}
     */
    private function empty(): array
    {
        return [
            'pending_payment' => 0,
            'pending_shipment' => 0,
            'awaiting_receipt' => 0,
            'fulfilled' => 0,
            'notice' => 0,
        ];
    }

    private function ordersTableExists(): bool
    {
        try {
            return Schema::hasTable('orders');
        } catch (Throwable) {
            return false;
        }
    }
}
