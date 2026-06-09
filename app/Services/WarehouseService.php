<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Procurement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
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

            $stock = $this->stockFor($product, $variant, $procurement, $procurement->name);
            $this->move($stock, $quantity, WarehouseMovement::TYPE_RECEIVED, [
                'procurement_id' => $procurement->id,
                'product_id' => $product?->id,
                'product_variant_id' => $variant?->id,
                'user_id' => $actor?->id,
                'note' => $note ?: '采购确认入库：'.$procurement->name,
            ]);

            $procurement->update([
                'status' => Procurement::STATUS_RECEIVED,
                'received_at' => now(),
                'warehouse_note' => $note ?: $procurement->warehouse_note,
            ]);
        });
    }

    public function shipOrder(Order $order, ?User $actor = null, ?string $note = null): void
    {
        DB::transaction(function () use ($order, $actor, $note): void {
            $order->loadMissing('items.product', 'items.incomingProduct');

            foreach ($order->items as $item) {
                $product = $item->incomingProduct ?: $item->product;
                $variant = $this->variantForItem($item, $product);
                $stock = $this->stockFor($product, $variant, null, $item->product_title);

                $this->move($stock, -1 * (int) $item->quantity, WarehouseMovement::TYPE_SHIPPED, [
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'product_id' => $product?->id,
                    'product_variant_id' => $variant?->id,
                    'user_id' => $actor?->id,
                    'note' => $note ?: '订单发货自动出库：'.$order->order_number,
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
                $stock = $this->stockFor($product, $variant, null, $item->product_title);

                $this->move($stock, (int) $item->quantity, WarehouseMovement::TYPE_RETURNED, [
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'product_id' => $product?->id,
                    'product_variant_id' => $variant?->id,
                    'user_id' => $actor?->id,
                    'note' => $note ?: '拒收/退回确认入库：'.$order->order_number,
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
            'procurement_id' => $stock->procurement_id,
            'product_id' => $stock->product_id,
            'product_variant_id' => $stock->product_variant_id,
            'user_id' => $actor?->id,
            'note' => $note,
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

            return WarehouseMovement::query()->create([
                'warehouse_stock_id' => $stock->id,
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
        });
    }

    private function stockFor(?Product $product, ?ProductVariant $variant, ?Procurement $procurement = null, ?string $fallbackName = null): WarehouseStock
    {
        $query = WarehouseStock::query()
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
}
