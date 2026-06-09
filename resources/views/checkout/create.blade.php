<x-layouts.app title="结算">
    <section class="rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
            <h1 class="text-lg font-semibold">结算</h1>
        </div>

        <div class="grid gap-4 p-4 lg:grid-cols-[1fr_340px]">
            <form method="post" action="{{ route('checkout.store') }}" class="space-y-5">
                @csrf

                <section class="rounded-sm border border-slate-300">
                    <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">联系信息</h2>
                    <div class="grid gap-4 p-4 md:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-medium">联系人</span>
                            <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="contact_name" value="{{ old('contact_name', auth()->user()->name) }}" required>
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium">联系电话</span>
                            <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="contact_phone" value="{{ old('contact_phone') }}" required>
                        </label>
                        <label class="block md:col-span-2">
                            <span class="text-sm font-medium">邮箱</span>
                            <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" type="email" name="contact_email" value="{{ old('contact_email', auth()->user()->email) }}">
                        </label>
                        @if($requiresShipping)
                            <label class="block md:col-span-2">
                                <span class="text-sm font-medium">收货地址</span>
                                <textarea class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="shipping_address" rows="3" required>{{ old('shipping_address') }}</textarea>
                            </label>
                        @endif
                    </div>
                </section>

                <section class="rounded-sm border border-slate-300">
                    <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">优惠与备注</h2>
                    <div class="grid gap-4 p-4 md:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-medium">优惠码</span>
                            <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm uppercase" name="coupon_code" value="{{ old('coupon_code') }}">
                        </label>
                        <label class="block md:col-span-2">
                            <span class="text-sm font-medium">备注</span>
                            <textarea class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="customer_note" rows="3">{{ old('customer_note') }}</textarea>
                        </label>
                    </div>
                </section>

                <button class="rounded-sm border border-emerald-700 bg-emerald-700 px-5 py-2 text-sm font-medium text-white hover:bg-emerald-800" type="submit">提交订单</button>
            </form>

            <aside class="h-fit rounded-sm border border-slate-300">
                <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">订单商品</h2>
                <div class="divide-y divide-slate-100">
                    @foreach($items as $item)
                        <div class="px-4 py-3 text-sm">
                            <p class="font-medium">{{ $item['product']->title }}</p>
                            <p class="mt-1 text-slate-600">{{ $item['variant']->specLabel() }} x {{ $item['quantity'] }}</p>
                            <p class="mt-1 text-right font-medium">@money($item['line_total_cents'])</p>
                        </div>
                    @endforeach
                </div>
                <div class="border-t border-slate-200 bg-slate-50 px-4 py-4">
                    <div class="flex justify-between text-sm">
                        <span>商品小计</span>
                        <span class="font-semibold">@money($subtotalCents)</span>
                    </div>
                </div>
            </aside>
        </div>
    </section>
</x-layouts.app>
