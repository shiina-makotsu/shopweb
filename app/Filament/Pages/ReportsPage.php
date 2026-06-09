<?php

namespace App\Filament\Pages;

use App\Support\ReportMetrics;
use App\Support\AdminAccess;
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

    public function lowStockVariants()
    {
        return app(ReportMetrics::class)->lowStockVariants();
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
