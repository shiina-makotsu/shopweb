<x-filament-widgets::widget>
    <x-filament::section
        heading="访问来源统计"
        description="区分前台/后台访问、游客/前台用户/后台用户、设备类型与 IP 地区来源。"
    >
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">今日访问</p>
                <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $total }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $uniqueSessions }} 个会话</p>
            </div>

            @foreach ([
                '访问位置' => $surfaces,
                '访客身份' => $visitors,
                '设备类型' => $devices,
            ] as $title => $rows)
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $title }}</p>
                    <div class="mt-3 space-y-3">
                        @forelse ($rows as $row)
                            <div>
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <span class="text-gray-700 dark:text-gray-200">{{ $row['label'] }}</span>
                                    <span class="font-medium text-gray-950 dark:text-white">{{ $row['count'] }}</span>
                                </div>
                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                    <div class="h-full rounded-full bg-blue-600" style="width: {{ $row['percent'] }}%"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">暂无数据</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">IP 地区访问柱状图</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">横向柱越长表示该地区访问越多，颜色区分访客身份。</p>
                </div>
                <div class="flex flex-wrap gap-3 text-xs text-gray-600 dark:text-gray-300">
                    <span class="inline-flex items-center gap-1"><i class="h-2.5 w-2.5 rounded-full bg-blue-500"></i>游客</span>
                    <span class="inline-flex items-center gap-1"><i class="h-2.5 w-2.5 rounded-full bg-emerald-500"></i>前台用户</span>
                    <span class="inline-flex items-center gap-1"><i class="h-2.5 w-2.5 rounded-full bg-amber-500"></i>后台用户</span>
                </div>
            </div>

            <div class="mt-4 space-y-3">
                @forelse ($regionBars as $row)
                    <div class="grid gap-2 md:grid-cols-[12rem_1fr_4rem] md:items-center">
                        <div class="truncate text-sm text-gray-700 dark:text-gray-200" title="{{ $row['region'] }}">{{ $row['region'] }}</div>
                        <div class="h-6 overflow-hidden rounded-full bg-gray-100 ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700" title="游客 {{ $row['guest'] }} / 前台用户 {{ $row['customer'] }} / 后台用户 {{ $row['staff'] }}">
                            <div class="flex h-full">
                                <div class="bg-blue-500" style="width: {{ $row['guest_percent'] }}%"></div>
                                <div class="bg-emerald-500" style="width: {{ $row['customer_percent'] }}%"></div>
                                <div class="bg-amber-500" style="width: {{ $row['staff_percent'] }}%"></div>
                            </div>
                        </div>
                        <div class="text-right text-sm font-medium text-gray-950 dark:text-white">{{ $row['total'] }}</div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">暂无地区数据。</p>
                @endforelse
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
