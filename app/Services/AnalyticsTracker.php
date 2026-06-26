<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AnalyticsTracker
{
    public const REQUEST_PAGE_VIEW_TRACKED = 'shopweb.analytics.page_view_tracked';

    private static ?bool $analyticsTableReady = null;

    public function track(Request $request, string $event, array $data = []): void
    {
        if (! $this->isReady()) {
            return;
        }

        try {
            AnalyticsEvent::query()->create($this->payload($request, $event, $data));

            if ($event === AnalyticsEvent::PAGE_VIEW) {
                $request->attributes->set(self::REQUEST_PAGE_VIEW_TRACKED, true);
            }
        } catch (Throwable) {
            // Analytics should never block shopping, login, or checkout.
        }
    }

    /**
     * @param  iterable<int, Product>  $products
     */
    public function trackProductImpressions(Request $request, iterable $products, string $source): void
    {
        if (! $this->isReady()) {
            return;
        }

        $rows = [];
        $now = Carbon::now();

        foreach ($products as $position => $product) {
            if (! $product instanceof Product) {
                continue;
            }

            $rows[] = $this->payload($request, AnalyticsEvent::PRODUCT_IMPRESSION, [
                'product_id' => $product->id,
                'source' => $source,
                'metadata' => [
                    'position' => $position + 1,
                    'status' => $product->status,
                    'title' => $product->title,
                ],
            ], $now, true);
        }

        if ($rows === []) {
            return;
        }

        try {
            AnalyticsEvent::query()->insert($rows);
        } catch (Throwable) {
            // Analytics should never block page rendering.
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
        if (self::$analyticsTableReady !== null) {
            return self::$analyticsTableReady;
        }

        try {
            if (Schema::hasTable('analytics_events')) {
                return self::$analyticsTableReady = true;
            }

            return false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(Request $request, string $event, array $data = [], ?Carbon $now = null, bool $encodeMetadata = false): array
    {
        $now ??= Carbon::now();
        $metadata = $data['metadata'] ?? null;

        if ($encodeMetadata && $metadata !== null) {
            $metadata = json_encode($metadata, JSON_UNESCAPED_UNICODE);
        }

        return [
            'event' => $event,
            'user_id' => $request->user()?->id,
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'product_id' => $data['product_id'] ?? null,
            'product_variant_id' => $data['product_variant_id'] ?? null,
            'order_id' => $data['order_id'] ?? null,
            'source' => $data['source'] ?? null,
            'surface' => $data['surface'] ?? $this->surface($request),
            'visitor_type' => $this->visitorType($request),
            'device_type' => $data['device_type'] ?? $this->deviceType($request),
            'path' => mb_substr($request->path(), 0, 1024),
            'referrer' => mb_substr((string) $request->headers->get('referer'), 0, 1024) ?: null,
            'ip_hash' => hash('sha256', (string) $request->ip()),
            'ip_region' => $this->ipRegion($request),
            'ip_country' => $this->ipCountry($request),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512) ?: null,
            'quantity' => $data['quantity'] ?? null,
            'amount_cents' => $data['amount_cents'] ?? null,
            'metadata' => $metadata,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function surface(Request $request): string
    {
        return $request->is('admin') || $request->is('admin/*') ? 'admin' : 'frontend';
    }

    private function visitorType(Request $request): string
    {
        $user = $request->user();

        if (! $user) {
            return 'guest';
        }

        return $user->isBackofficeUser() ? 'staff' : 'customer';
    }

    private function deviceType(Request $request): string
    {
        $agent = strtolower((string) $request->userAgent());

        if ($agent === '') {
            return 'unknown';
        }

        if (str_contains($agent, 'ipad') || str_contains($agent, 'tablet')) {
            return 'tablet';
        }

        if (str_contains($agent, 'mobile') || str_contains($agent, 'iphone') || str_contains($agent, 'android')) {
            return 'mobile';
        }

        return 'desktop';
    }

    private function ipRegion(Request $request): string
    {
        $country = $this->ipCountry($request);
        $region = $this->headerValue($request, [
            'cf-ipcountry-region',
            'x-vercel-ip-country-region',
            'x-appengine-region',
            'x-region',
            'x-real-region',
        ]);
        $city = $this->headerValue($request, [
            'cf-ipcity',
            'x-vercel-ip-city',
            'x-appengine-city',
            'x-city',
            'x-real-city',
        ]);

        if ($this->isPrivateIp((string) $request->ip())) {
            return '本地/内网';
        }

        $parts = array_values(array_filter([$country, $region, $city], fn (?string $value): bool => filled($value)));

        return $parts === [] ? '未知地区' : mb_substr(implode(' / ', array_unique($parts)), 0, 120);
    }

    private function ipCountry(Request $request): ?string
    {
        if ($this->isPrivateIp((string) $request->ip())) {
            return '本地/内网';
        }

        return $this->headerValue($request, [
            'cf-ipcountry',
            'x-vercel-ip-country',
            'x-appengine-country',
            'x-country',
            'x-real-country',
        ]);
    }

    /**
     * @param  array<int, string>  $headers
     */
    private function headerValue(Request $request, array $headers): ?string
    {
        foreach ($headers as $header) {
            $value = trim((string) $request->headers->get($header, ''));

            if ($value !== '' && ! in_array(strtolower($value), ['xx', 'unknown', 'null'], true)) {
                return mb_substr($value, 0, 120);
            }
        }

        return null;
    }

    private function isPrivateIp(string $ip): bool
    {
        if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
