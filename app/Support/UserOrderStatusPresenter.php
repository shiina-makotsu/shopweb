<?php

namespace App\Support;

use App\Models\Order;

class UserOrderStatusPresenter
{
    public function paymentLabel(Order $order): string
    {
        if ($order->payment_status === Order::PAYMENT_CONFIRMED) {
            return '已付款';
        }

        return match ($order->payment_status) {
            Order::PAYMENT_SUBMITTED => '待确认收款',
            Order::PAYMENT_REJECTED => '待支付',
            default => '待支付',
        };
    }

    public function orderLabel(Order $order, ?string $fallback = null): string
    {
        if ($order->status === Order::STATUS_PENDING_PAYMENT && $order->payment_status === Order::PAYMENT_SUBMITTED) {
            return '待确认收款';
        }

        return $fallback ?? $order->status;
    }
}
