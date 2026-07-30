<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\Warehouse;
use App\Models\WarehouseShippingRate;
use App\Models\WarehouseStock;
use App\Support\ChinaRegions;
use Illuminate\Support\Collection;

class ShippingQuoteService
{
    /**
     * @param  Collection<int, array{product: Product, variant: mixed, quantity: int}>  $cartItems
     * @param  array<int|string, mixed>  $selectedCarriers warehouse_id => shipping_carrier_id
     * @return array<string, mixed>
     */
    public function quote(Collection $cartItems, ?string $province = null, array $selectedCarriers = [], ?string $city = null): array
    {
        $province = ChinaRegions::normalizeProvince($province);
        $selectedCarriers = $this->normalizeSelectedCarriers($selectedCarriers);
        $defaultPresaleWarehouseId = $this->defaultPresaleWarehouseId();
        $shippingItems = $cartItems
            ->filter(fn (array $item): bool => $item['product']->requiresShipping())
            ->values();

        if ($shippingItems->isEmpty()) {
            return $this->emptyQuote($province);
        }

        $warehouses = Warehouse::query()
            ->with('shippingRates.shippingCarrier')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($warehouses->isEmpty()) {
            $warehouses = collect([$this->defaultWarehouse()]);
        }

        $stockMatrix = $this->stockMatrix($shippingItems);
        $singleWarehouse = $warehouses
            ->filter(fn (Warehouse $warehouse): bool => $this->warehouseCoversAllItems($warehouse, $shippingItems, $stockMatrix, $defaultPresaleWarehouseId))
            ->sortByDesc(fn (Warehouse $warehouse): int => $this->warehouseAddressScore($warehouse, $province, $city) * 10 + ($this->warehouseHasActiveRate($warehouse, $province) ? 1 : 0))
            ->first();

        if ($singleWarehouse) {
            return $this->buildQuote($province, [
                $this->shipmentFor($singleWarehouse, $shippingItems, $province, $selectedCarriers),
            ]);
        }

        $shipments = [];
        $itemWarehouseMap = [];

        foreach ($shippingItems as $item) {
            $warehouse = $this->bestWarehouseForItem($warehouses, $item, $stockMatrix, $defaultPresaleWarehouseId, $province, $city) ?: $warehouses->first();
            $key = (string) ($warehouse?->id ?? 0);

            if (! isset($shipments[$key])) {
                $rate = $warehouse ? $this->selectedRateForWarehouse($warehouse, $province, $selectedCarriers[(int) $warehouse->id] ?? null) : null;
                $baseFee = (int) ($rate['fee_cents'] ?? 0);
                $shipments[$key] = [
                    'warehouse_id' => $warehouse?->id,
                    'warehouse_name' => $warehouse?->name ?? '默认仓库',
                    'shipping_carrier_id' => $rate['shipping_carrier_id'] ?? null,
                    'shipping_carrier_name' => $rate['shipping_carrier_name'] ?? null,
                    'base_fee_cents' => 0,
                    'extra_fee_cents' => 0,
                    'fee_cents' => 0,
                    'shipping_charge_enabled' => false,
                    'available_carriers' => $warehouse ? $this->warehouseRateOptions($warehouse, $province) : [],
                    'items' => [],
                ];
            }

            $extraFee = $this->productExtraFee($item);
            $shippingChargeEnabled = $this->itemHasConfiguredShippingCharge($item);
            $shipments[$key]['items'][] = $this->itemSummary($item);
            $shipments[$key]['extra_fee_cents'] += $extraFee;
            $shipments[$key]['shipping_charge_enabled'] = $shipments[$key]['shipping_charge_enabled'] || $shippingChargeEnabled;
            $shipments[$key]['base_fee_cents'] = $shipments[$key]['shipping_charge_enabled'] ? $baseFee : 0;
            $shipments[$key]['fee_cents'] = $shipments[$key]['base_fee_cents'] + $shipments[$key]['extra_fee_cents'];
            $itemWarehouseMap[(int) $item['variant']->id] = $warehouse?->id;
        }

        $quote = $this->buildQuote($province, array_values($shipments));
        $quote['item_warehouse_map'] = $itemWarehouseMap;

        return $quote;
    }

