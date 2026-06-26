<?php

namespace App\Support;

use App\Models\CouponRedemption;
use App\Models\AnalyticsEvent;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportMetrics
{
    private const CHART_COLORS = [
        '#3b82f6',
        '#ec4899',
        '#22c55e',
        '#f59e0b',
        '#8b5cf6',
        '#06b6d4',
        '#ef4444',
        '#64748b',
    ];

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
            '已完成订单' => Order::query()
                ->where('payment_status', Order::PAYMENT_CONFIRMED)
                ->where('status', Order::STATUS_FULFILLED)
                ->count(),
        ];
    }

    /**
     * @return Collection<int, array{product:string,status:string,impressions:int,views:int,guest_views:int,customer_views:int,staff_views:int,adds:int,buy_now:int,orders:int,paid_orders:int,view_rate:string,cart_rate:string,order_rate:string}>
     */
    public function productConversions(int $limit = 20): Collection
    {
        $customerEventCount = fn (string $event): string => "sum(case when event = '{$event}' and coalesce(visitor_type, 'guest') != 'staff' then 1 else 0 end)";

        $events = DB::table('analytics_events')
            ->select([
                'product_id',
                DB::raw($customerEventCount(AnalyticsEvent::PRODUCT_IMPRESSION).' as impressions'),
                DB::raw($customerEventCount(AnalyticsEvent::PRODUCT_VIEW).' as views'),
                DB::raw("sum(case when event = '".AnalyticsEvent::PRODUCT_VIEW."' and coalesce(visitor_type, 'guest') = 'guest' then 1 else 0 end) as guest_views"),
                DB::raw("sum(case when event = '".AnalyticsEvent::PRODUCT_VIEW."' and coalesce(visitor_type, 'guest') = 'customer' then 1 else 0 end) as customer_views"),
                DB::raw("sum(case when event = '".AnalyticsEvent::PRODUCT_VIEW."' and coalesce(visitor_type, 'guest') = 'staff' then 1 else 0 end) as staff_views"),
                DB::raw($customerEventCount(AnalyticsEvent::ADD_TO_CART).' as adds'),
                DB::raw($customerEventCount(AnalyticsEvent::BUY_NOW).' as buy_now'),
                DB::raw($customerEventCount(AnalyticsEvent::ORDER_CREATED).' as order_events'),
            ])
            ->whereNotNull('product_id')
            ->groupBy('product_id');

        $paidOrders = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', Order::PAYMENT_CONFIRMED)
            ->where('orders.status', Order::STATUS_FULFILLED)
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
                DB::raw('coalesce(events.guest_views, 0) as guest_views'),
                DB::raw('coalesce(events.customer_views, 0) as customer_views'),
                DB::raw('coalesce(events.staff_views, 0) as staff_views'),
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
                'guest_views' => (int) $row->guest_views,
                'customer_views' => (int) $row->customer_views,
                'staff_views' => (int) $row->staff_views,
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
     * @return Collection<int, array{visitor:string,type:string,visits:int,pages:int,region:string,last_seen:string}>
     */
    public function todayVisitors(int $limit = 50): Collection
    {
        $start = now()->startOfDay();
        $end = now()->endOfDay();

        return DB::table('analytics_events')
            ->leftJoin('users', 'users.id', '=', 'analytics_events.user_id')
            ->where('analytics_events.event', AnalyticsEvent::PAGE_VIEW)
            ->whereBetween('analytics_events.created_at', [$start, $end])
            ->select([
                'analytics_events.user_id',
                'analytics_events.ip_hash',
                'analytics_events.visitor_type',
                'users.name',
                'users.email',
                DB::raw("coalesce(nullif(max(analytics_events.ip_region), ''), nullif(max(analytics_events.ip_country), ''), '未知地区') as region_name"),
                DB::raw('count(*) as visit_count'),
                DB::raw('count(distinct analytics_events.path) as page_count'),
                DB::raw('max(analytics_events.created_at) as last_seen_at'),
            ])
            ->groupBy('analytics_events.user_id', 'analytics_events.ip_hash', 'analytics_events.visitor_type', 'users.name', 'users.email')
            ->orderByDesc('visit_count')
            ->orderByDesc('last_seen_at')
            ->limit($limit)
            ->get()
            ->map(function ($row): array {
                $visitorType = (string) ($row->visitor_type ?? 'guest');

                return [
                    'visitor' => $row->user_id
                        ? ((string) ($row->name ?: $row->email ?: '用户 #'.$row->user_id))
                        : '游客 '.mb_substr((string) ($row->ip_hash ?: 'unknown'), 0, 12),
                    'type' => match ($visitorType) {
                        'staff' => '后台用户',
                        'customer' => '前台用户',
                        default => '游客/IP',
                    },
                    'visits' => (int) $row->visit_count,
                    'pages' => (int) $row->page_count,
                    'region' => $row->region_name,
                    'last_seen' => $row->last_seen_at ? Carbon::parse($row->last_seen_at)->format('H:i:s') : '-',
                ];
            });
    }

    /**
     * @return array{series: array<int, array{name:string,color:string,points:string}>, markers: array<int, array<string, mixed>>, y_labels: array<int, string>, x_labels: array<int, array{label:string,x:float}>, has_data: bool}
     */
    public function visitTrend(string $range = '24h', int $limitRegions = 6): array
    {
        $minuteMode = $range === '24h';
        $start = $minuteMode ? now()->subHours(24)->startOfMinute() : now()->subDays(29)->startOfDay();
        $end = $minuteMode ? now()->endOfMinute() : now()->endOfDay();
        $dateFormat = $minuteMode ? 'Y-m-d H:i' : 'Y-m-d';
        $labelFormat = $minuteMode ? 'm-d H:i' : 'm-d';
        $steps = $minuteMode ? max(1, $start->diffInMinutes($end)) : 29;
        $labelEvery = $minuteMode ? 120 : 7;

        $regionExpression = "coalesce(nullif(ip_region, ''), nullif(ip_country, ''), '未知地区')";
        $topRegions = DB::table('analytics_events')
            ->where('event', AnalyticsEvent::PAGE_VIEW)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw($regionExpression.' as region_name, count(*) as total')
            ->groupBy(DB::raw($regionExpression))
            ->orderByDesc('total')
            ->limit($limitRegions)
            ->pluck('region_name')
            ->map(fn ($region): string => (string) $region)
            ->all();

        if ($topRegions === []) {
            $topRegions = ['暂无访问'];
        }

        $rawEvents = AnalyticsEvent::query()
            ->where('event', AnalyticsEvent::PAGE_VIEW)
            ->whereBetween('created_at', [$start, $end])
            ->get(['ip_region', 'ip_country', 'created_at']);

        $events = $rawEvents
            ->groupBy(fn (AnalyticsEvent $event): string => $event->ip_region ?: ($event->ip_country ?: '未知地区'))
            ->map(fn (Collection $items): Collection => $items
                ->groupBy(fn (AnalyticsEvent $event): string => $event->created_at->format($dateFormat))
                ->map(fn (Collection $bucket): object => (object) ['total' => $bucket->count()]));

        $rows = collect(range(0, $steps))
            ->map(fn (int $step): array => [
                'time' => $minuteMode ? $start->copy()->addMinutes($step) : $start->copy()->addDays($step),
            ]);

        return $this->lineChartFromSeries(
            $rows,
            collect($topRegions)->values()->map(function (string $region, int $index) use ($events, $dateFormat): array {
                $regionEvents = $events->get($region, collect());

                return [
                    'name' => $region,
                    'color' => self::CHART_COLORS[$index % count(self::CHART_COLORS)],
                    'value' => function (array $row) use ($regionEvents, $dateFormat): int {
                        /** @var Carbon $time */
                        $time = $row['time'];
                        $bucket = $time->format($dateFormat);

                        return (int) ($regionEvents->get($bucket)?->total ?? 0);
                    },
                    'line' => fn (int $value): string => '访问量：'.$value,
                ];
            })->all(),
            labelFormat: $labelFormat,
            labelEvery: $labelEvery,
        );
    }

    /**
     * @return array<string, string|int>
     */
    public function salesSummary(): array
    {
        $confirmed = fn () => Order::query()
            ->where('payment_status', Order::PAYMENT_CONFIRMED)
            ->where('status', Order::STATUS_FULFILLED);

        return [
            '今日销售' => Money::format((int) $this->completedInRange($confirmed(), now()->startOfDay(), now()->endOfDay())->sum('total_cents')),
            '本月销售' => Money::format((int) $this->completedInRange($confirmed(), now()->startOfMonth(), now()->endOfMonth())->sum('total_cents')),
            '已完成订单' => $confirmed()->count(),
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
            ])->where('status', '!=', Product::STATUS_PRESALE))
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
            ->withCount(['orders as completed_orders_count' => fn ($query) => $query
                ->where('payment_status', Order::PAYMENT_CONFIRMED)
                ->where('status', Order::STATUS_FULFILLED)])
            ->withSum(['orders as completed_orders_total_cents' => fn ($query) => $query
                ->where('payment_status', Order::PAYMENT_CONFIRMED)
                ->where('status', Order::STATUS_FULFILLED)], 'total_cents')
            ->orderByDesc('completed_orders_total_cents')
            ->limit($limit)
            ->get()
            ->map(fn (User $user): array => [
                'name' => $user->name,
                'email' => $user->email,
                'orders' => (int) $user->completed_orders_count,
                'total' => Money::format((int) ($user->completed_orders_total_cents ?? 0)),
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
     * @return Collection<int, array{product:string,sku:string,orders:int,quantity:int,total:string}>
     */
    public function productSales(int $limit = 10): Collection
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', Order::PAYMENT_CONFIRMED)
            ->where('orders.status', Order::STATUS_FULFILLED)
            ->select([
                'order_items.product_title',
                'order_items.variant_sku',
                DB::raw('count(distinct orders.id) as completed_order_count'),
                DB::raw('sum(order_items.quantity) as quantity_sum'),
                DB::raw('sum(order_items.line_total_cents) as total_cents_sum'),
            ])
            ->groupBy('order_items.product_title', 'order_items.variant_sku')
            ->orderByDesc('completed_order_count')
            ->orderByDesc('quantity_sum')
            ->orderByDesc('total_cents_sum')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'product' => $row->product_title,
                'sku' => $row->variant_sku,
                'orders' => (int) $row->completed_order_count,
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
            ->where('orders.status', Order::STATUS_FULFILLED)
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
            ->where('products.status', Product::STATUS_CONCEPT)
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

    private function completedInRange($query, $start, $end)
    {
        return $query->where(function ($query) use ($start, $end): void {
            $query
                ->whereBetween('fulfilled_at', [$start, $end])
                ->orWhere(function ($query) use ($start, $end): void {
                    $query
                        ->whereNull('fulfilled_at')
                        ->whereBetween('paid_at', [$start, $end]);
                });
        });
    }

    private function percent(int $value, int $base): string
    {
        if ($base <= 0) {
            return '0%';
        }

        return rtrim(rtrim(number_format(($value / $base) * 100, 1), '0'), '.').'%';
    }

    /**
     * @param  Collection<int, array{time: Carbon}>  $rows
     * @param  array<int, array{name:string,color:string,value:callable,line:callable}>  $seriesDefs
     * @return array{series: array<int, array{name:string,color:string,points:string}>, markers: array<int, array<string, mixed>>, y_labels: array<int, string>, x_labels: array<int, array{label:string,x:float}>, has_data: bool}
     */
    private function lineChartFromSeries(Collection $rows, array $seriesDefs, string $labelFormat, int $labelEvery): array
    {
        $width = 1000;
        $height = 260;
        $left = 58;
        $right = 28;
        $top = 18;
        $bottom = 40;
        $plotWidth = $width - $left - $right;
        $plotHeight = $height - $top - $bottom;
        $count = max(1, $rows->count() - 1);
        $valuesBySeries = [];
        $max = 1;

        foreach ($seriesDefs as $index => $series) {
            $valuesBySeries[$index] = $rows
                ->values()
                ->map(fn (array $row): int|float => (float) $series['value']($row))
                ->all();
            $seriesMax = max([0, ...$valuesBySeries[$index]]);
            $max = max($max, $seriesMax);
        }

        $point = static function (int $index, float|int $value) use ($left, $top, $plotWidth, $plotHeight, $count, $max): array {
            $x = round($left + (($plotWidth / $count) * $index), 2);
            $y = round($top + ($plotHeight - (((float) $value / max(1, $max)) * $plotHeight)), 2);

            return [$x, $y];
        };

        $seriesRows = [];
        $markers = [];

        foreach ($seriesDefs as $seriesIndex => $series) {
            $points = [];

            foreach ($valuesBySeries[$seriesIndex] as $index => $value) {
                [$x, $y] = $point($index, $value);
                $points[] = $x.','.$y;
                $time = $rows->values()->get($index)['time'];

                $markers[] = [
                    'x' => $x,
                    'y' => $y,
                    'title' => $time->format($labelFormat),
                    'line_1' => $series['name'],
                    'line_2' => $series['line']((int) $value),
                    'color' => $series['color'],
                ];
            }

            $seriesRows[] = [
                'name' => $series['name'],
                'color' => $series['color'],
                'points' => implode(' ', $points),
            ];
        }

        return [
            'series' => $seriesRows,
            'markers' => $markers,
            'y_labels' => collect(range(0, 4))
                ->map(fn (int $step): string => (string) (int) round(($max / 4) * (4 - $step)))
                ->all(),
            'x_labels' => $rows
                ->values()
                ->filter(fn (array $row, int $index): bool => $index === 0 || $index === $rows->count() - 1 || $index % $labelEvery === 0)
                ->map(fn (array $row, int $index): array => [
                    'label' => $row['time']->format($labelFormat),
                    'x' => round($left + (($plotWidth / $count) * $index), 2),
                ])
                ->values()
                ->all(),
            'has_data' => collect($valuesBySeries)->flatten()->sum() > 0,
        ];
    }
}
