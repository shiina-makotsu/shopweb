<?php

namespace App\Support;

use App\Models\CouponRedemption;
use App\Models\AnalyticsEvent;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportMetrics
{
    /**
     * @return array<string, string|int>
     */
    public function conversionFunnel(): array
    {
        $events = fn (string $event): int => $this->eventCount($event);

        return [
            '总访问量' => $events(AnalyticsEvent::PAGE_VIEW),
            '商品曝光' => $events(AnalyticsEvent::PRODUCT_IMPRESSION),
            '商品详情访问' => $events(AnalyticsEvent::PRODUCT_VIEW),
            '加购次数' => $events(AnalyticsEvent::ADD_TO_CART),
            '立即购买' => $events(AnalyticsEvent::BUY_NOW),
            '进入结算' => $events(AnalyticsEvent::CHECKOUT_VIEW),
            '创建订单' => Order::query()->count(),
            '已确认付款' => Order::query()->where('payment_status', Order::PAYMENT_CONFIRMED)->count(),
        ];
    }

    /**
     * @return Collection<int, array{product:string,status:string,impressions:int,views:int,adds:int,buy_now:int,orders:int,paid_orders:int,view_rate:string,cart_rate:string,order_rate:string}>
     */
    public function productConversions(int $limit = 20): Collection
    {
        $events = DB::table('analytics_events')
            ->select([
                'product_id',
                DB::raw("sum(case when event = '".AnalyticsEvent::PRODUCT_IMPRESSION."' then 1 else 0 end) as impressions"),
                DB::raw("sum(case when event = '".AnalyticsEvent::PRODUCT_VIEW."' then 1 else 0 end) as views"),
                DB::raw("sum(case when event = '".AnalyticsEvent::ADD_TO_CART."' then 1 else 0 end) as adds"),
                DB::raw("sum(case when event = '".AnalyticsEvent::BUY_NOW."' then 1 else 0 end) as buy_now"),
                DB::raw("sum(case when event = '".AnalyticsEvent::ORDER_CREATED."' then 1 else 0 end) as order_events"),
            ])
            ->whereNotNull('product_id')
            ->groupBy('product_id');

        $paidOrders = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', Order::PAYMENT_CONFIRMED)
            ->select([
                'order_items.product_id',
                DB::raw('count(distinct orders.id) as paid_order_count'),
            ])
            ->groupBy('order_items.product_id');

        return DB::table('products')
            ->leftJoinSub($events, 'events', 'products.id', '=', 'events.product_id')
            ->leftJoinSub($paidOrders, 'paid_orders', 'products.id', '=', 'paid_orders.product_id')
            ->select([
                'products.title',
                'products.status',
                DB::raw('coalesce(events.impressions, 0) as impressions'),
                DB::raw('coalesce(events.views, 0) as views'),
                DB::raw('coalesce(events.adds, 0) as adds'),
                DB::raw('coalesce(events.buy_now, 0) as buy_now'),
                DB::raw('coalesce(events.order_events, 0) as order_events'),
                DB::raw('coalesce(paid_orders.paid_order_count, 0) as paid_order_count'),
            ])
            ->orderByDesc(DB::raw('coalesce(events.impressions, 0) + coalesce(events.views, 0) + coalesce(events.adds, 0) + coalesce(events.order_events, 0)'))
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'product' => $row->title,
                'status' => Product::statusOptions()[$row->status] ?? (string) $row->status,
                'impressions' => (int) $row->impressions,
                'views' => (int) $row->views,
                'adds' => (int) $row->adds,
                'buy_now' => (int) $row->buy_now,
                'orders' => (int) $row->order_events,
                'paid_orders' => (int) $row->paid_order_count,
                'view_rate' => $this->percent((int) $row->views, (int) $row->impressions),
                'cart_rate' => $this->percent((int) $row->adds + (int) $row->buy_now, max(1, (int) $row->views)),
                'order_rate' => $this->percent((int) $row->order_events, max(1, (int) $row->views)),
            ]);
    }

    /**
     * @return Collection<int, array{source:string,impressions:int,views:int,adds:int,orders:int}>
     */
    public function trafficSources(int $limit = 12): Collection
    {
        return DB::table('analytics_events')
            ->select([
                DB::raw("coalesce(nullif(source, ''), '未标记') as source_name"),
                DB::raw("sum(case when event = '".AnalyticsEvent::PRODUCT_IMPRESSION."' then 1 else 0 end) as impressions"),
                DB::raw("sum(case when event = '".AnalyticsEvent::PRODUCT_VIEW."' then 1 else 0 end) as views"),
                DB::raw("sum(case when event = '".AnalyticsEvent::ADD_TO_CART."' then 1 else 0 end) as adds"),
                DB::raw("sum(case when event = '".AnalyticsEvent::ORDER_CREATED."' then 1 else 0 end) as orders"),
            ])
            ->groupBy(DB::raw("coalesce(nullif(source, ''), '未标记')"))
            ->orderByDesc(DB::raw('count(*)'))
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'source' => $row->source_name,
                'impressions' => (int) $row->impressions,
                'views' => (int) $row->views,
                'adds' => (int) $row->adds,
                'orders' => (int) $row->orders,
            ]);
    }

    /**
     * @return array<string, string|int>
     */
    public function salesSummary(): array
    {
        $confirmed = fn () => Order::query()->where('payment_status', Order::PAYMENT_CONFIRMED);

        return [
            '今日销售' => Money::format((int) $confirmed()->whereBetween('paid_at', [now()->startOfDay(), now()->endOfDay()])->sum('total_cents')),
            '本月销售' => Money::format((int) $confirmed()->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('total_cents')),
            '确认收款订单' => $confirmed()->count(),
            '待审核付款' => Order::query()->where('payment_status', Order::PAYMENT_SUBMITTED)->count(),
            '待付款订单' => Order::query()->where('status', Order::STATUS_PENDING_PAYMENT)->count(),
            '已取消订单' => Order::query()->where('status', Order::STATUS_CANCELLED)->count(),
        ];
    }

    /**
     * @return Collection<int, array{sku:string,product:string,stock:int,threshold:int}>
     */
    public function lowStockVariants(int $limit = 10): Collection
    {
        return ProductVariant::query()
            ->with('product')
            ->whereHas('product', fn ($query) => $query->whereNotIn('fulfillment_type', [
                Product::FULFILLMENT_ONLINE,
                Product::FULFILLMENT_CONTACT_LEGACY,
            ]))
            ->whereColumn('stock', '<=', 'low_stock_threshold')
            ->orderBy('stock')
            ->limit($limit)
            ->get()
            ->map(fn (ProductVariant $variant): array => [
                'sku' => $variant->sku,
                'product' => $variant->product?->title ?? '-',
                'stock' => $variant->stock,
                'threshold' => $variant->low_stock_threshold,
            ]);
    }

    /**
     * @return Collection<int, array{name:string,email:string,orders:int,total:string}>
     */
    public function topCustomers(int $limit = 10): Collection
    {
        return User::query()
            ->where('role', 'customer')
            ->withCount('orders')
            ->withSum('orders as orders_total_cents', 'total_cents')
            ->orderByDesc('orders_total_cents')
            ->limit($limit)
            ->get()
            ->map(fn (User $user): array => [
                'name' => $user->name,
                'email' => $user->email,
                'orders' => (int) $user->orders_count,
                'total' => Money::format((int) ($user->orders_total_cents ?? 0)),
            ]);
    }

    /**
     * @return Collection<int, array{code:string,name:string,confirmed:int,discount:string}>
     */
    public function couponUsage(int $limit = 10): Collection
    {
        return DB::table('coupons')
            ->leftJoin('coupon_redemptions', function ($join): void {
                $join->on('coupons.id', '=', 'coupon_redemptions.coupon_id')
                    ->where('coupon_redemptions.status', '=', CouponRedemption::STATUS_CONFIRMED);
            })
            ->select([
                'coupons.code',
                'coupons.name',
                DB::raw('count(coupon_redemptions.id) as confirmed_count'),
                DB::raw('coalesce(sum(coupon_redemptions.discount_cents), 0) as discount_total_cents'),
            ])
            ->groupBy('coupons.id', 'coupons.code', 'coupons.name')
            ->orderByDesc('confirmed_count')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'code' => $row->code,
                'name' => $row->name,
                'confirmed' => (int) $row->confirmed_count,
                'discount' => Money::format((int) $row->discount_total_cents),
            ]);
    }

    /**
     * @return Collection<int, array{product:string,sku:string,quantity:int,total:string}>
     */
    public function productSales(int $limit = 10): Collection
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', Order::PAYMENT_CONFIRMED)
            ->select([
                'order_items.product_title',
                'order_items.variant_sku',
                DB::raw('sum(order_items.quantity) as quantity_sum'),
                DB::raw('sum(order_items.line_total_cents) as total_cents_sum'),
            ])
            ->groupBy('order_items.product_title', 'order_items.variant_sku')
            ->orderByDesc('total_cents_sum')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'product' => $row->product_title,
                'sku' => $row->variant_sku,
                'quantity' => (int) $row->quantity_sum,
                'total' => Money::format((int) $row->total_cents_sum),
            ]);
    }

    /**
     * @return Collection<int, array{category:string,items:int,quantity:int,total:string}>
     */
    public function categorySales(int $limit = 10): Collection
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('orders.payment_status', Order::PAYMENT_CONFIRMED)
            ->select([
                DB::raw("coalesce(categories.name, '未分类') as category_name"),
                DB::raw('count(order_items.id) as item_count'),
                DB::raw('sum(order_items.quantity) as quantity_sum'),
                DB::raw('sum(order_items.line_total_cents) as total_cents_sum'),
            ])
            ->groupBy('categories.name')
            ->orderByDesc('total_cents_sum')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'category' => $row->category_name,
                'items' => (int) $row->item_count,
                'quantity' => (int) $row->quantity_sum,
                'total' => Money::format((int) $row->total_cents_sum),
            ]);
    }

    /**
     * @return Collection<int, array{product:string,want:int,considering:int,not_now:int,total:int}>
     */
    public function intentVotes(int $limit = 10): Collection
    {
        return DB::table('products')
            ->leftJoin('product_intent_votes', 'products.id', '=', 'product_intent_votes.product_id')
            ->select([
                'products.title',
                DB::raw("sum(case when product_intent_votes.intent = 'want' then 1 else 0 end) as want_count"),
                DB::raw("sum(case when product_intent_votes.intent = 'considering' then 1 else 0 end) as considering_count"),
                DB::raw("sum(case when product_intent_votes.intent = 'not_now' then 1 else 0 end) as not_now_count"),
                DB::raw('count(product_intent_votes.id) as total_count'),
            ])
            ->groupBy('products.id', 'products.title')
            ->orderByDesc('total_count')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'product' => $row->title,
                'want' => (int) $row->want_count,
                'considering' => (int) $row->considering_count,
                'not_now' => (int) $row->not_now_count,
                'total' => (int) $row->total_count,
            ]);
    }

    /**
     * @return Collection<int, array{product:string,option:string,votes:int}>
     */
    public function priceVotes(int $limit = 10): Collection
    {
        return DB::table('price_vote_options')
            ->join('products', 'products.id', '=', 'price_vote_options.product_id')
            ->leftJoin('product_price_votes', 'price_vote_options.id', '=', 'product_price_votes.price_vote_option_id')
            ->select([
                'products.title as product_title',
                'price_vote_options.label',
                DB::raw('count(product_price_votes.id) as vote_count'),
            ])
            ->groupBy('price_vote_options.id', 'products.title', 'price_vote_options.label')
            ->orderByDesc('vote_count')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'product' => $row->product_title,
                'option' => $row->label,
                'votes' => (int) $row->vote_count,
            ]);
    }

    private function eventCount(string $event): int
    {
        return (int) DB::table('analytics_events')->where('event', $event)->count();
    }

    private function percent(int $value, int $base): string
    {
        if ($base <= 0) {
            return '0%';
        }

        return rtrim(rtrim(number_format(($value / $base) * 100, 1), '0'), '.').'%';
    }
}
