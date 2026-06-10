<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Support\ChinaRegions;
use Illuminate\Support\Collection;

class ShippingQuoteService
{
    /**
     * @param  Collection<int, array{product: Product, variant: mixed, quantity: int}>  $cartItems
     * @return array{
     *     province:?string,
     *     shipping_fee_cents:int,
     *     is_multi_warehouse:bool,
     *     shipments:array<int, array{warehouse_id:?int, warehouse_name:string, fee_cents:int, items:array<int, array{product_id:int, product_variant_id:?int, title:string, quantity:int}>}>,
     *     item_warehouse_map:array<int, int|null>,
     *     notice:?string
     * }
     */
    public function quote(Collection $cartItems, ?string $province = null): array
    {
        $province = ChinaRegions::normalizeProvince($province);
        $shippingItems = $cartItems
            ->filter(fn (array $item): bool => $item['product']->requiresShipping())
            ->values();

        if ($shippingItems->isEmpty()) {
            return $this->emptyQuote($province);
        }

        $warehouses = Warehouse::query()
            ->with('shippingRates')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($warehouses->isEmpty()) {
            $warehouses = collect([$this->defaultWarehouse()]);
        }

        $stockMatrix = $this->stockMatrix($shippingItems);
        $singleWarehouse = $warehouses->first(fn (Warehouse $warehouse): bool => $this->warehouseCoversAllItems($warehouse, $shippingItems, $stockMatrix));

        if ($singleWarehouse) {
            return $this->buildQuote($province, [
                $this->shipmentFor($singleWarehouse, $shippingItems, $province),
            ]);
        }

        $shipments = [];
        $itemWarehouseMap = [];

        foreach ($shippingItems as $item) {
            $warehouse = $this->bestWarehouseForItem($warehouses, $item, $stockMatrix) ?: $warehouses->first();
            $key = (string) ($warehouse?->id ?? 0);
            $shipments[$key] ??= [
                'warehouse_id' => $warehouse?->id,
                'warehouse_name' => $warehouse?->name ?? '默认仓库',
                'fee_cents' => $warehouse ? $this->warehouseBaseFee($warehouse, $province) : 0,
                'items' => [],
            ];
            $shipments[$key]['items'][] = $this->itemSummary($item);
            $shipments[$key]['fee_cents'] += $this->productExtraFee($item);
            $itemWarehouseMap[(int) $item['variant']->id] = $warehouse?->id;
        }

        $quote = $this->buildQuote($province, array_values($shipments));
        $quote['item_warehouse_map'] = $itemWarehouseMap;

        return $quote;
    }

    /**
     * @param  array<int, array{warehouse_id:?int, warehouse_name:string, fee_cents:int, items:array<int, array{product_id:int, product_variant_id:?int, title:string, quantity:int}>}>  $shipments
     * @return array<string, mixed>
     */
    private function buildQuote(?string $province, array $shipments): array
    {
        $shippingFee = array_sum(array_map(fn (array $shipment): int => (int) $shipment['fee_cents'], $shipments));
        $isMultiWarehouse = count($shipments) > 1;
        $notice = null;

        if ($isMultiWarehouse) {
            $parts = array_map(function (array $shipment): string {
                $items = collect($shipment['items'])->map(fn (array $item): string => $item['title'].' x '.$item['quantity'])->implode('、');

                return $shipment['warehouse_name'].'：'.$items;
            }, $shipments);
            $notice = '本订单需要多仓发货，以下商品来自不同仓库，将分别计算邮费：'.implode('；', $parts);
        }

        $itemWarehouseMap = [];

        foreach ($shipments as $shipment) {
            foreach ($shipment['items'] as $item) {
                if ($item['product_variant_id']) {
                    $itemWarehouseMap[(int) $item['product_variant_id']] = $shipment['warehouse_id'];
                }
            }
        }

        return [
            'province' => $province,
            'shipping_fee_cents' => $shippingFee,
            'is_multi_warehouse' => $isMultiWarehouse,
            'shipments' => $shipments,
            'item_warehouse_map' => $itemWarehouseMap,
            'notice' => $notice,
        ];
    }

