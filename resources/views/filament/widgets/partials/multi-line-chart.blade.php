@php
    $chartId = $chartId ?? 'chart-'.\Illuminate\Support\Str::random(8);
    $title = $title ?? null;
    $emptyText = $emptyText ?? '等待更多样本';
@endphp

<div class="shop-daily-sales-chart-frame" data-shop-chart-frame>
    @unless ($chart['has_data'] ?? false)
        <div class="shop-daily-sales-empty">{{ $emptyText }}</div>
    @endunless

    <div class="shop-chart-floating-tooltip hidden" data-shop-chart-tooltip style="display: none;">
        <div class="shop-chart-tooltip-card">
            <p class="shop-chart-tooltip-title" data-shop-chart-tooltip-title></p>
            @foreach (range(1, 6) as $line)
                <p class="shop-chart-tooltip-line" data-shop-chart-tooltip-line data-chart-line-index="{{ $line }}"></p>
            @endforeach
        </div>
    </div>

    @if ($title)
        <div class="shop-chart-panel-title">{{ $title }}</div>
    @endif

    <svg
        class="shop-daily-sales-svg"
        data-shop-chart-svg
        viewBox="0 0 1000 260"
        role="img"
        aria-label="{{ $title ?? '折线图' }}"
        preserveAspectRatio="none"
    >
        @foreach ([18, 68.5, 119, 169.5, 220] as $index => $y)
            <line x1="58" x2="972" y1="{{ $y }}" y2="{{ $y }}" class="shop-daily-sales-grid" />
            <text x="12" y="{{ $y + 4 }}" class="shop-daily-sales-y-label">
                {{ $chart['y_labels'][$index] ?? '' }}
            </text>
        @endforeach

        @foreach (($chart['series'] ?? []) as $series)
            <polyline points="{{ $series['points'] }}" class="shop-daily-sales-line" style="stroke: {{ $series['color'] }}; stroke-width: {{ $series['width'] ?? 2 }};" />
        @endforeach

        @foreach (($chart['x_labels'] ?? []) as $label)
            <text x="{{ $label['x'] }}" y="248" class="shop-daily-sales-x-label">
                {{ $label['label'] }}
            </text>
        @endforeach

        @foreach (($chart['markers'] ?? []) as $marker)
            <g class="shop-chart-data-marker" data-shop-chart-point data-chart-x="{{ $marker['x'] }}" data-chart-y="{{ $marker['y'] }}" data-chart-title="{{ $marker['title'] }}" data-chart-line-1="{{ $marker['line_1'] ?? '' }}" data-chart-line-2="{{ $marker['line_2'] ?? '' }}" data-chart-line-3="{{ $marker['line_3'] ?? '' }}" data-chart-line-4="{{ $marker['line_4'] ?? '' }}" data-chart-line-5="{{ $marker['line_5'] ?? '' }}" data-chart-line-6="{{ $marker['line_6'] ?? '' }}" data-chart-color="{{ $marker['color'] }}">
                <circle cx="{{ $marker['x'] }}" cy="{{ $marker['y'] }}" r="6" class="shop-chart-hit-target" fill="none" stroke="transparent" stroke-width="12" data-shop-chart-hit-target style="pointer-events:stroke;" />
                <circle cx="{{ $marker['x'] }}" cy="{{ $marker['y'] }}" r="2.5" class="shop-chart-point-dot" style="fill:{{ $marker['color'] }}; stroke:{{ $marker['color'] }}; opacity:0;" />
                <circle cx="{{ $marker['x'] }}" cy="{{ $marker['y'] }}" r="4.5" class="shop-chart-point-ring" style="fill:none; stroke:{{ $marker['color'] }}; opacity:0;" />
            </g>
        @endforeach
    </svg>
</div>

@include('filament.widgets.partials.chart-hover-script')
