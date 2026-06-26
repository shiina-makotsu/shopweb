<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardSalesRange
{
    /**
     * @return Collection<int, array{date: string, label: string, sales_cents: int, paid_cents: int, order_count: int, created_order_count: int, completed_order_count: int, paid_order_count: int}>
     */
    public function daily(int $days = 30): Collection
    {
        $days = max(1, $days);
        $startDate = now()->subDays($days - 1)->startOfDay();
        $endDate = now()->endOfDay();

        $completedOrders = Order::query()
            ->where('payment_status', Order::PAYMENT_CONFIRMED)
            ->where('status', Order::STATUS_FULFILLED)
            ->where(function ($query) use ($startDate, $endDate): void {
                $query
                    ->whereBetween('fulfilled_at', [$startDate, $endDate])
                    ->orWhere(function ($query) use ($startDate, $endDate): void {
                        $query
                            ->whereNull('fulfilled_at')
                            ->whereBetween('paid_at', [$startDate, $endDate]);
                    });
            })
            ->get(['fulfilled_at', 'paid_at', 'total_cents']);

        $completedByDate = $completedOrders
            ->groupBy(fn (Order $order): string => ($order->fulfilled_at ?? $order->paid_at)->toDateString())
            ->map(fn (Collection $orders): array => [
                'sales_cents' => (int) $orders->sum('total_cents'),
                'completed_order_count' => $orders->count(),
            ]);

        $createdByDate = Order::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get(['created_at'])
            ->groupBy(fn (Order $order): string => $order->created_at->toDateString())
            ->map(fn (Collection $orders): int => $orders->count());

        $paidByDate = Order::query()
            ->where('payment_status', Order::PAYMENT_CONFIRMED)
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->get(['paid_at', 'total_cents'])
            ->groupBy(fn (Order $order): string => $order->paid_at->toDateString())
            ->map(fn (Collection $orders): array => [
                'paid_cents' => (int) $orders->sum('total_cents'),
                'paid_order_count' => $orders->count(),
            ]);

        return collect(range(0, $days - 1))
            ->map(function (int $day) use ($startDate, $completedByDate, $createdByDate, $paidByDate): array {
                /** @var Carbon $date */
                $date = $startDate->copy()->addDays($day);
                $dateKey = $date->toDateString();
                $completed = $completedByDate->get($dateKey, [
                    'sales_cents' => 0,
                    'completed_order_count' => 0,
                ]);
                $paid = $paidByDate->get($dateKey, [
                    'paid_cents' => 0,
                    'paid_order_count' => 0,
                ]);

                return [
                    'date' => $dateKey,
                    'label' => $date->format('m-d'),
                    'sales_cents' => (int) $completed['sales_cents'],
                    'paid_cents' => (int) $paid['paid_cents'],
                    'order_count' => (int) $completed['completed_order_count'],
                    'created_order_count' => (int) ($createdByDate->get($dateKey) ?? 0),
                    'completed_order_count' => (int) $completed['completed_order_count'],
                    'paid_order_count' => (int) $paid['paid_order_count'],
                ];
            });
    }

    /**
     * @return Collection<int, array{time: string, label: string, sales_cents: int, paid_cents: int, order_count: int, created_order_count: int, completed_order_count: int, paid_order_count: int}>
     */
    public function hourlyMinutes(int $hours = 24): Collection
    {
        $hours = max(1, $hours);
        $start = now()->subHours($hours)->startOfMinute();
        $end = now()->endOfMinute();

        $completedByMinute = Order::query()
            ->where('payment_status', Order::PAYMENT_CONFIRMED)
            ->where('status', Order::STATUS_FULFILLED)
            ->where(function ($query) use ($start, $end): void {
                $query
                    ->whereBetween('fulfilled_at', [$start, $end])
                    ->orWhere(function ($query) use ($start, $end): void {
                        $query
                            ->whereNull('fulfilled_at')
                            ->whereBetween('paid_at', [$start, $end]);
                    });
            })
            ->get(['fulfilled_at', 'paid_at', 'total_cents'])
            ->groupBy(fn (Order $order): string => ($order->fulfilled_at ?? $order->paid_at)->format('Y-m-d H:i'))
            ->map(fn (Collection $orders): array => [
                'sales_cents' => (int) $orders->sum('total_cents'),
                'completed_order_count' => $orders->count(),
            ]);

        $createdByMinute = Order::query()
            ->whereBetween('created_at', [$start, $end])
            ->get(['created_at'])
            ->groupBy(fn (Order $order): string => $order->created_at->format('Y-m-d H:i'))
            ->map(fn (Collection $orders): int => $orders->count());

        $paidByMinute = Order::query()
            ->where('payment_status', Order::PAYMENT_CONFIRMED)
            ->whereBetween('paid_at', [$start, $end])
            ->get(['paid_at', 'total_cents'])
            ->groupBy(fn (Order $order): string => $order->paid_at->format('Y-m-d H:i'))
            ->map(fn (Collection $orders): array => [
                'paid_cents' => (int) $orders->sum('total_cents'),
                'paid_order_count' => $orders->count(),
            ]);

        $minutes = max(1, $start->diffInMinutes($end));

        return collect(range(0, $minutes))
            ->map(function (int $minute) use ($start, $completedByMinute, $createdByMinute, $paidByMinute): array {
                /** @var Carbon $time */
                $time = $start->copy()->addMinutes($minute);
                $key = $time->format('Y-m-d H:i');
                $completed = $completedByMinute->get($key, [
                    'sales_cents' => 0,
                    'completed_order_count' => 0,
                ]);
                $paid = $paidByMinute->get($key, [
                    'paid_cents' => 0,
                    'paid_order_count' => 0,
                ]);

                return [
                    'time' => $key,
                    'label' => $time->format('m-d H:i'),
                    'sales_cents' => (int) $completed['sales_cents'],
                    'paid_cents' => (int) $paid['paid_cents'],
                    'order_count' => (int) $completed['completed_order_count'],
                    'created_order_count' => (int) ($createdByMinute->get($key) ?? 0),
                    'completed_order_count' => (int) $completed['completed_order_count'],
                    'paid_order_count' => (int) $paid['paid_order_count'],
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
        $orderCount = (int) $daily->sum('completed_order_count');

        return [
            'total_cents' => $totalCents,
            'order_count' => $orderCount,
            'created_order_count' => (int) $daily->sum('created_order_count'),
            'completed_order_count' => $orderCount,
            'paid_order_count' => (int) $daily->sum('paid_order_count'),
            'paid_cents' => (int) $daily->sum('paid_cents'),
            'average_order_cents' => $orderCount > 0 ? (int) round($totalCents / $orderCount) : 0,
            'best_day_cents' => (int) $daily->max('sales_cents'),
        ];
    }
}
