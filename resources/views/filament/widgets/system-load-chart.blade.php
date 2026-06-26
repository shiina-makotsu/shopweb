<x-filament-widgets::widget class="fi-wi-chart shop-daily-sales-chart">
    <x-filament::section
        heading="实时负载走势"
        description="展示最近 24 小时 MySQL、Redis、PHP 内存、服务器内存、服务器 CPU 与请求量走势，每分钟保留一个点。异常阈值会触发 P1 告警。"
    >
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
