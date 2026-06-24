<x-filament-panels::page>
    <div class="space-y-6">
        @php($trend = $this->salesTrend())
        <div class="shop-daily-sales-chart">
        @include('filament.widgets.partials.daily-sales-chart-content', [
            'daily' => $trend['daily'],
            'summary' => $trend['summary'],
            'chart' => $trend['chart'],
            'hasData' => $trend['hasData'],
            'totalSales' => $trend['totalSales'],
            'averageOrder' => $trend['averageOrder'],
            'bestDaySales' => $trend['bestDaySales'],
        ])
        </div>

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
                <x-report-table title="商品转化排行" :rows="$this->productConversions()" :columns="['product' => '商品', 'status' => '状态', 'impressions' => '曝光', 'views' => '详情访问', 'adds' => '加购', 'buy_now' => '立即购买', 'orders' => '订单', 'paid_orders' => '付款订单', 'view_rate' => '曝光到详情', 'cart_rate' => '详情到加购', 'order_rate' => '详情到订单']" />
            </div>
            <x-report-table title="入口来源表现" :rows="$this->trafficSources()" :columns="['source' => '来源', 'impressions' => '曝光', 'views' => '详情访问', 'adds' => '加购', 'orders' => '订单']" />
            <x-report-table title="低库存 SKU" :rows="$this->lowStockVariants()" :columns="['sku' => 'SKU', 'product' => '商品', 'stock' => '库存', 'threshold' => '阈值']" />
            <x-report-table title="客户排行" :rows="$this->topCustomers()" :columns="['name' => '用户昵称', 'email' => '邮箱', 'orders' => '订单数', 'total' => '累计金额']" />
            <x-report-table title="优惠码使用" :rows="$this->couponUsage()" :columns="['code' => '代码', 'name' => '名称', 'confirmed' => '确认次数', 'discount' => '优惠金额']" />
            <x-report-table title="商品销售排行" :rows="$this->productSales()" :columns="['product' => '商品', 'sku' => 'SKU', 'quantity' => '数量', 'total' => '销售额']" :export-url="\App\Support\Url::route('admin.report-exports.product-sales')" />
            <x-report-table title="分类销售排行" :rows="$this->categorySales()" :columns="['category' => '分类', 'items' => '订单商品数', 'quantity' => '数量', 'total' => '销售额']" :export-url="\App\Support\Url::route('admin.report-exports.category-sales')" />
            <x-report-table title="购买意愿投票" :rows="$this->intentVotes()" :columns="['product' => '商品', 'want' => '想买', 'considering' => '考虑中', 'not_now' => '暂时不买', 'total' => '合计']" />
            <x-report-table title="价格区间投票" :rows="$this->priceVotes()" :columns="['product' => '商品', 'option' => '区间', 'votes' => '票数']" />
        </div>
    </div>
</x-filament-panels::page>
