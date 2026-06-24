<x-filament-widgets::widget>
    <x-filament::section
        heading="AI 通道监控"
        description="检测默认 AI 接口连通性；接口不可用时显示当前会回退到的本地工作流或本地模型。"
    >
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($channels as $channel)
                @php
                    $mode = $channel['active_mode'];
                    $tone = match ($mode) {
                        'api' => 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200',
                        'workflow' => 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-200',
                        'model' => 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200',
                        default => 'border-red-200 bg-red-50 text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200',
                    };
                    $modeLabel = match ($mode) {
                        'api' => '默认接口',
                        'workflow' => '本地工作流',
                        'model' => '本地模型',
                        default => '不可用',
                    };
                @endphp
                <article class="rounded-xl border {{ $tone }} p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold">{{ $channel['label'] }}</h3>
                            <p class="mt-1 text-xs opacity-80">{{ $channel['message'] }}</p>
                        </div>
                        <span class="rounded-full bg-white/70 px-2 py-1 text-xs font-semibold dark:bg-black/20">
                            {{ $modeLabel }}
                        </span>
                    </div>

                    <dl class="mt-4 grid gap-3 text-xs sm:grid-cols-2">
                        <div>
                            <dt class="opacity-70">接口</dt>
                            <dd class="mt-1 break-all font-medium">
                                @if ($channel['api']['configured'])
                                    {{ $channel['api']['host'] }}
                                    <span class="opacity-70">
                                        {{ $channel['api']['ok'] ? '正常' : '异常' }}
                                        @if ($channel['api']['ms'])
                                            / {{ $channel['api']['ms'] }} ms
                                        @endif
                                    </span>
                                @else
                                    无接口
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="opacity-70">接口信息</dt>
                            <dd class="mt-1 line-clamp-2 font-medium">{{ $channel['api']['message'] }}</dd>
                        </div>
                        <div>
                            <dt class="opacity-70">工作流</dt>
                            <dd class="mt-1 font-medium">
                                {{ $channel['workflow']['name'] ?? '未设置' }}
                                @if ($channel['workflow']['slug'] ?? null)
                                    <span class="opacity-70">({{ $channel['workflow']['slug'] }})</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="opacity-70">本地模型</dt>
                            <dd class="mt-1 font-medium">
                                {{ $channel['model']['name'] ?? '未发现' }}
                                @if ($channel['model']['id'] ?? null)
                                    <span class="opacity-70">/ {{ $channel['model']['id'] }}</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </article>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
