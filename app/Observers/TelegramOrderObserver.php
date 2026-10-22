<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\TelegramBotService;

class TelegramOrderObserver
{
    public function created(Order $order): void
    {
        app(TelegramBotService::class)->notifyOrder($order, '新订单');
    }

    public function updated(Order $order): void
    {
        if ($order->wasChanged('status') && $order->status === Order::STATUS_CANCELLED) {
            app(TelegramBotService::class)->notifyOrder($order, '订单已取消');
        }
    }
}
