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
     * @return array<int, array{type:string,label:string,sales_cents:int,cost_cents:int,purchase_cost_cents:int,gross_profit_cents:int,profit_cents:int,formula_profit_cents:int,profit_rate:?float,orders_count:int}>
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

        $purchaseCost = (int) (clone $this->costEntryQuery($dateFrom, $dateTo))
            ->where('category', CostEntry::CATEGORY_PURCHASE)
            ->sum('amount_cents');
        $totalCost = (int) (clone $this->costEntryQuery($dateFrom, $dateTo))->sum('amount_cents');
        $sales = max(1, array_sum(array_column($rows, 'sales_cents')));

        foreach ($rows as &$row) {
            $costShare = (int) round($totalCost * ($row['sales_cents'] / $sales));
            $purchaseShare = (int) round($purchaseCost * ($row['sales_cents'] / $sales));
            $row['cost_cents'] = $costShare;
            $row['purchase_cost_cents'] = $purchaseShare;
            $row['gross_profit_cents'] = $row['sales_cents'] - $purchaseShare;
            $row['profit_cents'] = $row['sales_cents'] - $costShare;
            $row['formula_profit_cents'] = $this->formulaProfit($row);
            $row['profit_rate'] = $this->rate((int) $row['formula_profit_cents'], (int) $row['sales_cents']);
            $row['orders_count'] = count($row['orders']);
            unset($row['orders']);
        }

        return array_values($rows);
    }

    public function profitFormula(): string
    {
        $formula = trim((string) SiteSetting::query()->value('profit_formula'));

        return $formula !== '' ? $formula : self::DEFAULT_FORMULA;
    }

    public function updateProfitFormula(?string $formula): void
    {
        $formula = trim((string) $formula);
        $settings = SiteSetting::query()->firstOrCreate([], ['site_name' => config('app.name', 'ShopWeb')]);
        $settings->profit_formula = $formula !== '' ? $formula : null;
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
    private function formulaProfit(array $row): int
    {
        $variables = [
            'sales' => ((int) $row['sales_cents']) / 100,
            'cost' => ((int) $row['cost_cents']) / 100,
            'purchase_cost' => ((int) $row['purchase_cost_cents']) / 100,
            'gross_profit' => ((int) $row['gross_profit_cents']) / 100,
            'profit' => ((int) $row['profit_cents']) / 100,
        ];

        return (int) round($this->evaluateFormula($this->profitFormula(), $variables) * 100);
    }

    /**
     * @param  array<string, float|int>  $variables
     */
    private function evaluateFormula(string $formula, array $variables): float
    {
        $expression = Str::lower(trim($formula));

        if ($expression === '') {
            $expression = self::DEFAULT_FORMULA;
        }

        foreach ($variables as $name => $value) {
            $expression = preg_replace('/\b'.preg_quote($name, '/').'\b/', '('.((float) $value).')', $expression) ?? $expression;
        }

        if (preg_match('/[^0-9+\-*\/().\s]/', $expression)) {
            $expression = str_replace(['sales', 'cost'], ['('.$variables['sales'].')', '('.$variables['cost'].')'], self::DEFAULT_FORMULA);
        }

        try {
            $result = eval('return '.$expression.';');
        } catch (\Throwable) {
            $result = $variables['sales'] - $variables['cost'];
        }

        return is_numeric($result) && is_finite((float) $result) ? (float) $result : 0.0;
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
