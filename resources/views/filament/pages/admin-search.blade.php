<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-sm border border-slate-200 bg-white p-4 shadow-sm">
            <label class="block text-sm font-medium text-slate-700" for="admin-search-input">搜索后台功能或页面</label>
            <input
                id="admin-search-input"
                wire:model.live.debounce.250ms="search"
                class="mt-2 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm"
                type="search"
                placeholder="例如：付款、物流、regex:^订单、/用户|客户/"
            >
            <p class="mt-2 text-xs text-slate-500">支持普通关键词、regex:表达式、或 /表达式/。</p>
        </div>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @forelse($this->results() as $entry)
                <a class="rounded-sm border border-slate-200 bg-white p-4 shadow-sm transition hover:border-blue-300 hover:bg-blue-50" href="{{ $entry['url'] }}">
                    <span class="text-xs font-medium text-blue-700">{{ $entry['group'] }}</span>
                    <h2 class="mt-1 text-base font-semibold text-slate-950">{{ $entry['label'] }}</h2>
                    <p class="mt-2 line-clamp-2 text-sm text-slate-600">{{ $entry['keywords'] }}</p>
                </a>
            @empty
                <div class="rounded-sm border border-slate-200 bg-white p-6 text-sm text-slate-600">
                    没有找到匹配的后台功能。
                </div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
