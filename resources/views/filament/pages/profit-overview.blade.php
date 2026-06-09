<x-filament-panels::page>
    <section class="rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 px-4 py-3">
            <h2 class="text-lg font-semibold">利润概览</h2>
            <p class="mt-1 text-sm text-slate-600">利润 = 已确认付款订单金额 - 成本条目总额。</p>
        </div>
        <div class="grid gap-px bg-slate-200 md:grid-cols-4">
            @foreach($this->metrics() as $label => $value)
                <div class="bg-white px-4 py-5">
                    <p class="text-sm text-slate-600">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ $value }}</p>
                </div>
            @endforeach
        </div>
    </section>
</x-filament-panels::page>
