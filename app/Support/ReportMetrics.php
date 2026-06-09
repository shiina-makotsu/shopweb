<?php

namespace App\Support;

use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportMetrics
{
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
}
