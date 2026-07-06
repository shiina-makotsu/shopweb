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
            Order::PAYMENT_SUBMITTED => '付款凭证已提交',
            Order::PAYMENT_REJECTED => '待支付',
            default => '待支付',
        };
    }

    public function orderLabel(Order $order, ?string $fallback = null): string
    {
        if ($order->status === Order::STATUS_PENDING_PAYMENT && $order->payment_status === Order::PAYMENT_SUBMITTED) {
            return '待发货';
        }

        return $fallback ?? $order->status;
    }
}
