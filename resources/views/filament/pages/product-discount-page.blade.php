<x-filament-panels::page>
    <form wire:submit="save" class="space-y-4">
        {{ $this->form }}

        <div class="shop-settings-form-actions">
            <x-filament::button type="submit">
                保存设置
            </x-filament::button>
        </div>
    </form>

    <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="mb-4">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">当前折扣商品</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">可直接修改折扣价或取消折扣；SKU 前会显示商品名和商品状态，避免同名规格混淆。</p>
        </div>

        @php($variants = $this->discountedVariants())

        @if($variants->isEmpty())
            <p class="rounded-md border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">暂无已设置折扣的 SKU。</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-sm">
                    <thead class="border-b border-gray-200 text-left text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2 font-medium">商品 / 状态</th>
                            <th class="px-3 py-2 font-medium">SKU / 规格</th>
                            <th class="px-3 py-2 text-right font-medium">原价</th>
                            <th class="px-3 py-2 text-right font-medium">折扣价</th>
                            <th class="px-3 py-2 font-medium">有效时间</th>
                            <th class="px-3 py-2 text-right font-medium">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($variants as $variant)
                            <tr wire:key="discount-variant-{{ $variant->id }}">
                                <td class="px-3 py-3">
                                    <p class="font-medium text-gray-950 dark:text-white">{{ $variant->product?->title ?? '商品已删除' }}</p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $this->statusLabel($variant->product?->status) }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <p class="font-medium text-gray-800 dark:text-gray-100">{{ $variant->sku ?: '-' }}</p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $variant->displayName() }}</p>
                                </td>
                                <td class="px-3 py-3 text-right text-gray-700 dark:text-gray-200">@money($variant->price_cents)</td>
                                <td class="px-3 py-3">
                                    <input
                                        class="w-28 rounded-md border border-gray-300 px-2 py-1 text-right text-sm dark:border-gray-700 dark:bg-gray-950"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        wire:model.defer="discountRows.{{ $variant->id }}.discount_price"
                                    >
                                </td>
                                <td class="px-3 py-3 text-xs text-gray-500 dark:text-gray-400">
                                    <p>{{ $variant->discount_starts_at?->format('Y-m-d H:i') ?? '立即开始' }}</p>
                                    <p class="mt-1">{{ $variant->discount_ends_at?->format('Y-m-d H:i') ?? '长期有效' }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex justify-end gap-2">
                                        <x-filament::button size="sm" color="primary" wire:click="updateDiscount({{ $variant->id }})" type="button">
                                            改价
                                        </x-filament::button>
                                        <x-filament::button size="sm" color="danger" wire:click="cancelDiscount({{ $variant->id }})" type="button">
                                            取消折扣
                                        </x-filament::button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-filament-panels::page>