    /**
     * @param  Collection<int, array{product: Product, variant: mixed, quantity: int}>  $items
     * @return array{warehouse_id:?int, warehouse_name:string, fee_cents:int, items:array<int, array{product_id:int, product_variant_id:?int, title:string, quantity:int}>}
     */
    private function shipmentFor(Warehouse $warehouse, Collection $items, ?string $province): array
    {
        return [
            'warehouse_id' => $warehouse->id,
            'warehouse_name' => $warehouse->name,
            'fee_cents' => $this->warehouseBaseFee($warehouse, $province)
                + (int) $items->sum(fn (array $item): int => $this->productExtraFee($item)),
            'items' => $items->map(fn (array $item): array => $this->itemSummary($item))->values()->all(),
        ];
    }

    /**
     * @param  array{product: Product, variant: mixed, quantity: int}  $item
     * @return array{product_id:int, product_variant_id:?int, title:string, quantity:int}
     */
    private function itemSummary(array $item): array
    {
        return [
            'product_id' => (int) $item['product']->id,
            'product_variant_id' => $item['variant']?->id,
            'title' => $item['product']->title,
            'quantity' => (int) $item['quantity'],
        ];
    }

    private function warehouseBaseFee(Warehouse $warehouse, ?string $province): int
    {
        $rates = $warehouse->shippingRates
            ->where('is_active', true)
            ->sortBy('sort_order');

        $provinceRate = $rates->first(fn ($rate): bool => ! $rate->is_default && $rate->matchesProvince($province));

        if ($provinceRate) {
            return (int) $provinceRate->fee_cents;
        }

        return (int) ($rates->firstWhere('is_default', true)?->fee_cents ?? 0);
    }

    /**
     * @param  array{product: Product, quantity: int}  $item
     */
    private function productExtraFee(array $item): int
    {
        return (int) ($item['product']->shipping_extra_fee_cents ?? 0) * max(1, (int) $item['quantity']);
    }

    /**
     * @param  Collection<int, array{variant: mixed}>  $items
     * @return array<int, array<int, int>>
     */
    private function stockMatrix(Collection $items): array
    {
        $variantIds = $items->map(fn (array $item): int => (int) $item['variant']->id)->filter()->unique()->values();

        return WarehouseStock::query()
            ->whereIn('product_variant_id', $variantIds)
            ->get()
            ->reduce(function (array $matrix, WarehouseStock $stock): array {
                $matrix[(int) $stock->warehouse_id][(int) $stock->product_variant_id] = $stock->availableQuantity();

                return $matrix;
            }, []);
    }

    /**
     * @param  array<int, array<int, int>>  $stockMatrix
     * @param  Collection<int, array{variant: mixed, quantity: int}>  $items
     */
    private function warehouseCoversAllItems(Warehouse $warehouse, Collection $items, array $stockMatrix): bool
    {
        foreach ($items as $item) {
            $available = $stockMatrix[(int) $warehouse->id][(int) $item['variant']->id] ?? 0;

            if ($available < (int) $item['quantity']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  Collection<int, Warehouse>  $warehouses
     * @param  array{variant: mixed, quantity: int}  $item
     * @param  array<int, array<int, int>>  $stockMatrix
     */
    private function bestWarehouseForItem(Collection $warehouses, array $item, array $stockMatrix): ?Warehouse
    {
        return $warehouses->first(function (Warehouse $warehouse) use ($item, $stockMatrix): bool {
            $available = $stockMatrix[(int) $warehouse->id][(int) $item['variant']->id] ?? 0;

            return $available >= (int) $item['quantity'];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyQuote(?string $province): array
    {
        return [
            'province' => $province,
            'shipping_fee_cents' => 0,
            'is_multi_warehouse' => false,
            'shipments' => [],
            'item_warehouse_map' => [],
            'notice' => null,
        ];
    }

    private function defaultWarehouse(): Warehouse
    {
        return Warehouse::query()->firstOrCreate(
            ['name' => '默认仓库'],
            ['country' => '中国', 'is_active' => true, 'sort_order' => 0],
        );
    }
}
