<?php

namespace App\Filament\Pages;

use App\Support\AdminAccess;
use App\Support\Money;
use App\Support\ProfitMetrics;
use BackedEnum;
use Carbon\Carbon;
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

    public ?string $date_from = null;

    public ?string $date_to = null;

    public function mount(): void
    {
        $this->date_from = request('date_from');
        $this->date_to = request('date_to');
    }

    public static function canAccess(): bool
    {
        return AdminAccess::can('finance');
    }

    /**
     * @return array<string, string|int>
     */
    public function metrics(): array
    {
        $summary = app(ProfitMetrics::class)->summary($this->dateFrom(), $this->dateTo());

        return [
            '销售额' => Money::format($summary['sales_cents']),
            '采购成本' => Money::format($summary['purchase_cost_cents']),
            '毛利润' => Money::format($summary['gross_profit_cents']),
            '毛利润率' => $this->formatRate($summary['gross_profit_rate']),
            '总成本' => Money::format($summary['cost_cents']),
            '总利润' => Money::format($summary['profit_cents']),
            '总利润率' => $this->formatRate($summary['profit_rate']),
            '已确认付款订单' => $summary['completed_orders'],
        ];
    }

    /**
     * @return array<int, array<string, string|int|null>>
     */
    public function warehouseRows(): array
    {
        return collect(app(ProfitMetrics::class)->warehouseBreakdown($this->dateFrom(), $this->dateTo()))
            ->map(fn (array $row): array => [
                'warehouse_name' => $row['warehouse_name'],
                'sales' => Money::format((int) $row['sales_cents']),
                'cost' => Money::format((int) $row['cost_cents']),
                'profit' => Money::format((int) $row['profit_cents']),
                'profit_rate' => $this->formatRate($row['profit_rate']),
                'orders_count' => $row['orders_count'],
            ])
            ->all();
    }

    public function exportUrl(): string
    {
        return route('admin.report-exports.profit-overview', array_filter([
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
        ]));
    }

    private function dateFrom(): ?Carbon
    {
        return $this->parseDate($this->date_from);
    }

    private function dateTo(): ?Carbon
    {
        return $this->parseDate($this->date_to);
    }

    private function parseDate(?string $date): ?Carbon
    {
        if (blank($date)) {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatRate(?float $rate): string
    {
        if ($rate === null) {
            return '-';
        }

        return number_format($rate * 100, 2).'%';
    }
}
