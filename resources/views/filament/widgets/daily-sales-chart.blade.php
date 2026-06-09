@php($chartDescription = '销售额 '.$totalSales.'，订单 '.$summary['order_count'].' 笔')

<x-filament-widgets::widget class="fi-wi-chart shop-daily-sales-chart">
    <x-filament::section
        heading="近 30 日销售趋势"
        :description="$chartDescription"
    >
        <div class="shop-daily-sales-summary" aria-label="近 30 日销售数据统计">
            <div>
                <span>销售额</span>
                <strong>{{ $totalSales }}</strong>
            </div>
            <div>
                <span>订单数</span>
                <strong>{{ $summary['order_count'] }}</strong>
            </div>
            <div>
                <span>平均客单价</span>
                <strong>{{ $averageOrder }}</strong>
            </div>
            <div>
                <span>最高日销售</span>
                <strong>{{ $bestDaySales }}</strong>
            </div>
        </div>

        <div class="shop-daily-sales-chart-frame">
            @unless ($hasData)
                <div class="shop-daily-sales-empty">暂无数据</div>
            @endunless

            <svg
                class="shop-daily-sales-svg"
                viewBox="0 0 1000 260"
                role="img"
                aria-label="近 30 日销售趋势折线图"
                preserveAspectRatio="none"
            >
                <defs>
                    <linearGradient id="shop-sales-fill" x1="0" x2="0" y1="0" y2="1">
                        <stop offset="0%" stop-color="currentColor" stop-opacity="0.18" />
                        <stop offset="100%" stop-color="currentColor" stop-opacity="0.02" />
                    </linearGradient>
                </defs>

                @foreach ([18, 68.5, 119, 169.5, 220] as $index => $y)
                    <line x1="58" x2="972" y1="{{ $y }}" y2="{{ $y }}" class="shop-daily-sales-grid" />
                    <text x="12" y="{{ $y + 4 }}" class="shop-daily-sales-y-label">
                        {{ $chart['y_labels'][$index] }}
                    </text>
                @endforeach

                <polyline
                    points="{{ $chart['baseline_points'] }}"
                    class="shop-daily-sales-baseline"
                />

                <polygon
                    points="58,220 {{ $chart['sales_points'] }} 972,220"
                    class="shop-daily-sales-fill"
                />

                <polyline
                    points="{{ $chart['sales_points'] }}"
                    class="shop-daily-sales-line shop-daily-sales-line-primary"
                />

                <polyline
                    points="{{ $chart['order_points'] }}"
                    class="shop-daily-sales-line shop-daily-sales-line-secondary"
                />

                @foreach ($chart['x_labels'] as $label)
                    <text x="{{ $label['x'] }}" y="248" class="shop-daily-sales-x-label">
                        {{ $label['label'] }}
                    </text>
                @endforeach
            </svg>
        </div>

        <div class="shop-daily-sales-legend">
            <span><i class="shop-daily-sales-legend-primary"></i>销售额</span>
            <span><i class="shop-daily-sales-legend-secondary"></i>订单数</span>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
