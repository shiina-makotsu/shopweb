<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;
use App\Support\ProfitMetrics;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $heading = '数据统计';

    protected function getStats(): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $todaySales = Order::query()
            ->where('payment_status', Order::PAYMENT_CONFIRMED)
            ->whereBetween('paid_at', [$todayStart, $todayEnd])
            ->sum('total_cents');

        $monthSales = Order::query()
            ->where('payment_status', Order::PAYMENT_CONFIRMED)
            ->whereBetween('paid_at', [$monthStart, $monthEnd])
            ->sum('total_cents');

        $pendingPayments = Order::query()
            ->where('payment_status', Order::PAYMENT_SUBMITTED)
            ->count();

        $lowStockVariants = ProductVariant::query()
            ->whereHas('product', fn ($query) => $query->whereNotIn('fulfillment_type', [
                Product::FULFILLMENT_ONLINE,
                Product::FULFILLMENT_CONTACT_LEGACY,
            ]))
            ->whereColumn('stock', '<=', 'low_stock_threshold')
            ->count();

        $profit = app(ProfitMetrics::class)->summary();

        return [
            Stat::make('今日销售', Money::format((int) $todaySales))
                ->description('已确认付款订单')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success'),

            Stat::make('本月销售', Money::format((int) $monthSales))
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

            Stat::make('待确认付款', $pendingPayments)
                ->description('需要后台审核凭证')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color($pendingPayments > 0 ? 'warning' : 'gray')
                ->url(OrderResource::getUrl('index')),

            Stat::make('低库存 SKU', $lowStockVariants)
                ->description('库存小于或等于阈值')
                ->descriptionIcon(Heroicon::OutlinedCircleStack)
                ->color($lowStockVariants > 0 ? 'danger' : 'gray'),
        ];
    }
}
