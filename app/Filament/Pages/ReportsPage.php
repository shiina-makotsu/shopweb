<?php

namespace App\Filament\Pages;

use App\Support\ReportMetrics;
use App\Support\AdminAccess;
use App\Support\DashboardSalesRange;
use App\Support\Money;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ReportsPage extends Page
{
    protected static ?string $navigationLabel = '报告中心';
    protected static string|\UnitEnum|null $navigationGroup = '报告';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;
    protected static ?int $navigationSort = 10;
    protected static ?string $slug = 'reports';
    protected string $view = 'filament.pages.reports';

    public function getTitle(): string
    {
        return '报告中心';
    }

    public static function canAccess(): bool
    {
        return AdminAccess::can('reports');
    }

    /**
     * @return array<string, string|int>
     */
    public function salesSummary(): array
    {
        return app(ReportMetrics::class)->salesSummary();
    }

    public function salesTrend(): array
    {
        $range = app(DashboardSalesRange::class);
        $daily = $range->daily();
        $summary = $range->summary();

        return [
            'daily' => $daily,
            'summary' => $summary,
            'chart' => app(\App\Filament\Widgets\DailySalesChart::class)->publicBuildChartForReports($daily),
            'hasData' => $summary['total_cents'] > 0 || $summary['order_count'] > 0,
            'totalSales' => Money::format($summary['total_cents']),
            'averageOrder' => Money::format($summary['average_order_cents']),
            'bestDaySales' => Money::format($summary['best_day_cents']),
        ];
    }

    public function lowStockVariants()
    {
        return app(ReportMetrics::class)->lowStockVariants();
    }

    public function conversionFunnel(): array
    {
        return app(ReportMetrics::class)->conversionFunnel();
    }

    public function productConversions()
    {
        return app(ReportMetrics::class)->productConversions();
    }

    public function trafficSources()
    {
        return app(ReportMetrics::class)->trafficSources();
    }

    public function topCustomers()
    {
        return app(ReportMetrics::class)->topCustomers();
    }

    public function couponUsage()
    {
        return app(ReportMetrics::class)->couponUsage();
    }

    public function productSales()
    {
        return app(ReportMetrics::class)->productSales();
    }

    public function categorySales()
    {
        return app(ReportMetrics::class)->categorySales();
    }

    public function intentVotes()
    {
        return app(ReportMetrics::class)->intentVotes();
    }

    public function priceVotes()
    {
        return app(ReportMetrics::class)->priceVotes();
    }
}