    /**
     * @param  array<int, array<string, mixed>>  $shipments
     * @return array<string, mixed>
     */
    private function buildQuote(?string $province, array $shipments): array
    {
        $quotedFees = array_map(fn (array $shipment): int => (int) $shipment['fee_cents'], $shipments);
        $shippingFee = $quotedFees === [] ? 0 : max($quotedFees);
        $chargedShipmentIndex = $shippingFee > 0 ? array_search($shippingFee, $quotedFees, true) : null;

        foreach ($shipments as $index => &$shipment) {
            $shipment['quoted_fee_cents'] = (int) $shipment['fee_cents'];
            $shipment['charges_order_shipping'] = $chargedShipmentIndex !== null && $index === $chargedShipmentIndex;
            $shipment['fee_cents'] = $shipment['charges_order_shipping'] ? $shippingFee : 0;
        }
        unset($shipment);

        $isMultiWarehouse = count($shipments) > 1;
        $notice = null;

        if ($isMultiWarehouse) {
            $notice = '订单中的商品需要分批发货，整单仅收取一次邮费。';
        }

        $itemWarehouseMap = [];

        foreach ($shipments as $shipment) {
            foreach ($shipment['items'] as $item) {
                if ($item['product_variant_id']) {
                    $itemWarehouseMap[(int) $item['product_variant_id']] = $shipment['warehouse_id'];
                }
            }
        }

        $carrierIds = collect($shipments)->pluck('shipping_carrier_id')->filter()->unique()->values();

        return [
            'province' => $province,
            'shipping_fee_cents' => $shippingFee,
            'shipping_carrier_id' => $carrierIds->count() === 1 ? $carrierIds->first() : null,
            'is_multi_warehouse' => $isMultiWarehouse,
            'shipments' => $shipments,
            'item_warehouse_map' => $itemWarehouseMap,
            'notice' => $notice,
        ];
    }

