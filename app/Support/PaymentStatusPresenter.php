<?php

namespace App\Support;

use App\Models\Order;

class PaymentStatusPresenter
{
    /** @return array<string, string> */
    public function options(): array
    {
        return [
            Order::PAYMENT_PENDING => '待付款',
            Order::PAYMENT_SUBMITTED => '已提交付款凭证',
            Order::PAYMENT_CONFIRMED => '已确认付款凭证',
            Order::PAYMENT_REJECTED => '已驳回',
        ];
    }

    public function label(?string $status): string
    {
        return $this->options()[$status ?? ''] ?? ($status ?: '-');
    }

    public function color(?string $status): string
    {
        return match ($status) {
            Order::PAYMENT_CONFIRMED => 'success',
            Order::PAYMENT_SUBMITTED => 'info',
            Order::PAYMENT_REJECTED => 'danger',
            default => 'warning',
        };
    }
}
