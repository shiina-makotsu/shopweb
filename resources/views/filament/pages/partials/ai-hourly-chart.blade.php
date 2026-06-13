@php
    $buckets = collect($hourly['buckets'] ?? []);
    $models = collect($hourly['models'] ?? [])->values();
    $maxTokens = max(1, (int) ($hourly['max_tokens'] ?? 1));
    $modelColors = $models
        ->mapWithKeys(fn ($model, int $index): array => [(string) $model => $chartColors[$index % count($chartColors)]])
        ->all();
@endphp

<div class="space-y-4">
    @if($models->isNotEmpty())
        <div class="flex flex-wrap gap-3 text-xs text-slate-600">
            @foreach($models as $modelIndex => $model)
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $modelColors[(string) $model] ?? $chartColors[$modelIndex % count($chartColors)] }}"></span>
                    {{ $model }}
                </span>
            @endforeach
        </div>
    @endif

    <div class="flex h-44 items-end gap-1 overflow-x-auto border-b border-slate-200 pb-2">
        @forelse($buckets as $bucket)
            @php
                $height = max(4, min(100, ((int) $bucket['total_tokens'] / $maxTokens) * 100));
                $tooltipLines = collect($bucket['models'])
                    ->map(fn (array $row): string => $row['model'].': '.$formatTokens((int) $row['total_tokens']).'（输入 '.$formatTokens((int) $row['prompt_tokens']).' / 输出 '.$formatTokens((int) $row['completion_tokens']).'）')
                    ->implode("\n");
            @endphp
            <div class="flex h-full min-w-8 flex-1 flex-col items-center justify-end gap-1">
                <div
                    class="relative flex w-full max-w-8 flex-col justify-end overflow-hidden rounded-t-sm bg-slate-100"
                    style="height: {{ $height }}%"
                    title="{{ $bucket['label']."\n总量 ".$formatTokens((int) $bucket['total_tokens']).($tooltipLines ? "\n".$tooltipLines : '') }}"
                >
                    @foreach($bucket['models'] as $modelIndex => $row)
                        @php
                            $segmentHeight = ((int) $bucket['total_tokens']) > 0 ? max(3, ((int) $row['total_tokens'] / (int) $bucket['total_tokens']) * 100) : 0;
                            $segmentColor = $modelColors[(string) $row['model']] ?? $chartColors[$modelIndex % count($chartColors)];
                        @endphp
                        <div
                            class="w-full"
                            style="height: {{ $segmentHeight }}%; background: {{ $segmentColor }}"
                        ></div>
                    @endforeach
                </div>
                <span class="text-[10px] text-slate-400">{{ $bucket['short_label'] }}</span>
            </div>
        @empty
            <p class="w-full py-12 text-center text-sm text-slate-600">{{ $emptyText }}</p>
        @endforelse
    </div>
</div>
