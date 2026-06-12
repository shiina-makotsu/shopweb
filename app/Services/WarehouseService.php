<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\InventoryMovement;
use App\Models\Procurement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseMovement;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\DB;

class WarehouseService
{
    public function receiveProcurement(Procurement $procurement, ?User $actor = null, ?string $note = null): void
    {
        DB::transaction(function () use ($procurement, $actor, $note): void {
            $procurement = app(ProcurementService::class)->syncProcurement($procurement)->fresh(['incomingProduct.variants', 'product.variants']);
            $product = $procurement->incomingProduct ?: $procurement->product;
            $variant = $product?->variants()->oldest('id')->first();
            $quantity = (int) $procurement->quantity;
            $warehouse = $procurement->warehouse ?: $this->defaultWarehouse();

            $stock = $this->stockFor($product, $variant, $warehouse, $procurement, $procurement->name);
            $this->move($stock, $quantity, WarehouseMovement::TYPE_RECEIVED, [
                'warehouse_id' => $warehouse?->id,
                'procurement_id' => $procurement->id,
                'product_id' => $product?->id,
                'product_variant_id' => $variant?->id,
                'user_id' => $actor?->id,
                'note' => $note ?: '采购确认入库：'.$procurement->name,
                'sync_variant_stock' => true,
                'sync_variant_stock_reason' => 'warehouse_received',
            ]);

            $procurement->update([
                'status' => Procurement::STATUS_RECEIVED,
                'received_at' => now(),
                'warehouse_note' => $note ?: $procurement->warehouse_note,
            ]);

            $variant?->update(['is_active' => true]);

            if ($product?->status === Product::STATUS_INCOMING) {
                $product->update(['status' => Product::STATUS_PUBLISHED]);
            }

            OrderItem::query()
                ->where('incoming_product_id', $product?->id)
                ->where('status', Order::STATUS_INCOMING)
                ->update(['status' => Order::STATUS_PENDING_SHIPMENT]);

            Order::query()
                ->whereIn('status', [Order::STATUS_INCOMING, Order::STATUS_PENDING_SHIPMENT])
                ->whereHas('items', fn ($query) => $query->where('incoming_product_id', $product?->id))
                ->whereDoesntHave('items', fn ($query) => $query->where('status', Order::STATUS_INCOMING))
                ->update(['status' => Order::STATUS_PENDING_SHIPMENT]);
        });
    }

    public function shipOrder(Order $order, ?User $actor = null, ?string $note = null): void
    {
        DB::transaction(function () use ($order, $actor, $note): void {
            $order->loadMissing('items.product', 'items.incomingProduct');

            foreach ($order->items as $item) {
                $product = $item->incomingProduct ?: $item->product;
                $variant = $this->variantForItem($item, $product);
                $warehouse = $item->warehouse ?: $this->defaultWarehouse();
                $stock = $this->stockFor($product, $variant, $warehouse, null, $item->product_title);

                $this->move($stock, -1 * (int) $item->quantity, WarehouseMovement::TYPE_SHIPPED, [
                    'warehouse_id' => $warehouse?->id,
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'product_id' => $product?->id,
                    'product_variant_id' => $variant?->id,
                    'user_id' => $actor?->id,
                    'note' => $note ?: '订单发货自动出库：'.$order->order_number,
                    'sync_variant_stock' => $item->product_status !== Product::STATUS_PUBLISHED,
                    'sync_variant_stock_reason' => 'warehouse_shipped',
                ]);
            }
        });
    }

    public function returnOrder(Order $order, ?User $actor = null, ?string $note = null): void
    {
        DB::transaction(function () use ($order, $actor, $note): void {
            $order->loadMissing('items.product', 'items.incomingProduct');

            foreach ($order->items as $item) {
                $product = $item->incomingProduct ?: $item->product;
                $variant = $this->variantForItem($item, $product);
                $warehouse = $item->warehouse ?: $this->defaultWarehouse();
                $stock = $this->stockFor($product, $variant, $warehouse, null, $item->product_title);

                $this->move($stock, (int) $item->quantity, WarehouseMovement::TYPE_RETURNED, [
                    'warehouse_id' => $warehouse?->id,
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'product_id' => $product?->id,
                    'product_variant_id' => $variant?->id,
                    'user_id' => $actor?->id,
                    'note' => $note ?: '拒收/退回确认入库：'.$order->order_number,
                    'sync_variant_stock' => true,
                    'sync_variant_stock_reason' => 'warehouse_returned',
                ]);
            }
        });
    }