    /**
     * @param  Collection<int, array{product: Product, variant: mixed, quantity: int}>  $items
     * @return array<string, mixed>
     */
    private function shipmentFor(Warehouse $warehouse, Collection $items, ?string $province, array $selectedCarriers): array
    {
        $rate = $this->selectedRateForWarehouse($warehouse, $province, $selectedCarriers[(int) $warehouse->id] ?? null);
        $shippingChargeEnabled = $items->contains(fn (array $item): bool => $this->itemHasConfiguredShippingCharge($item));
        $baseFee = $shippingChargeEnabled ? (int) ($rate['fee_cents'] ?? 0) : 0;
        $extraFee = (int) $items->sum(fn (array $item): int => $this->productExtraFee($item));

        return [
            'warehouse_id' => $warehouse->id,
            'warehouse_name' => $warehouse->name,
            'shipping_carrier_id' => $rate['shipping_carrier_id'] ?? null,
            'shipping_carrier_name' => $rate['shipping_carrier_name'] ?? null,
            'base_fee_cents' => $baseFee,
            'extra_fee_cents' => $extraFee,
            'fee_cents' => $baseFee + $extraFee,
            'shipping_charge_enabled' => $shippingChargeEnabled,
            'available_carriers' => $this->warehouseRateOptions($warehouse, $province),
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
        return (int) ($this->selectedRateForWarehouse($warehouse, $province)['fee_cents'] ?? 0);
    }

    /**
     * @return array<int, array{shipping_carrier_id:?int, shipping_carrier_name:string, rate_id:int, name:string, fee_cents:int}>
     */
    private function warehouseRateOptions(Warehouse $warehouse, ?string $province): array
    {
        $rates = $warehouse->shippingRates
            ->where('is_active', true)
            ->sortBy('sort_order')
            ->values();

        $matched = blank($province)
            ? $rates
            : $rates
                ->filter(fn (WarehouseShippingRate $rate): bool => ! $rate->is_default && $rate->matchesProvince($province))
                ->values();

        if ($matched->isEmpty()) {
            $matched = $rates
                ->filter(fn (WarehouseShippingRate $rate): bool => (bool) $rate->is_default)
                ->values();
        }

        return $matched
            ->map(function (WarehouseShippingRate $rate): array {
                return [
                    'shipping_carrier_id' => $rate->shipping_carrier_id ? (int) $rate->shipping_carrier_id : null,
                    'shipping_carrier_name' => $rate->shippingCarrier?->name ?: $rate->name ?: '默认物流',
                    'rate_id' => (int) $rate->id,
                    'name' => (string) $rate->name,
                    'fee_cents' => (int) $rate->fee_cents,
                ];
            })
            ->unique(fn (array $rate): string => (string) ($rate['shipping_carrier_id'] ?? 'rate-'.$rate['rate_id']))
            ->values()
            ->all();
    }

    /**
     * @return array{shipping_carrier_id:?int, shipping_carrier_name:string, rate_id:int, name:string, fee_cents:int}|null
     */
    private function selectedRateForWarehouse(Warehouse $warehouse, ?string $province, ?int $selectedCarrierId = null): ?array
    {
        $options = $this->warehouseRateOptions($warehouse, $province);

        if ($options === []) {
            return null;
        }

        if ($selectedCarrierId) {
            foreach ($options as $option) {
                if ((int) ($option['shipping_carrier_id'] ?? 0) === $selectedCarrierId) {
                    return $option;
                }
            }
        }

        return $options[0];
    }

    private function warehouseHasActiveRate(Warehouse $warehouse, ?string $province): bool
    {
        return $warehouse->shippingRates
            ->where('is_active', true)
            ->contains(fn ($rate): bool => (bool) $rate->is_default || $rate->matchesProvince($province));
    }

    /**
     * @param  array{product: Product, quantity: int}  $item
     */
    private function productExtraFee(array $item): int
    {
        return (int) ($item['product']->shipping_extra_fee_cents ?? 0);
    }

    /**
     * Presale products are free to ship until a product-level shipping option is explicitly configured.
     * The default presale warehouse remains available for fulfilment assignment only.
     *
     * @param  array{product: Product}  $item
     */
    private function itemHasConfiguredShippingCharge(array $item): bool
    {
        $product = $item['product'];

        if ($product->status !== Product::STATUS_PRESALE) {
            return true;
        }

        return filled($product->presale_shipping_warehouse_id)
            || (int) $product->shipping_extra_fee_cents > 0;
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
    private function warehouseCoversAllItems(Warehouse $warehouse, Collection $items, array $stockMatrix, ?int $defaultPresaleWarehouseId): bool
    {
        foreach ($items as $item) {
            if ($item['product']->status === Product::STATUS_PRESALE) {
                $preferredWarehouseId = $this->preferredPresaleWarehouseId($item['product'], $defaultPresaleWarehouseId);

                if ($preferredWarehouseId && (int) $warehouse->id !== $preferredWarehouseId) {
                    return false;
                }

                continue;
            }

            if ($item['product']->status === Product::STATUS_CONCEPT) {
                continue;
            }

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
    private function bestWarehouseForItem(Collection $warehouses, array $item, array $stockMatrix, ?int $defaultPresaleWarehouseId, ?string $province, ?string $city): ?Warehouse
    {
        if ($item['product']->status === Product::STATUS_PRESALE) {
            $preferredWarehouseId = $this->preferredPresaleWarehouseId($item['product'], $defaultPresaleWarehouseId);

            if ($preferredWarehouseId) {
                return $warehouses->firstWhere('id', $preferredWarehouseId) ?: $warehouses->first();
            }
        }

        return $warehouses
            ->filter(function (Warehouse $warehouse) use ($item, $stockMatrix): bool {
                if (in_array($item['product']->status, [Product::STATUS_CONCEPT, Product::STATUS_PRESALE], true)) {
                    return true;
                }

                $available = $stockMatrix[(int) $warehouse->id][(int) $item['variant']->id] ?? 0;

                return $available >= (int) $item['quantity'];
            })
            ->sortByDesc(fn (Warehouse $warehouse): int => $this->warehouseAddressScore($warehouse, $province, $city))
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyQuote(?string $province): array
    {
        return [
            'province' => $province,
            'shipping_fee_cents' => 0,
            'shipping_carrier_id' => null,
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

    private function defaultPresaleWarehouseId(): ?int
    {
        $id = SiteSetting::query()->value('presale_default_warehouse_id');

        return $id ? (int) $id : null;
    }

    private function preferredPresaleWarehouseId(Product $product, ?int $defaultPresaleWarehouseId): ?int
    {
        return $product->presale_shipping_warehouse_id
            ? (int) $product->presale_shipping_warehouse_id
            : $defaultPresaleWarehouseId;
    }

    private function warehouseAddressScore(Warehouse $warehouse, ?string $province, ?string $city): int
    {
        $score = 0;
        $warehouseProvince = ChinaRegions::normalizeProvince($warehouse->province) ?? trim((string) $warehouse->province);
        $orderProvince = ChinaRegions::normalizeProvince($province) ?? trim((string) $province);

        if ($warehouseProvince !== '' && $orderProvince !== '' && $warehouseProvince === $orderProvince) {
            $score += 100;
        }

        if (trim((string) $warehouse->city) !== '' && trim((string) $city) !== '' && trim((string) $warehouse->city) === trim((string) $city)) {
            $score += 30;
        }

        return $score;
    }

    /**
     * @param  array<int|string, mixed>  $selectedCarriers
     * @return array<int, int>
     */
    private function normalizeSelectedCarriers(array $selectedCarriers): array
    {
        $normalized = [];

        foreach ($selectedCarriers as $warehouseId => $carrierId) {
            if (! is_numeric($warehouseId) || ! is_numeric($carrierId)) {
                continue;
            }

            $normalized[(int) $warehouseId] = (int) $carrierId;
        }

        return $normalized;
    }
}
