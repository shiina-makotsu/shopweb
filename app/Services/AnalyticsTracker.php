<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AnalyticsTracker
{
    public function track(Request $request, string $event, array $data = []): void
    {
        if (! $this->isReady()) {
            return;
        }

        try {
            AnalyticsEvent::query()->create([
                'event' => $event,
                'user_id' => $request->user()?->id,
                'session_id' => $request->hasSession() ? $request->session()->getId() : null,
                'product_id' => $data['product_id'] ?? null,
                'product_variant_id' => $data['product_variant_id'] ?? null,
                'order_id' => $data['order_id'] ?? null,
                'source' => $data['source'] ?? null,
                'path' => mb_substr($request->path(), 0, 1024),
                'referrer' => mb_substr((string) $request->headers->get('referer'), 0, 1024) ?: null,
                'ip_hash' => hash('sha256', (string) $request->ip()),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 512) ?: null,
                'quantity' => $data['quantity'] ?? null,
                'amount_cents' => $data['amount_cents'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);
        } catch (Throwable) {
            // Analytics should never block shopping, login, or checkout.
        }
    }

    /**
     * @param  iterable<int, Product>  $products
     */
    public function trackProductImpressions(Request $request, iterable $products, string $source): void
    {
        foreach ($products as $position => $product) {
            if (! $product instanceof Product) {
                continue;
            }

            $this->track($request, AnalyticsEvent::PRODUCT_IMPRESSION, [
                'product_id' => $product->id,
                'source' => $source,
                'metadata' => [
                    'position' => $position + 1,
                    'status' => $product->status,
                    'title' => $product->title,
                ],
            ]);
        }
    }

    public function trackVariant(Request $request, string $event, ProductVariant $variant, int $quantity, ?string $source = null): void
    {
        $variant->loadMissing('product');

        $this->track($request, $event, [
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'source' => $source,
            'quantity' => max(1, $quantity),
            'amount_cents' => $variant->effectivePriceCents() * max(1, $quantity),
            'metadata' => [
                'sku' => $variant->sku,
                'product_title' => $variant->product?->title,
            ],
        ]);
    }

    public function trackOrderCreated(Request $request, Order $order): void
    {
        $this->track($request, AnalyticsEvent::ORDER_CREATED, [
            'order_id' => $order->id,
            'amount_cents' => (int) $order->total_cents,
            'metadata' => [
                'order_number' => $order->order_number,
                'payment_method' => $order->payment_method,
                'item_count' => $order->items()->sum('quantity'),
            ],
        ]);

        $order->loadMissing('items');
        foreach ($order->items as $item) {
            $this->track($request, AnalyticsEvent::ORDER_CREATED, [
                'order_id' => $order->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'quantity' => (int) $item->quantity,
                'amount_cents' => (int) $item->line_total_cents,
                'metadata' => [
                    'order_number' => $order->order_number,
                    'product_title' => $item->product_title,
                    'sku' => $item->variant_sku,
                ],
            ]);
        }
    }

    private function isReady(): bool
    {
        try {
            return Schema::hasTable('analytics_events');
        } catch (Throwable) {
            return false;
        }
    }
}
