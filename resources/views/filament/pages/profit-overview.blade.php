<x-filament-panels::page>
    <section class="rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 px-4 py-3">
            <h2 class="text-lg font-semibold">利润概览</h2>
            <p class="mt-1 text-sm text-slate-600">毛利润 = 销售额 - 采购成本；总利润 = 销售额 - 全部成本；利润率 = 利润 / 销售额。</p>
            <form class="mt-4 flex flex-wrap items-end gap-3" method="get">
                <label class="block">
                    <span class="text-xs font-medium text-slate-600">开始日期</span>
                    <input class="mt-1 rounded-sm border border-slate-300 px-3 py-2 text-sm" type="date" name="date_from" value="{{ $this->date_from }}">
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-600">结束日期</span>
                    <input class="mt-1 rounded-sm border border-slate-300 px-3 py-2 text-sm" type="date" name="date_to" value="{{ $this->date_to }}">
                </label>
                <button class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800" type="submit">应用筛选</button>
                <a class="rounded-sm border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" href="{{ \App\Filament\Pages\ProfitOverviewPage::getUrl() }}">重置</a>
                <a class="rounded-sm border border-emerald-700 bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800" href="{{ $this->exportUrl() }}">导出 CSV</a>
            </form>
        </div>
        <div class="grid gap-px bg-slate-200 md:grid-cols-4 xl:grid-cols-8">
            @foreach($this->metrics() as $label => $value)
                <div class="bg-white px-4 py-5">
                    <p class="text-sm text-slate-600">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ $value }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mt-6 rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 px-4 py-3">
            <h2 class="text-lg font-semibold">仓库利润</h2>
            <p class="mt-1 text-sm text-slate-600">按订单商品所属仓库归集销售额和邮费，按采购批次所属仓库归集成本；仓库利润率 = 仓库利润 / 仓库销售额。</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-4 py-3 font-medium">仓库</th>
                        <th class="px-4 py-3 text-right font-medium">销售额</th>
                        <th class="px-4 py-3 text-right font-medium">成本</th>
                        <th class="px-4 py-3 text-right font-medium">利润</th>
                        <th class="px-4 py-3 text-right font-medium">利润率</th>
                        <th class="px-4 py-3 text-right font-medium">完成订单</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($this->warehouseRows() as $row)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-950">{{ $row['warehouse_name'] }}</td>
                            <td class="px-4 py-3 text-right">{{ $row['sales'] }}</td>
                            <td class="px-4 py-3 text-right">{{ $row['cost'] }}</td>
                            <td class="px-4 py-3 text-right font-semibold">{{ $row['profit'] }}</td>
                            <td class="px-4 py-3 text-right">{{ $row['profit_rate'] }}</td>
                            <td class="px-4 py-3 text-right">{{ $row['orders_count'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-4 py-8 text-center text-slate-500" colspan="6">暂无仓库利润数据</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-filament-panels::page>
