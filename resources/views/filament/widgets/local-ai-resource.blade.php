<x-filament-widgets::widget>
    <x-filament::section
        heading="本地 AI 资源保护"
        description="本地 AI 工作流或模型占用过高时会阻断本地 AI 调用，并可通过 LOCAL_AI_STOP_URL 尝试停止 runner，优先保证网站可用。"
    >
        @php
            $blocked = (bool) $snapshot['blocked'];
        @endphp

        <div class="grid gap-4 lg:grid-cols-[280px_1fr]">
            <div class="rounded-xl border p-4 {{ $blocked ? 'border-red-200 bg-red-50 text-red-900 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200' : 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200' }}">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm font-semibold">{{ $blocked ? '保护中' : '可运行' }}</span>
                    <span class="rounded-full bg-white/70 px-2 py-1 text-xs font-medium dark:bg-black/20">
                        {{ $snapshot['enabled'] ? '已启用' : '未启用' }}
                    </span>
                </div>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="opacity-70">内存占用</dt>
                        <dd class="font-semibold">{{ $snapshot['used_percent'] ?? '-' }}%</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="opacity-70">可用内存</dt>
                        <dd class="font-semibold">{{ $snapshot['free_mb'] ?? '-' }} MB</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="opacity-70">保护阈值</dt>
                        <dd class="font-semibold">{{ $snapshot['max_memory_percent'] }}% / {{ $snapshot['min_free_memory_mb'] }} MB</dd>
                    </div>
                    <div>
                        <dt class="opacity-70">状态说明</dt>
                        <dd class="mt-1 font-semibold">{{ $snapshot['reason'] }}</dd>
                    </div>
                    @if ($snapshot['blocked_seconds'])
                        <div class="flex justify-between gap-3">
                            <dt class="opacity-70">剩余冷却</dt>
                            <dd class="font-semibold">{{ $snapshot['blocked_seconds'] }} 秒</dd>
                        </div>
                    @endif
                    <div class="flex justify-between gap-3">
                        <dt class="opacity-70">停止接口</dt>
                        <dd class="font-semibold">{{ $snapshot['stop_url_configured'] ? '已配置' : '未配置' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="min-h-56 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-950">
                @unless ($chart['has_data'])
                    <div class="flex h-48 items-center justify-center rounded-lg border border-dashed border-gray-200 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                        等待本地 AI 调用样本
                    </div>
                @else
                    <svg class="h-56 w-full" viewBox="0 0 1000 220" preserveAspectRatio="none" role="img" aria-label="本地 AI 内存趋势">
                        @foreach ([16, 58.5, 101, 143.5, 186] as $y)
                            <line x1="54" x2="976" y1="{{ $y }}" y2="{{ $y }}" class="stroke-gray-200 dark:stroke-gray-800" />
                        @endforeach
                        <text x="10" y="20" class="fill-gray-400 text-xs">100%</text>
                        <text x="10" y="190" class="fill-gray-400 text-xs">0</text>
                        <polyline points="{{ $chart['memory_points'] }}" fill="none" stroke="#ef4444" stroke-width="3" />
                        <polyline points="{{ $chart['free_points'] }}" fill="none" stroke="#3b82f6" stroke-width="3" />
                    </svg>
                @endunless

                <div class="mt-3 flex flex-wrap gap-4 text-xs text-gray-500 dark:text-gray-400">
                    <span class="inline-flex items-center gap-1"><i class="h-2 w-2 rounded-full bg-red-500"></i>内存占用 %</span>
                    <span class="inline-flex items-center gap-1"><i class="h-2 w-2 rounded-full bg-blue-500"></i>可用内存 MB，上限 {{ $chart['max_free'] ?? 0 }}</span>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
