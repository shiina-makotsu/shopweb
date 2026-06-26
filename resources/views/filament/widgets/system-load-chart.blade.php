<x-filament-widgets::widget class="fi-wi-chart shop-daily-sales-chart">
    <x-filament::section
        heading="实时负载走势"
        description="展示 MySQL、Redis、PHP 内存、服务器内存、服务器 CPU 与请求量走势。折线按每分钟数据绘制，筛选只影响可见点位与时间范围。异常阈值会触发 P1 告警。"
    >
        <form class="shop-system-load-filter" wire:submit.prevent="applyFilters">
            <label>
                <span>开始时间</span>
                <input type="datetime-local" wire:model.defer="rangeStart">
            </label>
            <label>
                <span>结束时间</span>
                <input type="datetime-local" wire:model.defer="rangeEnd">
            </label>
            <label>
                <span>显示点位</span>
                <select wire:model.defer="visiblePointInterval">
                    @foreach ($visiblePointIntervalOptions as $option)
                        <option value="{{ $option }}">每 {{ $option }} 分钟</option>
                    @endforeach
                </select>
            </label>
            <div class="shop-system-load-filter-actions">
                <button type="submit">应用筛选</button>
                <button type="button" wire:click="resetFilters">重置</button>
            </div>
        </form>

        <div class="grid gap-6">
            @foreach ($charts as $item)
                @include('filament.widgets.partials.multi-line-chart', [
                    'title' => $item['title'],
                    'chart' => $item['chart'],
                    'emptyText' => '等待更多监控样本',
                ])
            @endforeach
        </div>

        <div class="shop-daily-sales-legend">
            <span><i class="shop-daily-sales-legend-primary"></i>MySQL ms</span>
            <span><i class="shop-daily-sales-legend-secondary"></i>Redis ms</span>
            <span><i style="background:#8b5cf6"></i>PHP 内存 %</span>
            <span><i class="shop-system-load-legend-memory"></i>服务器内存 %</span>
            <span><i style="background:#ef4444"></i>服务器 CPU %</span>
            <span><i class="shop-system-load-legend-rpm"></i>请求/分钟</span>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
