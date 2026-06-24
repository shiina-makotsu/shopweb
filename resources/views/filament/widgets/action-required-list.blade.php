<x-filament-widgets::widget>
    <x-filament::section
        heading="需要关注"
        description="聚合最近的待确认收款、待发货/交付、客服未读和 AI 失败记录。"
    >
        @if ($items->isEmpty())
            <div class="rounded-lg border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                暂无需要立即处理的事项。
            </div>
        @else
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($items as $item)
                    @php
                        $tone = [
                            'danger' => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-300',
                            'warning' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300',
                            'info' => 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-300',
                        ][$item['tone']] ?? 'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-gray-500/10 dark:text-gray-300';
                    @endphp
                    <a
                        @if ($item['url']) href="{{ $item['url'] }}" @endif
                        class="flex items-start justify-between gap-4 px-1 py-3 text-sm transition hover:bg-gray-50 dark:hover:bg-gray-800/60"
                    >
                        <span class="min-w-0">
                            <span class="mb-1 inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $tone }}">
                                {{ $item['type'] }}
                            </span>
                            <span class="block truncate font-medium text-gray-950 dark:text-gray-100">{{ $item['title'] }}</span>
                            <span class="mt-0.5 block truncate text-gray-500 dark:text-gray-400">{{ $item['detail'] }}</span>
                        </span>
                        <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500">
                            {{ $item['time']?->diffForHumans() }}
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
