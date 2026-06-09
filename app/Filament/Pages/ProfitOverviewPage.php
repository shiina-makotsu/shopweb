<?php

namespace App\Filament\Pages;

use App\Support\AdminAccess;
use App\Support\Money;
use App\Support\ProfitMetrics;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ProfitOverviewPage extends Page
{
    protected string $view = 'filament.pages.profit-overview';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static string|UnitEnum|null $navigationGroup = '财务';

    protected static ?string $navigationLabel = '利润概览';

    protected static ?string $slug = 'profit-overview';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return AdminAccess::can('finance');
    }

    /**
     * @return array<string, string|int>
     */
    public function metrics(): array
    {
        $summary = app(ProfitMetrics::class)->summary();

        return [
            '销售额' => Money::format($summary['sales_cents']),
            '总成本' => Money::format($summary['cost_cents']),
            '利润' => Money::format($summary['profit_cents']),
            '已确认付款订单' => $summary['completed_orders'],
        ];
    }
}
