<div class="shop-daily-sales-summary">
    <div>
        <span>总销售额</span>
        <strong>{{ $totalSales }}</strong>
    </div>
    <div>
        <span>已支付销售额</span>
        <strong>{{ $paidSales }}</strong>
    </div>
    <div>
        <span>平均客单价</span>
        <strong>{{ $averageOrder }}</strong>
    </div>
    <div>
        <span>最佳单日销售额</span>
        <strong>{{ $bestDaySales }}</strong>
    </div>
</div>

@php($chartTabs = $chartTabs ?? [['key' => 'daily', 'label' => '每日', 'chart' => $chart, 'active' => true]])

<div class="shop-chart-tabs" data-shop-chart-tabs>
    <div class="shop-chart-tab-buttons" role="tablist" aria-label="销售额折线图范围">
        @foreach ($chartTabs as $tab)
            <button
                type="button"
                class="shop-chart-tab-button {{ $tab['active'] ?? false ? 'is-active' : '' }}"
                data-shop-chart-tab-button="{{ $tab['key'] }}"
            >
                {{ $tab['label'] }}
            </button>
        @endforeach
    </div>

@foreach ($chartTabs as $tab)
<div class="shop-daily-sales-chart-frame {{ $tab['active'] ?? false ? '' : 'hidden' }}" data-shop-chart-frame data-shop-chart-tab-panel="{{ $tab['key'] }}">
    @php($currentChart = $tab['chart'])
    @unless ($hasData)
        <div class="shop-daily-sales-empty">等待更多销售样本</div>
    @endunless

    <div class="shop-chart-floating-tooltip hidden" data-shop-chart-tooltip style="display: none;">
        <div class="shop-chart-tooltip-card">
            <p class="shop-chart-tooltip-title" data-shop-chart-tooltip-title></p>
            <p class="shop-chart-tooltip-line" data-shop-chart-tooltip-line data-chart-line-index="1"></p>
            <p class="shop-chart-tooltip-line" data-shop-chart-tooltip-line data-chart-line-index="2"></p>
            <p class="shop-chart-tooltip-line" data-shop-chart-tooltip-line data-chart-line-index="3"></p>
        </div>
    </div>

    <svg
        class="shop-daily-sales-svg"
        data-shop-chart-svg
        viewBox="0 0 1000 260"
        role="img"
        aria-label="销售趋势图"
        preserveAspectRatio="none"
    >
        @foreach ([18, 68.5, 119, 169.5, 220] as $index => $y)
            <line x1="58" x2="972" y1="{{ $y }}" y2="{{ $y }}" class="shop-daily-sales-grid" />
            <text x="12" y="{{ $y + 4 }}" class="shop-daily-sales-y-label">
                {{ $currentChart['money_y_labels'][$index] }}
            </text>
        @endforeach

        <polyline points="{{ $currentChart['paid_points'] }}" class="shop-daily-sales-line shop-daily-sales-line-primary" />
        <polyline points="{{ $currentChart['created_order_points'] }}" class="shop-daily-sales-line shop-daily-sales-line-secondary" />
        <polyline points="{{ $currentChart['completed_order_points'] }}" class="shop-daily-sales-line shop-daily-sales-line-tertiary" />

        @foreach ($currentChart['x_labels'] as $label)
            <text x="{{ $label['x'] }}" y="248" class="shop-daily-sales-x-label">
                {{ $label['label'] }}
            </text>
        @endforeach

        @foreach ($currentChart['sample_points'] as $point)
            <g class="shop-chart-data-marker" data-shop-chart-point data-chart-x="{{ $point['x'] }}" data-chart-y="{{ $point['paid_y'] }}" data-chart-title="{{ $point['label'] }}" data-chart-line-1="已支付金额：{{ \App\Support\Money::format($point['paid_cents']) }}" data-chart-line-2="已支付订单：{{ $point['paid_order_count'] ?? 0 }}" data-chart-line-3="" data-chart-color="#3b82f6">
                <circle cx="{{ $point['x'] }}" cy="{{ $point['paid_y'] }}" r="6" class="shop-chart-hit-target" fill="none" stroke="transparent" stroke-width="12" data-shop-chart-hit-target style="pointer-events:stroke;" />
                <circle cx="{{ $point['x'] }}" cy="{{ $point['paid_y'] }}" r="2.5" class="shop-chart-point-dot" style="fill:#3b82f6; stroke:#3b82f6; opacity:0;" />
                <circle cx="{{ $point['x'] }}" cy="{{ $point['paid_y'] }}" r="4.5" class="shop-chart-point-ring" style="fill:none; stroke:#3b82f6; opacity:0;" />
            </g>
            <g class="shop-chart-data-marker" data-shop-chart-point data-chart-x="{{ $point['x'] }}" data-chart-y="{{ $point['created_y'] }}" data-chart-title="{{ $point['label'] }}" data-chart-line-1="下单量：{{ $point['created_order_count'] }}" data-chart-line-2="已支付金额：{{ \App\Support\Money::format($point['paid_cents']) }}" data-chart-line-3="" data-chart-color="#ec4899">
                <circle cx="{{ $point['x'] }}" cy="{{ $point['created_y'] }}" r="6" class="shop-chart-hit-target" fill="none" stroke="transparent" stroke-width="12" data-shop-chart-hit-target style="pointer-events:stroke;" />
                <circle cx="{{ $point['x'] }}" cy="{{ $point['created_y'] }}" r="2.5" class="shop-chart-point-dot" style="fill:#ec4899; stroke:#ec4899; opacity:0;" />
                <circle cx="{{ $point['x'] }}" cy="{{ $point['created_y'] }}" r="4.5" class="shop-chart-point-ring" style="fill:none; stroke:#ec4899; opacity:0;" />
            </g>
            <g class="shop-chart-data-marker" data-shop-chart-point data-chart-x="{{ $point['x'] }}" data-chart-y="{{ $point['completed_y'] }}" data-chart-title="{{ $point['label'] }}" data-chart-line-1="完成订单：{{ $point['completed_order_count'] }}" data-chart-line-2="完成销售额：{{ \App\Support\Money::format($point['sales_cents'] ?? 0) }}" data-chart-line-3="" data-chart-color="#22c55e">
                <circle cx="{{ $point['x'] }}" cy="{{ $point['completed_y'] }}" r="6" class="shop-chart-hit-target" fill="none" stroke="transparent" stroke-width="12" data-shop-chart-hit-target style="pointer-events:stroke;" />
                <circle cx="{{ $point['x'] }}" cy="{{ $point['completed_y'] }}" r="2.5" class="shop-chart-point-dot" style="fill:#22c55e; stroke:#22c55e; opacity:0;" />
                <circle cx="{{ $point['x'] }}" cy="{{ $point['completed_y'] }}" r="4.5" class="shop-chart-point-ring" style="fill:none; stroke:#22c55e; opacity:0;" />
            </g>
        @endforeach
    </svg>
</div>
@endforeach
</div>

@include('filament.widgets.partials.chart-hover-script')
