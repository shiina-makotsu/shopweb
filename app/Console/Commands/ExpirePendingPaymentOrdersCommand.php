<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use App\Services\OrderService;
use Illuminate\Console\Command;

class ExpirePendingPaymentOrdersCommand extends Command
{
    protected $signature = 'shop:orders-expire-pending-payments';

    protected $description = 'Close pending payment orders that did not receive a payment proof in time.';

    public function handle(OrderService $orders): int
    {
        $settings = SiteSetting::query()->first();
        $timeoutMinutes = max(1, (int) ($settings?->payment_pending_timeout_minutes ?: 10));
        $expired = $orders->expireUnsubmittedPaymentOrders($timeoutMinutes);

        $this->info(sprintf(
            'Closed %d pending payment orders without proof after %d minutes.',
            $expired,
            $timeoutMinutes,
        ));

        return self::SUCCESS;
    }
}
