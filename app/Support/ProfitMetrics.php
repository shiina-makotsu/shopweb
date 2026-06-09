<?php

namespace App\Support;

use App\Models\CostEntry;
use App\Models\Order;

class ProfitMetrics
{
    /**
     * @return array{sales_cents:int,cost_cents:int,profit_cents:int,completed_orders:int}
     */
    public function summary(): array
    {
        $sales = (int) Order::query()
            ->where('payment_status', Order::PAYMENT_CONFIRMED)
            ->where('status', Order::STATUS_FULFILLED)
            ->sum('total_cents');

        $cost = (int) CostEntry::query()->sum('amount_cents');

        return [
            'sales_cents' => $sales,
            'cost_cents' => $cost,
            'profit_cents' => $sales - $cost,
            'completed_orders' => Order::query()
                ->where('payment_status', Order::PAYMENT_CONFIRMED)
                ->where('status', Order::STATUS_FULFILLED)
                ->count(),
        ];
    }
}
