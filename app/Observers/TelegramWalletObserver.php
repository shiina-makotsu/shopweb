<?php

namespace App\Observers;

use App\Models\WalletTransaction;
use App\Services\TelegramBotService;

class TelegramWalletObserver
{
    public function created(WalletTransaction $transaction): void
    {
        if ($transaction->source !== WalletTransaction::SOURCE_ORDER_REFUND) {
            return;
        }
        app(TelegramBotService::class)->notifyRefund($transaction);
    }
}
