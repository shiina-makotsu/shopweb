<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderPaymentTimeoutNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Order $order,
        private readonly int $timeoutMinutes,
        private readonly int $walletRefundedCents,
        private readonly int $couponCount,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, int|string> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'order_payment_timeout',
            'order_id' => (int) $this->order->id,
            'order_number' => (string) $this->order->order_number,
            'timeout_minutes' => $this->timeoutMinutes,
            'wallet_refunded_cents' => $this->walletRefundedCents,
            'coupon_count' => $this->couponCount,
            'title' => '待付款订单已超时关闭',
            'message' => "订单 {$this->order->order_number} 因超过 {$this->timeoutMinutes} 分钟未提交付款凭证，已自动关闭。",
        ];
    }
}