    public function adjust(WarehouseStock $stock, int $delta, string $type, ?User $actor = null, ?string $note = null): void
    {
        $type = array_key_exists($type, WarehouseMovement::typeOptions())
            ? $type
            : WarehouseMovement::TYPE_ADJUSTMENT;

        $this->move($stock, $delta, $type, [
            'warehouse_id' => $stock->warehouse_id,
            'procurement_id' => $stock->procurement_id,
            'product_id' => $stock->product_id,
            'product_variant_id' => $stock->product_variant_id,
            'user_id' => $actor?->id,
            'note' => $note,
            'sync_variant_stock' => true,
            'sync_variant_stock_reason' => 'warehouse_'.$type,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function move(WarehouseStock $stock, int $delta, string $type, array $context = []): WarehouseMovement
    {
        return DB::transaction(function () use ($stock, $delta, $type, $context): WarehouseMovement {
            $stock = WarehouseStock::query()->whereKey($stock->id)->lockForUpdate()->firstOrFail();
            $stock->increment('quantity', $delta);
            $stock->refresh();

            $movement = WarehouseMovement::query()->create([
                'warehouse_stock_id' => $stock->id,
                'warehouse_id' => $context['warehouse_id'] ?? $stock->warehouse_id,
                'procurement_id' => $context['procurement_id'] ?? null,
                'order_id' => $context['order_id'] ?? null,
                'order_item_id' => $context['order_item_id'] ?? null,
                'product_id' => $context['product_id'] ?? $stock->product_id,
                'product_variant_id' => $context['product_variant_id'] ?? $stock->product_variant_id,
                'user_id' => $context['user_id'] ?? null,
                'type' => $type,
                'delta' => $delta,
                'quantity_after' => (int) $stock->quantity,
                'note' => $context['note'] ?? null,
            ]);

            if (($context['sync_variant_stock'] ?? false) && $stock->product_variant_id) {
                $this->syncVariantStockDelta($stock, $delta, $context, $movement);
            }

            return $movement;
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function syncVariantStockDelta(WarehouseStock $stock, int $delta, array $context, WarehouseMovement $movement): void
    {
        $variant = ProductVariant::query()
            ->whereKey($stock->product_variant_id)
            ->lockForUpdate()
            ->first();

        if (! $variant) {
            return;
        }

        $oldStock = (int) $variant->stock;
        $newStock = max(0, $oldStock + $delta);

        if ($newStock === $oldStock) {
            return;
        }

        $variant->forceFill(['stock' => $newStock])->save();

        InventoryMovement::query()->create([
            'product_variant_id' => $variant->id,
            'order_id' => $context['order_id'] ?? null,
            'user_id' => $context['user_id'] ?? null,
            'delta' => $newStock - $oldStock,
            'stock_after' => $newStock,
            'reason' => $context['sync_variant_stock_reason'] ?? 'warehouse_sync',
            'note' => trim(($context['note'] ?? '').' / 仓库流水 #'.$movement->id, ' /'),
        ]);

        $product = $variant->product()->first();
        if ($product?->status === Product::STATUS_PUBLISHED && $product->activeVariants()->sum('stock') <= 0) {
            $product->update(['status' => Product::STATUS_SOLD_OUT]);
        } elseif ($product?->status === Product::STATUS_SOLD_OUT && $product->activeVariants()->sum('stock') > 0) {
            $product->update(['status' => Product::STATUS_PUBLISHED]);
        }
    }

    private function stockFor(?Product $product, ?ProductVariant $variant, ?Warehouse $warehouse = null, ?Procurement $procurement = null, ?string $fallbackName = null): WarehouseStock
    {
        $warehouse ??= $this->defaultWarehouse();

        $query = WarehouseStock::query()
            ->where('warehouse_id', $warehouse?->id)
            ->where('product_id', $product?->id)
            ->where('product_variant_id', $variant?->id);

        if ($procurement) {
            $query->where('procurement_id', $procurement->id);
        }

        $stock = $query->first();

        if ($stock) {
            return $stock;
        }

        return WarehouseStock::query()->create([
            'warehouse_id' => $warehouse?->id,
            'product_id' => $product?->id,
            'product_variant_id' => $variant?->id,
            'procurement_id' => $procurement?->id,
            'name' => $product?->title ?: ($fallbackName ?: '未关联商品'),
            'sku' => $variant?->sku,
            'quantity' => 0,
            'reserved_quantity' => 0,
            'note' => $procurement ? '由采购入库创建' : null,
        ]);
    }

    private function variantForItem(OrderItem $item, ?Product $product): ?ProductVariant
    {
        if ($item->incoming_product_id && $product?->id === $item->incoming_product_id) {
            return $product->variants()->oldest('id')->first();
        }

        if ($item->product_variant_id) {
            return ProductVariant::query()->find($item->product_variant_id);
        }

        return $product?->variants()->oldest('id')->first();
    }

    private function defaultWarehouse(): Warehouse
    {
        return Warehouse::query()->firstOrCreate(
            ['name' => '默认仓库'],
            ['country' => '中国', 'is_active' => true, 'sort_order' => 0],
        );
    }
}
