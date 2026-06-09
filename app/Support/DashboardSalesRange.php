<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardSalesRange
{
    /**
     * @return Collection<int, array{date: string, label: string, sales_cents: int, order_count: int}>
     */
    public function daily(int $days = 30): Collection
    {
        $days = max(1, $days);
        $startDate = now()->subDays($days - 1)->startOfDay();
        $endDate = now()->endOfDay();

        $orders = Order::query()
            ->where('payment_status', Order::PAYMENT_CONFIRMED)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->get(['paid_at', 'total_cents']);

        $ordersByDate = $orders
            ->groupBy(fn (Order $order): string => $order->paid_at->toDateString())
            ->map(fn (Collection $orders): array => [
                'sales_cents' => (int) $orders->sum('total_cents'),
                'order_count' => $orders->count(),
            ]);

        return collect(range(0, $days - 1))
            ->map(function (int $day) use ($startDate, $ordersByDate): array {
                /** @var Carbon $date */
                $date = $startDate->copy()->addDays($day);
                $dateKey = $date->toDateString();
                $row = $ordersByDate->get($dateKey, [
                    'sales_cents' => 0,
                    'order_count' => 0,
                ]);

                return [
                    'date' => $dateKey,
                    'label' => $date->format('m-d'),
                    'sales_cents' => (int) $row['sales_cents'],
                    'order_count' => (int) $row['order_count'],
                ];
            });
    }

    /**
     * @return array{total_cents: int, order_count: int, average_order_cents: int, best_day_cents: int}
     */
    public function summary(int $days = 30): array
    {
        $daily = $this->daily($days);
        $totalCents = (int) $daily->sum('sales_cents');
        $orderCount = (int) $daily->sum('order_count');

        return [
            'total_cents' => $totalCents,
            'order_count' => $orderCount,
            'average_order_cents' => $orderCount > 0 ? (int) round($totalCents / $orderCount) : 0,
            'best_day_cents' => (int) $daily->max('sales_cents'),
        ];
    }
}
