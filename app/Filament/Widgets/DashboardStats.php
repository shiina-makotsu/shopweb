<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\OrderResource;
use App\Services\AdminDashboardCache;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $heading = '数据统计';

    protected function getStats(): array
    {
        $metrics = app(AdminDashboardCache::class)->dashboardStats();
        $profit = $metrics['profit'];

        return [
            Stat::make('今日销售', Money::format((int) $metrics['today_sales']))
                ->description('已完成订单')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success'),

            Stat::make('本月销售', Money::format((int) $metrics['month_sales']))
                ->description(now()->format('Y-m').' 月累计')
                ->descriptionIcon(Heroicon::OutlinedChartBar)
                ->color('primary'),

            Stat::make('毛利润', Money::format($profit['gross_profit_cents']))
                ->description('销售额 - 采购成本')
                ->descriptionIcon(Heroicon::OutlinedPresentationChartLine)
                ->color($profit['gross_profit_cents'] >= 0 ? 'success' : 'danger'),

            Stat::make('总利润', Money::format($profit['profit_cents']))
                ->description('销售额 - 总成本')
                ->descriptionIcon(Heroicon::OutlinedChartPie)
                ->color($profit['profit_cents'] >= 0 ? 'success' : 'danger'),

            Stat::make('待确认付款', $metrics['pending_payments'])
                ->description('需要后台审核凭证')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color($metrics['pending_payments'] > 0 ? 'warning' : 'gray')
                ->url(OrderResource::getUrl('index')),

            Stat::make('低库存 SKU', $metrics['low_stock_variants'])
                ->description('库存小于或等于阈值')
                ->descriptionIcon(Heroicon::OutlinedCircleStack)
                ->color($metrics['low_stock_variants'] > 0 ? 'danger' : 'gray'),
        ];
    }
}
