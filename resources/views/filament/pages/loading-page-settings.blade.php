<x-filament-panels::page>
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(420px,0.9fr)]">
        <form wire:submit="save" class="space-y-4">
            {{ $this->form }}

            <div class="flex flex-wrap gap-3">
                <x-filament::button type="submit">
                    保存设置
                </x-filament::button>
                <x-filament::button type="button" color="gray" wire:click="resetDefaults">
                    恢复默认
                </x-filament::button>
            </div>
        </form>

        <section class="space-y-3">
            <div>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">页面预览</h2>
                <p class="mt-1 text-sm text-gray-500">预览和前台等待页共用同一套组件配置。</p>
            </div>
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                {!! $this->preview() !!}
            </div>
        </section>
    </div>
</x-filament-panels::page>
