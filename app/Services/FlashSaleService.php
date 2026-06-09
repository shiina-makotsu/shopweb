<?php

namespace App\Services;

use App\Models\FlashSale;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FlashSaleService
{
    public function createReservedOrder(User $user, FlashSale $flashSale, int $quantity = 1): Order
    {
        return DB::transaction(function () use ($user, $flashSale, $quantity): Order {
            $flashSale = FlashSale::query()
                ->whereKey($flashSale->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $flashSale->isAvailable()) {
                throw ValidationException::withMessages(['flash_sale' => '本场秒杀名额已抢完或活动已结束。']);
            }

            $quantity = min(max(1, $quantity), $flashSale->availableQuantity());
            $product = $flashSale->product()->firstOrFail();
            $subtotalCents = (int) $flashSale->sale_price_cents * $quantity;

            $order = Order::query()->create([
                'user_id' => $user->id,
                'order_number' => $this->nextOrderNumber(),
                'status' => Order::STATUS_PENDING_PAYMENT,
                'payment_status' => Order::PAYMENT_PENDING,
                'subtotal_cents' => $subtotalCents,
                'discount_cents' => 0,
                'total_cents' => $subtotalCents,
                'contact_name' => $user->name,
                'contact_phone' => '',
                'contact_email' => $user->email,
                'requires_shipping' => $product->requiresShipping(),
            ]);

            $order->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => null,
                'product_title' => $product->title,
                'product_status' => $product->status,
                'variant_sku' => '待选择规格',
                'variant_specs' => null,
                'unit_price_cents' => (int) $flashSale->sale_price_cents,
                'quantity' => $quantity,
                'line_total_cents' => $subtotalCents,
                'status' => Order::STATUS_PENDING_PAYMENT,
                'flash_sale_id' => $flashSale->id,
            ]);

            $flashSale->increment('sold_quantity', $quantity);

            return $order->load('items');
        });
    }

    public function completeOrderSelection(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data): Order {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $item = $order->items()->whereNotNull('flash_sale_id')->firstOrFail();
            $flashSale = FlashSale::query()->whereKey($item->flash_sale_id)->firstOrFail();

            $variant = $flashSale->eligibleVariants()
                ->whereKey($data['product_variant_id'])
                ->lockForUpdate()
                ->first();

            if (! $variant || $variant->stock < $item->quantity) {
                throw ValidationException::withMessages(['product_variant_id' => '该规格库存不足，请换一个规格。']);
            }

            $item->update([
                'product_variant_id' => $variant->id,
                'variant_sku' => $variant->sku,
                'variant_specs' => $variant->specs,
            ]);

            $order->update([
                'contact_name' => $data['contact_name'],
                'contact_phone' => $data['contact_phone'],
                'contact_email' => $data['contact_email'] ?? null,
                'shipping_address' => $data['shipping_address'] ?? null,
                'customer_note' => $data['customer_note'] ?? null,
            ]);

            return $order->fresh(['items']);
        });
    }

    public function variantsFor(Order $order)
    {
        $item = $order->items()->whereNotNull('flash_sale_id')->first();

        if (! $item) {
            return ProductVariant::query()->whereRaw('1 = 0')->get();
        }

        return FlashSale::query()
            ->findOrFail($item->flash_sale_id)
            ->eligibleVariants()
            ->orderBy('price_cents')
            ->get();
    }

    private function nextOrderNumber(): string
    {
        do {
            $number = 'SW'.now()->format('YmdHis').str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }
}
