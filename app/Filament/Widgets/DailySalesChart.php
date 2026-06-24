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
        $daily = app(DashboardSalesRange::class)->daily();
        $summary = app(DashboardSalesRange::class)->summary();

        return [
            'daily' => $daily,
            'summary' => $summary,
            'chart' => $this->buildChart($daily),
            'hasData' => $summary['total_cents'] > 0 || $summary['order_count'] > 0,
            'totalSales' => Money::format($summary['total_cents']),
            'averageOrder' => Money::format($summary['average_order_cents']),
            'bestDaySales' => Money::format($summary['best_day_cents']),
        ];
    }

    /**
     * @param  Collection<int, array{date: string, label: string, sales_cents: int, order_count: int}>  $daily
     * @return array{sales_points: string, order_points: string, baseline_points: string, y_labels: array<int, string>, x_labels: array<int, array{label: string, x: float}>}
     */
    public function publicBuildChartForReports(Collection $daily): array
    {
        return $this->buildChart($daily);
    }

    /**
     * @param  Collection<int, array{date: string, label: string, sales_cents: int, order_count: int}>  $daily
     * @return array{sales_points: string, order_points: string, baseline_points: string, y_labels: array<int, string>, x_labels: array<int, array{label: string, x: float}>}
     */
    private function buildChart(Collection $daily): array
    {
        $width = 1000;
        $height = 260;
        $left = 58;
        $right = 28;
        $top = 18;
        $bottom = 40;
        $plotWidth = $width - $left - $right;
        $plotHeight = $height - $top - $bottom;

        $rawMaxSales = (int) $daily->max('sales_cents');
        $hasSales = $rawMaxSales > 0;
        $maxSales = max(1, $rawMaxSales);
        $maxOrders = max(1, (int) $daily->max('order_count'));
        $count = max(1, $daily->count() - 1);

        $point = function (int $index, int $value, int $max) use ($left, $top, $plotWidth, $plotHeight, $count): string {
            $x = $left + (($plotWidth / $count) * $index);
            $y = $top + ($plotHeight - (($value / $max) * $plotHeight));

            return round($x, 2).','.round($y, 2);
        };

        $salesPoints = $daily
            ->values()
            ->map(fn (array $row, int $index): string => $point($index, $row['sales_cents'], $maxSales))
            ->implode(' ');

        $orderPoints = $daily
            ->values()
            ->map(fn (array $row, int $index): string => $point($index, $row['order_count'], $maxOrders))
            ->implode(' ');

        $baselineY = $top + $plotHeight;
        $baselinePoints = collect(range(0, $daily->count() - 1))
            ->map(fn (int $index): string => round($left + (($plotWidth / $count) * $index), 2).','.round($baselineY, 2))
            ->implode(' ');

        $yLabels = $hasSales
            ? collect(range(0, 4))
                ->map(fn (int $step): string => Money::format((int) round(($maxSales / 4) * (4 - $step))))
                ->all()
            : array_fill(0, 5, Money::format(0));

        $xLabels = $daily
            ->values()
            ->filter(fn (array $row, int $index): bool => $index === 0 || $index === $daily->count() - 1 || $index % 7 === 0)
            ->map(fn (array $row, int $index): array => [
                'label' => $row['label'],
                'x' => round($left + (($plotWidth / $count) * $index), 2),
            ])
            ->values()
            ->all();

        return [
            'sales_points' => $salesPoints,
            'order_points' => $orderPoints,
            'baseline_points' => $baselinePoints,
            'y_labels' => $yLabels,
            'x_labels' => $xLabels,
        ];
    }
}
