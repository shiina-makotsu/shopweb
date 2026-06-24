<x-filament-widgets::widget class="fi-wi-chart shop-daily-sales-chart">
    <x-filament::section
        heading="实时负载趋势"
        description="展示 MySQL、Redis、PHP 内存、服务器内存、服务器 CPU 与请求量趋势。异常阈值会触发 P1 机器人告警。"
    >
        <div class="shop-daily-sales-chart-frame">
            @unless ($chart['has_data'])
                <div class="shop-daily-sales-empty">等待更多监控样本</div>
            @endunless

            <svg
                class="shop-daily-sales-svg"
                viewBox="0 0 1000 260"
                role="img"
                aria-label="网站实时负载趋势折线图"
                preserveAspectRatio="none"
            >
                @foreach ([18, 68.5, 119, 169.5, 220] as $index => $y)
                    <line x1="58" x2="972" y1="{{ $y }}" y2="{{ $y }}" class="shop-daily-sales-grid" />
                    <text x="12" y="{{ $y + 4 }}" class="shop-daily-sales-y-label">
                        {{ $chart['y_labels'][$index] }}
                    </text>
                @endforeach

                <polyline points="{{ $chart['db_points'] }}" class="shop-daily-sales-line shop-daily-sales-line-primary" />
                <polyline points="{{ $chart['redis_points'] }}" class="shop-daily-sales-line shop-daily-sales-line-secondary" />
                <polyline points="{{ $chart['memory_points'] }}" class="shop-daily-sales-line shop-system-load-line-memory" />
                <polyline points="{{ $chart['server_memory_points'] }}" class="shop-daily-sales-line" style="stroke: #f59e0b;" />
                <polyline points="{{ $chart['cpu_points'] }}" class="shop-daily-sales-line" style="stroke: #ef4444;" />
                <polyline points="{{ $chart['rpm_points'] }}" class="shop-daily-sales-line shop-system-load-line-rpm" />

                @foreach ($chart['x_labels'] as $label)
                    <text x="{{ $label['x'] }}" y="248" class="shop-daily-sales-x-label">
                        {{ $label['label'] }}
                    </text>
                @endforeach
            </svg>
        </div>

        <div class="shop-daily-sales-legend">
            <span><i class="shop-daily-sales-legend-primary"></i>MySQL ms</span>
            <span><i class="shop-daily-sales-legend-secondary"></i>Redis ms</span>
            <span><i class="shop-system-load-legend-memory"></i>PHP 内存 %</span>
            <span><i style="background:#f59e0b"></i>服务器内存 %</span>
            <span><i style="background:#ef4444"></i>服务器 CPU %</span>
            <span><i class="shop-system-load-legend-rpm"></i>请求/分钟</span>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
