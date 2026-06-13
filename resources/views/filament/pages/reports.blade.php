<x-filament-panels::page>
    <div class="space-y-6">
        <section class="grid gap-4 md:grid-cols-3">
            @foreach ($this->salesSummary() as $label => $value)
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $value }}</div>
                </div>
            @endforeach
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
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
