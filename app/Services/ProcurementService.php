<?php

namespace App\Services;

use App\Models\CostEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Procurement;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class ProcurementService
{
    /**
     * @return array<string, float>
     */
    public static function customsRateOptions(): array
    {
        return [
            'CN' => 0.1300,
            'JP' => 0.1000,
            'KR' => 0.1000,
            'US' => 0.0750,
            'GB' => 0.2000,
            'FR' => 0.2000,
            'DE' => 0.1900,
            'AU' => 0.1000,
            'OTHER' => 0.0000,
        ];
    }

    public function syncProcurement(Procurement $procurement): Procurement
    {
        return DB::transaction(function () use ($procurement): Procurement {
            $procurement->refresh();
            $incoming = $this->ensureIncomingProduct($procurement);
            $manualRate = (float) $procurement->customs_tax_rate;
            $customsRate = $manualRate > 0 ? $manualRate : $this->rateFor($procurement->shipping_country);
            $customsTaxCents = (int) round(($procurement->purchase_amount_cents + $procurement->shipping_amount_cents) * $customsRate);

            $procurement->updateQuietly([
                'incoming_product_id' => $incoming->id,
                'customs_tax_rate' => $customsRate,
                'customs_tax_cents' => $customsTaxCents,
            ]);

            $incoming->update([
                'incoming_quantity' => $procurement->quantity,
                'tracking_number' => $procurement->international_tracking_number,
                'tracking_url' => $procurement->tracking_url,
                'incoming_note' => $procurement->note,
            ]);

            $this->syncAutoCosts($procurement->fresh());

            return $procurement->fresh(['incomingProduct', 'costs']);
        });
    }

    /**
     * @param  array<int, array{order_item_id:int, allocated_quantity:int}>  $rows
     */
    public function syncAllocations(Procurement $procurement, array $rows): void
    {
        DB::transaction(function () use ($procurement, $rows): void {
            $procurement = $this->syncProcurement($procurement);
            $incoming = $procurement->fresh('incomingProduct')->incomingProduct;

            foreach ($rows as $row) {
                $quantity = max(0, (int) ($row['allocated_quantity'] ?? 0));
                if ($quantity <= 0) {
                    continue;
                }

                $item = OrderItem::query()
                    ->with('order')
                    ->whereKey($row['order_item_id'])
                    ->where('product_id', $procurement->product_id)
                    ->where('product_status', Product::STATUS_PRESALE)
                    ->firstOrFail();

                $quantity = min($quantity, (int) $item->quantity);
                $itemStatus = $incoming?->status === Product::STATUS_PUBLISHED
                    ? Order::STATUS_PENDING_SHIPMENT
                    : Order::STATUS_INCOMING;

                $procurement->allocations()->updateOrCreate(
                    ['order_item_id' => $item->id],
                    [
                        'user_id' => $item->order->user_id,
                        'order_id' => $item->order_id,
                        'presale_quantity' => $item->quantity,
                        'allocated_quantity' => $quantity,
                    ],
                );

                $item->update([
                    'incoming_product_id' => $procurement->incoming_product_id,
                    'warehouse_id' => $procurement->warehouse_id,
                    'status' => $itemStatus,
                ]);

                if (in_array($item->order->status, [Order::STATUS_PENDING_SHIPMENT, Order::STATUS_INCOMING], true)) {
                    $item->order->update([
                        'status' => $itemStatus,
                    ]);
                }
            }
        });
    }

    private function ensureIncomingProduct(Procurement $procurement): Product
    {
        if ($procurement->incomingProduct) {
            return $procurement->incomingProduct;
        }

        $source = $procurement->product()->with(['category', 'manufacturer', 'supplier', 'variants', 'media'])->firstOrFail();
        $baseSlug = $source->slug.'-incoming-'.$procurement->id;
        $slug = $baseSlug;
        $index = 2;

        while (Product::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$index++;
        }

        $incoming = Product::query()->create([
            'category_id' => $source->category_id,
            'manufacturer_id' => $source->manufacturer_id,
            'supplier_id' => $source->supplier_id,
            'title' => $source->title.'（进货中）',
            'slug' => $slug,
            'summary' => $source->summary,
            'description' => $source->description,
            'status' => Product::STATUS_INCOMING,
            'is_featured' => false,
            'fulfillment_type' => $source->fulfillment_type,
            'delivery_status_id' => $source->delivery_status_id,
            'sold_out_status_id' => $source->sold_out_status_id,
            'quantity_unit_id' => $source->quantity_unit_id,
            'source_product_id' => $source->id,
            'incoming_quantity' => $procurement->quantity,
            'incoming_note' => $procurement->note,
            'tracking_number' => $procurement->international_tracking_number,
            'tracking_url' => $procurement->tracking_url,
            'sort_order' => $source->sort_order,
        ]);

        foreach ($source->variants as $variant) {
            /** @var ProductVariant $variant */
            $incoming->variants()->create([
                'sku' => $variant->sku.'-PO-'.$procurement->id,
                'specs' => $variant->specs,
                'image_path' => $variant->image_path,
                'price_cents' => $variant->price_cents,
                'compare_at_price_cents' => $variant->compare_at_price_cents,
                'discount_price_cents' => $variant->discount_price_cents,
                'discount_starts_at' => $variant->discount_starts_at,
                'discount_ends_at' => $variant->discount_ends_at,
                'stock' => 0,
                'low_stock_threshold' => $variant->low_stock_threshold,
                'is_active' => false,
            ]);
        }

        foreach ($source->media as $media) {
            /** @var ProductMedia $media */
            $incoming->media()->create([
                'type' => $media->type,
                'media_kind' => $media->media_kind,
                'path' => $media->path,
                'mime_type' => $media->mime_type,
                'alt' => $media->alt,
                'sort_order' => $media->sort_order,
            ]);
        }

        return $incoming;
    }

    private function syncAutoCosts(Procurement $procurement): void
    {
        $rows = [
            CostEntry::CATEGORY_PURCHASE => ['采购成本', $procurement->purchase_amount_cents],
            CostEntry::CATEGORY_SHIPPING => ['运输成本', $procurement->shipping_amount_cents],
            CostEntry::CATEGORY_CUSTOMS => ['海关税务成本', $procurement->customs_tax_cents],
        ];

        foreach ($rows as $category => [$name, $amount]) {
            CostEntry::query()->updateOrCreate(
                [
                    'procurement_id' => $procurement->id,
                    'category' => $category,
                    'is_auto' => true,
                ],
                [
                    'created_by_id' => $procurement->created_by_id,
                    'name' => $name,
                    'amount_cents' => (int) $amount,
                    'currency_code' => 'CNY',
                    'currency_unit' => 'yuan',
                    'original_amount' => ((int) $amount) / 100,
                    'exchange_rate' => 1,
                    'country' => $procurement->shipping_country,
                    'tax_rate' => $category === CostEntry::CATEGORY_CUSTOMS ? $procurement->customs_tax_rate : null,
                    'note' => $procurement->name,
                ],
            );
        }
    }

    private function rateFor(?string $country): float
    {
        return self::customsRateOptions()[strtoupper((string) $country)] ?? 0.0;
    }

}
