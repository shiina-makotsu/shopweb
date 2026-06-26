<?php

namespace App\Filament\Widgets;

use App\Support\DashboardSalesRange;
use App\Support\Money;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class DailySalesChart extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.daily-sales-chart';

    protected int | string | array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $range = app(DashboardSalesRange::class);
        $daily = $range->daily();
        $summary = $range->summary();
        $last24h = $range->hourlyMinutes();

        return [
            'daily' => $daily,
            'last24h' => $last24h,
            'summary' => $summary,
            'chart' => $this->buildChart($daily),
            'chart24h' => $this->buildChart($last24h, 'minute'),
            'hasData' => $summary['total_cents'] > 0 || $summary['order_count'] > 0,
            'totalSales' => Money::format($summary['total_cents']),
            'paidSales' => Money::format($summary['paid_cents']),
            'averageOrder' => Money::format($summary['average_order_cents']),
            'bestDaySales' => Money::format($summary['best_day_cents']),
        ];
    }

    /**
     * @param  Collection<int, array{date: string, label: string, sales_cents: int, paid_cents: int, order_count: int, created_order_count: int, completed_order_count: int, paid_order_count: int}>  $daily
     * @return array{paid_points: string, created_order_points: string, completed_order_points: string, baseline_points: string, money_y_labels: array<int, string>, count_y_labels: array<int, string>, x_labels: array<int, array{label: string, x: float}>, sample_points: array<int, array{x: float, paid_y: float, created_y: float, completed_y: float, label: string, paid_cents: int, created_order_count: int, completed_order_count: int}>}
     */
    public function publicBuildChartForReports(Collection $daily): array
    {
        return $this->buildChart($daily);
    }

    public function publicBuildMinuteChartForReports(Collection $rows): array
    {
        return $this->buildChart($rows, 'minute');
    }

    /**
     * @param  Collection<int, array{date: string, label: string, sales_cents: int, paid_cents: int, order_count: int, created_order_count: int, completed_order_count: int, paid_order_count: int}>  $daily
     * @return array{paid_points: string, created_order_points: string, completed_order_points: string, baseline_points: string, money_y_labels: array<int, string>, count_y_labels: array<int, string>, x_labels: array<int, array{label: string, x: float}>, sample_points: array<int, array{x: float, paid_y: float, created_y: float, completed_y: float, label: string, paid_cents: int, created_order_count: int, completed_order_count: int}>}
     */
    private function buildChart(Collection $daily, string $granularity = 'day'): array
    {
        $width = 1000;
        $height = 260;
        $left = 58;
        $right = 28;
        $top = 18;
        $bottom = 40;
        $plotWidth = $width - $left - $right;
        $plotHeight = $height - $top - $bottom;

        $rawMaxPaid = (int) $daily->max('paid_cents');
        $hasPaid = $rawMaxPaid > 0;
        $maxPaid = max(1, $rawMaxPaid);
        $maxOrders = max(1, (int) max(
            $daily->max('created_order_count'),
            $daily->max('completed_order_count'),
        ));
        $count = max(1, $daily->count() - 1);

        $point = function (int $index, int $value, int $max) use ($left, $top, $plotWidth, $plotHeight, $count): string {
            $x = $left + (($plotWidth / $count) * $index);
            $y = $top + ($plotHeight - (($value / $max) * $plotHeight));

            return round($x, 2).','.round($y, 2);
        };

        $paidPoints = $daily
            ->values()
            ->map(fn (array $row, int $index): string => $point($index, $row['paid_cents'], $maxPaid))
            ->implode(' ');

        $createdOrderPoints = $daily
            ->values()
            ->map(fn (array $row, int $index): string => $point($index, $row['created_order_count'], $maxOrders))
            ->implode(' ');

        $completedOrderPoints = $daily
            ->values()
            ->map(fn (array $row, int $index): string => $point($index, $row['completed_order_count'], $maxOrders))
            ->implode(' ');

        $baselineY = $top + $plotHeight;
        $baselinePoints = collect(range(0, $daily->count() - 1))
            ->map(fn (int $index): string => round($left + (($plotWidth / $count) * $index), 2).','.round($baselineY, 2))
            ->implode(' ');

        $moneyYLabels = $hasPaid
            ? collect(range(0, 4))
                ->map(fn (int $step): string => Money::format((int) round(($maxPaid / 4) * (4 - $step))))
                ->all()
            : array_fill(0, 5, Money::format(0));

        $countYLabels = collect(range(0, 4))
            ->map(fn (int $step): string => (string) (int) round(($maxOrders / 4) * (4 - $step)))
            ->all();

        $labelEvery = $granularity === 'minute' ? 120 : 7;

        $xLabels = $daily
            ->values()
            ->filter(fn (array $row, int $index): bool => $index === 0 || $index === $daily->count() - 1 || $index % $labelEvery === 0)
            ->map(fn (array $row, int $index): array => [
                'label' => $row['label'],
                'x' => round($left + (($plotWidth / $count) * $index), 2),
            ])
            ->values()
            ->all();

        $samplePoints = $daily
            ->values()
            ->map(function (array $row, int $index) use ($left, $top, $plotWidth, $plotHeight, $count, $maxPaid, $maxOrders): array {
                $x = round($left + (($plotWidth / $count) * $index), 2);
                $y = static fn (int $value, int $max): float => round($top + ($plotHeight - (($value / max(1, $max)) * $plotHeight)), 2);

                return [
                    'x' => $x,
                    'paid_y' => $y((int) ($row['paid_cents'] ?? 0), $maxPaid),
                    'created_y' => $y((int) ($row['created_order_count'] ?? 0), $maxOrders),
                    'completed_y' => $y((int) ($row['completed_order_count'] ?? 0), $maxOrders),
                    'label' => $row['label'],
                    'paid_cents' => (int) ($row['paid_cents'] ?? 0),
                    'sales_cents' => (int) ($row['sales_cents'] ?? 0),
                    'paid_order_count' => (int) ($row['paid_order_count'] ?? 0),
                    'created_order_count' => (int) ($row['created_order_count'] ?? 0),
                    'completed_order_count' => (int) ($row['completed_order_count'] ?? 0),
                ];
            })
            ->all();

        return [
            'paid_points' => $paidPoints,
            'created_order_points' => $createdOrderPoints,
            'completed_order_points' => $completedOrderPoints,
            'baseline_points' => $baselinePoints,
            'money_y_labels' => $moneyYLabels,
            'count_y_labels' => $countYLabels,
            'x_labels' => $xLabels,
            'sample_points' => $samplePoints,
        ];
    }
}
