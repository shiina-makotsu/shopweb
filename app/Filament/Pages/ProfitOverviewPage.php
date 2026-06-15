<?php

namespace App\Filament\Pages;

use App\Support\AdminAccess;
use App\Support\Money;
use App\Support\ProfitMetrics;
use App\Support\Url;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
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

    public ?string $formula_result_name = null;

    /**
     * @var array<int, array{variable?:string,operator?:string}>
     */
    public array $formula_items = [];

    public function mount(): void
    {
        $this->date_from = request('date_from');
        $this->date_to = request('date_to');
        $this->fillFormulaState();
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

    /**
     * @return array<int, array<string, string|int|null>>
     */
    public function fulfillmentRows(): array
    {
        return collect(app(ProfitMetrics::class)->fulfillmentBreakdown($this->dateFrom(), $this->dateTo()))
            ->map(fn (array $row): array => [
                'label' => $row['label'],
                'sales' => Money::format((int) $row['sales_cents']),
                'purchase_cost' => Money::format((int) $row['purchase_cost_cents']),
                'cost' => Money::format((int) $row['cost_cents']),
                'gross_profit' => Money::format((int) $row['gross_profit_cents']),
                'profit' => Money::format((int) $row['profit_cents']),
                'formula_profit' => Money::format((int) $row['formula_profit_cents']),
                'formula_result_name' => $row['formula_result_name'] ?? ProfitMetrics::DEFAULT_FORMULA_RESULT_NAME,
                'profit_rate' => $this->formatRate($row['profit_rate']),
                'orders_count' => $row['orders_count'],
            ])
            ->all();
    }

    public function saveProfitFormula(): void
    {
        app(ProfitMetrics::class)->updateProfitFormulaConfig([
            'result_name' => $this->formula_result_name,
            'items' => $this->formula_items,
        ]);

        $this->fillFormulaState();

        Notification::make()
            ->title('利润公式已保存')
            ->success()
            ->send();
    }

    public function addFormulaItem(): void
    {
        $this->formula_items[] = [
            'operator' => ProfitMetrics::OPERATOR_ADD,
            'variable' => 'cost',
        ];
    }

    public function removeFormulaItem(int $index): void
    {
        if (count($this->formula_items) <= 2) {
            return;
        }

        unset($this->formula_items[$index]);
        $this->formula_items = array_values($this->formula_items);

        if (isset($this->formula_items[0])) {
            unset($this->formula_items[0]['operator']);
        }
    }

    /**
     * @return array<string, string>
     */
    public function formulaVariableOptions(): array
    {
        return app(ProfitMetrics::class)->formulaVariableOptions($this->dateFrom(), $this->dateTo());
    }

    /**
     * @return array<string, string>
     */
    public function formulaOperatorOptions(): array
    {
        return app(ProfitMetrics::class)->operatorOptions();
    }

    public function formulaResultName(): string
    {
        return $this->formula_result_name ?: ProfitMetrics::DEFAULT_FORMULA_RESULT_NAME;
    }

    public function formulaPreview(): string
    {
        $variables = $this->formulaVariableOptions();
        $parts = [];

        foreach ($this->formula_items as $index => $item) {
            $variable = $variables[$item['variable'] ?? ''] ?? ($item['variable'] ?? '未选择变量');

            if ($index === 0) {
                $parts[] = $variable;

                continue;
            }

            $parts[] = ($item['operator'] ?? ProfitMetrics::OPERATOR_ADD).' '.$variable;
        }

        return implode(' ', $parts).' = '.$this->formulaResultName();
    }

    public function exportUrl(): string
    {
        return Url::route('admin.report-exports.profit-overview', array_filter([
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

    private function fillFormulaState(): void
    {
        $config = app(ProfitMetrics::class)->profitFormulaConfig();
        $this->formula_result_name = $config['result_name'];
        $this->formula_items = $config['items'];
    }
}
