<?php

namespace App\Filament\Widgets;

use App\Support\DashboardSalesRange;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesRangeStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $heading = '近 30 日销售统计';

    protected function getStats(): array
    {
        $daily = app(DashboardSalesRange::class)->daily();
        $summary = app(DashboardSalesRange::class)->summary();
        $bestDay = $daily->sortByDesc('sales_cents')->first();
        $sparkline = $daily
            ->map(fn (array $row): float => round($row['sales_cents'] / 100, 2))
            ->all();

        return [
            Stat::make('30 日销售额', Money::format($summary['total_cents']))
                ->description('已确认收款订单合计')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->chart($sparkline)
                ->chartColor('primary')
                ->color('primary'),

            Stat::make('30 日订单数', $summary['order_count'])
                ->description('已确认收款订单数量')
                ->descriptionIcon(Heroicon::OutlinedShoppingCart)
                ->chart($daily->pluck('order_count')->all())
                ->chartColor('success')
                ->color('success'),

            Stat::make('平均客单价', Money::format($summary['average_order_cents']))
                ->description('销售额 / 订单数')
                ->descriptionIcon(Heroicon::OutlinedScale)
                ->color('gray'),

            Stat::make('最高日销售', Money::format($summary['best_day_cents']))
                ->description($bestDay && $bestDay['sales_cents'] > 0 ? $bestDay['date'] : '暂无销售峰值')
                ->descriptionIcon(Heroicon::OutlinedTrophy)
                ->color($summary['best_day_cents'] > 0 ? 'warning' : 'gray'),
        ];
    }
}
