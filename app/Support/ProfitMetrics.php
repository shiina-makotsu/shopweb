<?php

namespace App\Support;

use App\Models\CostEntry;
use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ProfitMetrics
{
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
     * @return Collection<int, Order>
     */
    private function completedOrders(?CarbonInterface $dateFrom = null, ?CarbonInterface $dateTo = null): Collection
    {
        return $this->completedOrderQuery($dateFrom, $dateTo)
            ->with('items.warehouse')
            ->get();
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
