<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm text-gray-500">当前基准</p>
                <p class="mt-2 text-lg font-semibold text-gray-950 dark:text-white">{{ $this->baseSummary() }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm text-gray-500">汇率更新时间</p>
                <p class="mt-2 text-lg font-semibold text-gray-950 dark:text-white">{{ $this->ratesUpdatedAt() }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm text-gray-500">黄金价格</p>
                <p class="mt-2 text-lg font-semibold text-gray-950 dark:text-white">{{ $this->goldSummary() }}</p>
            </div>
        </div>

        <form wire:submit="save" class="space-y-4">
            {{ $this->form }}

            <div class="flex flex-wrap gap-3">
                <x-filament::button type="submit">
                    保存设置
                </x-filament::button>
                <x-filament::button type="button" color="gray" wire:click="refreshRates">
                    刷新国际汇率与黄金快照
                </x-filament::button>
            </div>
        </form>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-4">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">汇率换算器</h2>
                <p class="mt-1 text-sm text-gray-500">换算使用自动获取的国际汇率快照；业务成交与利润统计最终都会折算为基准货币。</p>
            </div>
            <form wire:submit="convertCurrency" class="grid gap-3 md:grid-cols-[1fr_1fr_1fr_1fr_auto]">
                <label class="block">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">金额</span>
                    <input class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" type="number" step="0.0001" wire:model="converter_amount">
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">来源货币</span>
                    <select class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" wire:model.live="converter_from">
                        @foreach($this->currencyOptions() as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">来源单位</span>
                    <select class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" wire:model="converter_from_unit">
                        @foreach($this->unitOptions($converter_from) as $unit => $label)
                            <option value="{{ $unit }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">目标货币</span>
                    <select class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" wire:model.live="converter_to">
                        @foreach($this->currencyOptions() as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex items-end">
                    <x-filament::button type="submit" class="w-full">
                        换算
                    </x-filament::button>
                </div>
                <label class="block md:col-span-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">目标单位</span>
                    <select class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950" wire:model="converter_to_unit">
                        @foreach($this->unitOptions($converter_to) as $unit => $label)
                            <option value="{{ $unit }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-900 md:col-span-3 dark:border-blue-900/50 dark:bg-blue-950/40 dark:text-blue-100">
                    结果：{{ $converter_result ?: '等待换算' }}
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">自动汇率快照</h2>
                <p class="mt-1 text-sm text-gray-500">只读展示；需要更新时点击上方“刷新国际汇率与黄金快照”。</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-950">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">代码</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">货币</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">1 单位该货币折算为基准货币</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($this->rateRows() as $row)
                            <tr>
                                <td class="px-4 py-2 font-mono text-gray-950 dark:text-white">{{ $row['code'] }}</td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-200">{{ $row['name'] }}</td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-200">{{ $row['rate'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
