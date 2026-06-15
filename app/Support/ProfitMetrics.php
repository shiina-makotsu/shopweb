<?php

namespace App\Support;

use App\Models\CostEntry;
use App\Models\Order;
use App\Models\Product;
use App\Models\SiteSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProfitMetrics
{
    public const DEFAULT_FORMULA = 'sales - cost';
    public const DEFAULT_FORMULA_RESULT_NAME = '公式利润';
    public const OPERATOR_ADD = '+';
    public const OPERATOR_SUBTRACT = '-';
    public const OPERATOR_MULTIPLY = '*';
    public const OPERATOR_DIVIDE = '/';

    /**
     * @return array{sales_cents:int,purchase_cost_cents:int,cost_cents:int,gross_profit_cents:int,profit_cents:int,gross_profit_rate:?float,profit_rate:?float,completed_orders:int}
     */
    public function summary(?CarbonInterface $dateFrom = null, ?CarbonInterface $dateTo = null): array
    {
        $completedOrders = $this->completedOrderQuery($dateFrom, $dateTo);
        $costEntries = $this->costEntryQuery($dateFrom, $dateTo);

        $sales = (int) (clone $completedOrders)->sum('total_cents');

        $purchaseCost = (int) (clone $costEntries)
            ->where('category', CostEntry::CATEGORY_PURCHASE)
            ->sum('amount_cents');
        $cost = (int) (clone $costEntries)->sum('amount_cents');

        $grossProfit = $sales - $purchaseCost;
        $profit = $sales - $cost;

        return [
            'sales_cents' => $sales,
            'purchase_cost_cents' => $purchaseCost,
            'cost_cents' => $cost,
            'gross_profit_cents' => $grossProfit,
            'profit_cents' => $profit,
            'gross_profit_rate' => $this->rate($grossProfit, $sales),
            'profit_rate' => $this->rate($profit, $sales),
            'completed_orders' => (clone $completedOrders)->count(),
        ];
    }

    /**
     * @return array<int, array{warehouse_id:int|null,warehouse_name:string,sales_cents:int,cost_cents:int,profit_cents:int,profit_rate:?float,orders_count:int}>
     */
    public function warehouseBreakdown(?CarbonInterface $dateFrom = null, ?CarbonInterface $dateTo = null): array
    {
        $rows = [];

        $this->completedOrders($dateFrom, $dateTo)
            ->each(function (Order $order) use (&$rows): void {
                $order->loadMissing('items.warehouse');

                if ($order->items->isEmpty()) {
                    $row = &$this->row($rows, null, '未分配仓库');
                    $row['sales_cents'] += (int) $order->total_cents;
                    $row['orders'][$order->id] = true;

                    return;
                }

                $itemSubtotal = max(1, (int) $order->items->sum('line_total_cents'));
                $shippingByWarehouse = $this->shippingByWarehouse($order);

                $order->items
                    ->groupBy(fn ($item): int|string => $item->warehouse_id ?: 'unassigned')
                    ->each(function (Collection $items, int|string $warehouseKey) use (&$rows, $order, $itemSubtotal, $shippingByWarehouse): void {
                        $warehouse = $items->first()?->warehouse;
                        $warehouseId = $warehouse?->id;
                        $warehouseName = $warehouse?->name ?: '未分配仓库';
                        $lineTotal = (int) $items->sum('line_total_cents');
                        $discountShare = (int) round(((int) $order->discount_cents) * ($lineTotal / $itemSubtotal));
                        $shippingShare = (int) ($shippingByWarehouse[$warehouseId ?: 'unassigned'] ?? 0);

                        if ($shippingShare === 0 && count($shippingByWarehouse) === 0 && $order->items->groupBy('warehouse_id')->count() === 1) {
                            $shippingShare = (int) $order->shipping_fee_cents;
                        }

                        $row = &$this->row($rows, $warehouseId, $warehouseName);
                        $row['sales_cents'] += max(0, $lineTotal - $discountShare + $shippingShare);
                        $row['orders'][$order->id] = true;
                    });
            });

        $this->costEntryQuery($dateFrom, $dateTo)
            ->with('procurement.warehouse')
            ->get()
            ->each(function (CostEntry $entry) use (&$rows): void {
                $warehouse = $entry->procurement?->warehouse;
                $row = &$this->row($rows, $warehouse?->id, $warehouse?->name ?: '未分配仓库');
                $row['cost_cents'] += (int) $entry->amount_cents;
            });

        return collect($rows)
            ->map(function (array $row): array {
                $row['orders_count'] = count($row['orders']);
                $row['profit_cents'] = (int) $row['sales_cents'] - (int) $row['cost_cents'];
                $row['profit_rate'] = $this->rate((int) $row['profit_cents'], (int) $row['sales_cents']);
                unset($row['orders']);

                return $row;
            })
            ->sortByDesc('profit_cents')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{type:string,label:string,sales_cents:int,cost_cents:int,purchase_cost_cents:int,shipping_cost_cents:int,customs_cost_cents:int,other_cost_cents:int,gross_profit_cents:int,profit_cents:int,formula_profit_cents:int,formula_result_name:string,profit_rate:?float,orders_count:int}>
     */
    public function fulfillmentBreakdown(?CarbonInterface $dateFrom = null, ?CarbonInterface $dateTo = null): array
    {
        $rows = [
            Product::FULFILLMENT_ONLINE => $this->fulfillmentRow(Product::FULFILLMENT_ONLINE, '线上交付'),
            Product::FULFILLMENT_IN_PERSON => $this->fulfillmentRow(Product::FULFILLMENT_IN_PERSON, '线下交付'),
            Product::FULFILLMENT_LOGISTICS => $this->fulfillmentRow(Product::FULFILLMENT_LOGISTICS, '物流交付'),
        ];

        $this->completedOrders($dateFrom, $dateTo)
            ->each(function (Order $order) use (&$rows): void {
                $order->loadMissing('items.product');
                $itemSubtotal = max(1, (int) $order->items->sum('line_total_cents'));

                $order->items
                    ->groupBy(fn ($item): string => $this->normalizedFulfillmentType($item->product?->fulfillment_type))
                    ->each(function (Collection $items, string $type) use (&$rows, $order, $itemSubtotal): void {
                        $rows[$type] ??= $this->fulfillmentRow($type, Product::fulfillmentOptions()[$type] ?? $type);
                        $lineTotal = (int) $items->sum('line_total_cents');
                        $discountShare = (int) round(((int) $order->discount_cents) * ($lineTotal / $itemSubtotal));
                        $shippingShare = $type === Product::FULFILLMENT_LOGISTICS ? (int) $order->shipping_fee_cents : 0;

                        $rows[$type]['sales_cents'] += max(0, $lineTotal - $discountShare + $shippingShare);
                        $rows[$type]['orders'][$order->id] = true;
                    });
            });

        $costsByCategory = $this->costEntryQuery($dateFrom, $dateTo)
            ->selectRaw('category, SUM(amount_cents) as amount_cents')
            ->groupBy('category')
            ->pluck('amount_cents', 'category')
            ->map(fn ($amount): int => (int) $amount);

        $purchaseCost = (int) ($costsByCategory[CostEntry::CATEGORY_PURCHASE] ?? 0);
        $shippingCost = (int) ($costsByCategory[CostEntry::CATEGORY_SHIPPING] ?? 0);
        $customsCost = (int) ($costsByCategory[CostEntry::CATEGORY_CUSTOMS] ?? 0);
        $otherCost = (int) ($costsByCategory[CostEntry::CATEGORY_OTHER] ?? 0);
        $totalCost = $purchaseCost + $shippingCost + $customsCost + $otherCost;
        $totalSales = max(1, array_sum(array_column($rows, 'sales_cents')));

        foreach ($rows as &$row) {
            $ratio = $row['sales_cents'] / $totalSales;
            $costShare = (int) round($totalCost * $ratio);
            $purchaseShare = (int) round($purchaseCost * $ratio);
            $row['cost_cents'] = $costShare;
            $row['purchase_cost_cents'] = $purchaseShare;
            $row['shipping_cost_cents'] = (int) round($shippingCost * $ratio);
            $row['customs_cost_cents'] = (int) round($customsCost * $ratio);
            $row['other_cost_cents'] = (int) round($otherCost * $ratio);
            $row['_total_sales_cents'] = $totalSales;
            $row['gross_profit_cents'] = $row['sales_cents'] - $purchaseShare;
            $row['profit_cents'] = $row['sales_cents'] - $costShare;
            $row['formula_profit_cents'] = $this->formulaProfit($row, $dateFrom, $dateTo);
            $row['formula_result_name'] = $this->profitFormulaConfig()['result_name'];
            $row['profit_rate'] = $this->rate((int) $row['formula_profit_cents'], (int) $row['sales_cents']);
            $row['orders_count'] = count($row['orders']);
            unset($row['orders']);
        }

        return array_values($rows);
    }

    public function profitFormula(): string
    {
        $config = $this->profitFormulaConfig();

        return sprintf(
            '%s = %s',
            $config['result_name'],
            $this->formulaExpression($config),
        );
    }

    /**
     * @return array{result_name:string,items:array<int, array{variable:string,operator?:string}>}
     */
    public function profitFormulaConfig(): array
    {
        $stored = trim((string) SiteSetting::query()->value('profit_formula'));

        if ($stored !== '' && str_starts_with($stored, '{')) {
            $decoded = json_decode($stored, true);

            if (is_array($decoded)) {
                return $this->normalizeFormulaConfig($decoded);
            }
        }

        return $this->legacyFormulaConfig($stored);
    }

    public function updateProfitFormula(?string $formula): void
    {
        $formula = trim((string) $formula);
        $this->storeProfitFormula($formula !== '' ? $formula : null);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function updateProfitFormulaConfig(array $config): void
    {
        $config = $this->normalizeFormulaConfig($config);
        $this->storeProfitFormula(json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string, string>
     */
    public function operatorOptions(): array
    {
        return [
            self::OPERATOR_ADD => '+',
            self::OPERATOR_SUBTRACT => '-',
            self::OPERATOR_MULTIPLY => '*',
            self::OPERATOR_DIVIDE => '/',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function formulaVariableOptions(?CarbonInterface $dateFrom = null, ?CarbonInterface $dateTo = null): array
    {
        $options = [
            'sales' => '商品售价',
            'cost' => '全部成本',
            'purchase_cost' => '商品成本',
            'shipping_cost' => '运输成本',
            'customs_cost' => '海关税务成本',
            'other_cost' => '其他成本',
            'gross_profit' => '毛利润',
            'profit' => '默认利润',
        ];

        CostEntry::query()
            ->select('name')
            ->whereNotNull('name')
            ->when($dateFrom, fn ($query) => $query->where('created_at', '>=', $dateFrom->copy()->startOfDay()))
            ->when($dateTo, fn ($query) => $query->where('created_at', '<=', $dateTo->copy()->endOfDay()))
            ->distinct()
            ->orderBy('name')
            ->limit(80)
            ->pluck('name')
            ->each(function (string $name) use (&$options): void {
                $options[$this->costNameVariableKey($name)] = '成本词条：'.$name;
            });

        return $options;
    }

    public function costNameVariableKey(string $name): string
    {
        return 'cost_name:'.sha1($name);
    }

    private function storeProfitFormula(?string $value): void
    {
        $settings = SiteSetting::query()->firstOrCreate([], ['site_name' => config('app.name', 'ShopWeb')]);
        $settings->profit_formula = $value;
        $settings->save();
    }

    /**
     * @return Collection<int, Order>
     */
    private function completedOrders(?CarbonInterface $dateFrom = null, ?CarbonInterface $dateTo = null): Collection
    {
        return $this->completedOrderQuery($dateFrom, $dateTo)
            ->with('items.warehouse')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function fulfillmentRow(string $type, string $label): array
    {
        return [
            'type' => $type,
            'label' => $label,
            'sales_cents' => 0,
            'cost_cents' => 0,
            'purchase_cost_cents' => 0,
            'shipping_cost_cents' => 0,
            'customs_cost_cents' => 0,
            'other_cost_cents' => 0,
            'gross_profit_cents' => 0,
            'profit_cents' => 0,
            'formula_profit_cents' => 0,
            'profit_rate' => null,
            'orders_count' => 0,
            'orders' => [],
        ];
    }

    private function normalizedFulfillmentType(?string $type): string
    {
        return match ($type) {
            Product::FULFILLMENT_ONLINE, Product::FULFILLMENT_CONTACT_LEGACY => Product::FULFILLMENT_ONLINE,
            Product::FULFILLMENT_IN_PERSON => Product::FULFILLMENT_IN_PERSON,
            default => Product::FULFILLMENT_LOGISTICS,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function formulaProfit(array $row, ?CarbonInterface $dateFrom = null, ?CarbonInterface $dateTo = null): int
    {
        $config = $this->profitFormulaConfig();
        $variables = $this->formulaVariableValues($row, $dateFrom, $dateTo);

        return (int) round($this->evaluateFormulaConfig($config, $variables) * 100);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, float|int>
     */
    private function formulaVariableValues(array $row, ?CarbonInterface $dateFrom = null, ?CarbonInterface $dateTo = null): array
    {
        $values = [
            'sales' => ((int) $row['sales_cents']) / 100,
            'cost' => ((int) $row['cost_cents']) / 100,
            'purchase_cost' => ((int) $row['purchase_cost_cents']) / 100,
            'shipping_cost' => ((int) ($row['shipping_cost_cents'] ?? 0)) / 100,
            'customs_cost' => ((int) ($row['customs_cost_cents'] ?? 0)) / 100,
            'other_cost' => ((int) ($row['other_cost_cents'] ?? 0)) / 100,
            'gross_profit' => ((int) $row['gross_profit_cents']) / 100,
            'profit' => ((int) $row['profit_cents']) / 100,
        ];

        $sales = max(1, (int) ($row['_total_sales_cents'] ?? $row['sales_cents'] ?? 0));
        $ratio = max(0, (int) $row['sales_cents']) / $sales;

        $this->costEntryQuery($dateFrom, $dateTo)
            ->selectRaw('name, SUM(amount_cents) as amount_cents')
            ->whereNotNull('name')
            ->groupBy('name')
            ->get()
            ->each(function (CostEntry $entry) use (&$values, $ratio): void {
                $values[$this->costNameVariableKey((string) $entry->name)] = ((int) round(((int) $entry->amount_cents) * $ratio)) / 100;
            });

        return $values;
    }

    /**
     * @param  array{result_name:string,items:array<int, array{variable:string,operator?:string}>}  $config
     * @param  array<string, float|int>  $variables
     */
    private function evaluateFormulaConfig(array $config, array $variables): float
    {
        $values = [];
        $operators = [];

        foreach ($config['items'] as $index => $item) {
            $value = (float) ($variables[$item['variable']] ?? 0);

            if ($index === 0) {
                $values[] = $value;

                continue;
            }

            $operator = $item['operator'] ?? self::OPERATOR_ADD;

            if ($operator === self::OPERATOR_MULTIPLY) {
                $values[array_key_last($values)] *= $value;

                continue;
            }

            if ($operator === self::OPERATOR_DIVIDE) {
                $values[array_key_last($values)] = abs($value) < 0.000001 ? 0.0 : $values[array_key_last($values)] / $value;

                continue;
            }

            $operators[] = $operator;
            $values[] = $value;
        }

        $result = array_shift($values) ?? 0.0;

        foreach ($values as $index => $value) {
            $result = ($operators[$index] ?? self::OPERATOR_ADD) === self::OPERATOR_SUBTRACT
                ? $result - $value
                : $result + $value;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{result_name:string,items:array<int, array{variable:string,operator?:string}>}
     */
    private function normalizeFormulaConfig(array $config): array
    {
        $resultName = trim((string) ($config['result_name'] ?? ''));
        $rawItems = $config['items'] ?? null;

        if (! is_array($rawItems) || $rawItems === []) {
            $rawItems = [
                ['variable' => $config['left_variable'] ?? 'sales'],
                [
                    'operator' => $config['operator'] ?? self::OPERATOR_SUBTRACT,
                    'variable' => $config['right_variable'] ?? 'cost',
                ],
            ];
        }

        $items = [];

        foreach ($rawItems as $index => $rawItem) {
            if (! is_array($rawItem)) {
                continue;
            }

            $variable = trim((string) ($rawItem['variable'] ?? ''));

            if ($variable === '') {
                continue;
            }

            $item = ['variable' => $variable];

            if ($index > 0) {
                $operator = trim((string) ($rawItem['operator'] ?? self::OPERATOR_ADD));
                $item['operator'] = array_key_exists($operator, $this->operatorOptions())
                    ? $operator
                    : self::OPERATOR_ADD;
            }

            $items[] = $item;
        }

        if (count($items) < 2) {
            $items = [
                ['variable' => 'sales'],
                ['operator' => self::OPERATOR_SUBTRACT, 'variable' => 'cost'],
            ];
        }

        return [
            'result_name' => $resultName !== '' ? $resultName : self::DEFAULT_FORMULA_RESULT_NAME,
            'items' => array_values($items),
        ];
    }

    /**
     * @return array{result_name:string,items:array<int, array{variable:string,operator?:string}>}
     */
    private function legacyFormulaConfig(string $formula): array
    {
        $formula = Str::lower(trim($formula));

        if (preg_match_all('/([+\-*\/])?\s*([a-z_][a-z0-9_:]*)/', $formula, $matches, PREG_SET_ORDER) && count($matches) >= 2) {
            $items = [];

            foreach ($matches as $index => $match) {
                $item = ['variable' => $match[2]];

                if ($index > 0) {
                    $item['operator'] = $match[1] !== '' ? $match[1] : self::OPERATOR_ADD;
                }

                $items[] = $item;
            }

            return $this->normalizeFormulaConfig([
                'result_name' => self::DEFAULT_FORMULA_RESULT_NAME,
                'items' => $items,
            ]);
        }

        return $this->normalizeFormulaConfig([
            'result_name' => self::DEFAULT_FORMULA_RESULT_NAME,
            'items' => [
                ['variable' => 'sales'],
                ['operator' => self::OPERATOR_SUBTRACT, 'variable' => 'cost'],
            ],
        ]);
    }

    /**
     * @param  array{result_name:string,items:array<int, array{variable:string,operator?:string}>}  $config
     */
    private function formulaExpression(array $config): string
    {
        return collect($config['items'])
            ->map(fn (array $item, int $index): string => ($index === 0 ? '' : ($item['operator'] ?? self::OPERATOR_ADD).' ').$item['variable'])
            ->implode(' ');
    }

    private function completedOrderQuery(?CarbonInterface $dateFrom = null, ?CarbonInterface $dateTo = null)
    {
        return $this->applyDateRange(
            Order::query()
                ->where('payment_status', Order::PAYMENT_CONFIRMED)
                ->where('status', Order::STATUS_FULFILLED),
            $dateFrom,
            $dateTo,
        );
    }

    private function costEntryQuery(?CarbonInterface $dateFrom = null, ?CarbonInterface $dateTo = null)
    {
        return $this->applyDateRange(CostEntry::query(), $dateFrom, $dateTo);
    }

    private function applyDateRange($query, ?CarbonInterface $dateFrom = null, ?CarbonInterface $dateTo = null)
    {
        return $query
            ->when($dateFrom, fn ($query) => $query->where('created_at', '>=', $dateFrom->copy()->startOfDay()))
            ->when($dateTo, fn ($query) => $query->where('created_at', '<=', $dateTo->copy()->endOfDay()));
    }

    /**
     * @return array<int|string, int>
     */
    private function shippingByWarehouse(Order $order): array
    {
        return collect($order->shipment_plan ?: [])
            ->reduce(function (array $carry, array $shipment): array {
                $key = $shipment['warehouse_id'] ?? 'unassigned';
                $carry[$key] = ($carry[$key] ?? 0) + (int) ($shipment['fee_cents'] ?? 0);

                return $carry;
            }, []);
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function &row(array &$rows, ?int $warehouseId, string $warehouseName): array
    {
        $key = $warehouseId ?: 'unassigned';

        if (! isset($rows[$key])) {
            $rows[$key] = [
                'warehouse_id' => $warehouseId,
                'warehouse_name' => $warehouseName,
                'sales_cents' => 0,
                'cost_cents' => 0,
                'profit_cents' => 0,
                'orders' => [],
            ];
        }

        return $rows[$key];
    }

    private function rate(int $profitCents, int $salesCents): ?float
    {
        if ($salesCents <= 0) {
            return null;
        }

        return $profitCents / $salesCents;
    }
}
