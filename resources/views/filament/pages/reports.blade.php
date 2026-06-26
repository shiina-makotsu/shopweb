<x-filament-panels::page>
    <div class="space-y-6">
        @php($trend = $this->salesTrend())
        @php($productConversionColumns = [
            'product' => '商品',
            'status' => '状态',
            'impressions' => '曝光',
            'views' => '客户详情访问',
            'guest_views' => '游客详情',
            'customer_views' => '前台用户详情',
            'staff_views' => '后台用户详情',
            'adds' => '加购',
            'buy_now' => '立即购买',
            'orders' => '订单',
            'paid_orders' => '付款订单',
            'view_rate' => '曝光到详情',
            'cart_rate' => '详情到加购',
            'order_rate' => '详情到订单',
        ])

        <div class="shop-daily-sales-chart">
            @include('filament.widgets.partials.daily-sales-chart-content', [
                'daily' => $trend['daily'],
                'summary' => $trend['summary'],
                'chart' => $trend['chart'],
                'chartTabs' => [
                    ['key' => 'daily', 'label' => '每日销售额', 'chart' => $trend['chart'], 'active' => true],
                    ['key' => 'last24h', 'label' => '24h 销售额', 'chart' => $trend['chart24h'], 'active' => false],
                ],
                'hasData' => $trend['hasData'],
                'totalSales' => $trend['totalSales'],
                'paidSales' => $trend['paidSales'],
                'averageOrder' => $trend['averageOrder'],
                'bestDaySales' => $trend['bestDaySales'],
            ])
        </div>

        <x-filament::section heading="访问量趋势" description="默认显示最近 24 小时访问量，可切换为每日访问量；不同颜色代表不同 IP 地区。">
            <div class="shop-chart-tabs" data-shop-chart-tabs>
                <div class="shop-chart-tab-buttons" role="tablist" aria-label="访问量折线图范围">
                    <button type="button" class="shop-chart-tab-button is-active" data-shop-chart-tab-button="visits-24h">24h 访问量</button>
                    <button type="button" class="shop-chart-tab-button" data-shop-chart-tab-button="visits-daily">每日访问量</button>
                </div>

                <div data-shop-chart-tab-panel="visits-24h">
                    @include('filament.widgets.partials.multi-line-chart', [
                        'title' => '最近 24 小时访问量',
                        'chart' => $this->visitTrend('24h'),
                        'emptyText' => '等待更多访问样本',
                    ])
                </div>

                <div class="hidden" data-shop-chart-tab-panel="visits-daily">
                    @include('filament.widgets.partials.multi-line-chart', [
                        'title' => '最近 30 日访问量',
                        'chart' => $this->visitTrend('daily'),
                        'emptyText' => '等待更多访问样本',
                    ])
                </div>
            </div>
        </x-filament::section>

        <section class="grid gap-4 md:grid-cols-3">
            @foreach ($this->salesSummary() as $label => $value)
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $value }}</div>
                </div>
            @endforeach
        </section>

        <section class="grid gap-4 md:grid-cols-4">
            @foreach ($this->conversionFunnel() as $label => $value)
                <div class="rounded-lg border border-blue-100 bg-white p-4 shadow-sm dark:border-blue-900/60 dark:bg-gray-900">
                    <div class="text-xs font-medium uppercase tracking-wide text-blue-700 dark:text-blue-300">{{ $label }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $value }}</div>
                </div>
            @endforeach
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="xl:col-span-2">
                <x-report-table title="商品转化排行" :rows="$this->productConversions()" :columns="$productConversionColumns" />
            </div>

            <x-report-table title="入口来源表现" :rows="$this->trafficSources()" :columns="['source' => '来源', 'impressions' => '曝光', 'views' => '详情访问', 'adds' => '加购', 'orders' => '订单']" />
            <x-report-table title="低库存 SKU" :rows="$this->lowStockVariants()" :columns="['sku' => 'SKU', 'product' => '商品', 'stock' => '库存', 'threshold' => '阈值']" />
            <x-report-table title="客户排行" :rows="$this->topCustomers()" :columns="['name' => '用户昵称', 'email' => '邮箱', 'orders' => '订单数', 'total' => '累计金额']" />
            <x-report-table title="优惠码使用" :rows="$this->couponUsage()" :columns="['code' => '代码', 'name' => '名称', 'confirmed' => '确认次数', 'discount' => '优惠金额']" />
            <x-report-table title="商品销售排行" :rows="$this->productSales()" :columns="['product' => '商品', 'sku' => 'SKU', 'orders' => '完成订单', 'quantity' => '数量', 'total' => '销售额']" :export-url="\App\Support\Url::route('admin.report-exports.product-sales')" />
            <x-report-table title="分类销售排行" :rows="$this->categorySales()" :columns="['category' => '分类', 'items' => '订单商品数', 'quantity' => '数量', 'total' => '销售额']" :export-url="\App\Support\Url::route('admin.report-exports.category-sales')" />
            <x-report-table title="购买意愿投票" :rows="$this->intentVotes()" :columns="['product' => '商品', 'want' => '想买', 'considering' => '考虑中', 'not_now' => '暂时不买', 'total' => '合计']" />
            <x-report-table title="价格区间投票" :rows="$this->priceVotes()" :columns="['product' => '商品', 'option' => '区间', 'votes' => '票数']" />
        </div>
    </div>
</x-filament-panels::page>
