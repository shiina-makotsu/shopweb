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
                            <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="contact_name" value="{{ old('contact_name', $defaultAddress?->recipient_name ?? auth()->user()->name) }}" required>
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium">联系电话</span>
                            <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="contact_phone" value="{{ old('contact_phone', $defaultAddress?->phone) }}" required>
                        </label>
                        <label class="block md:col-span-2">
                            <span class="text-sm font-medium">邮箱</span>
                            <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" type="email" name="contact_email" value="{{ old('contact_email', auth()->user()->email) }}">
                        </label>
                        @if($requiresShipping)
                            <label class="block">
                                <span class="text-sm font-medium">收货省份 / 地区</span>
                                <select class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="shipping_province" required>
                                    <option value="">请选择省份</option>
                                    @foreach($provinceOptions as $provinceValue => $provinceLabel)
                                        <option value="{{ $provinceValue }}" @selected(old('shipping_province', $shippingProvince) === $provinceValue)>{{ $provinceLabel }}</option>
                                    @endforeach
                                </select>
                                <span class="mt-1 block text-xs text-slate-500">邮费按省份匹配；没有单独设置的省份会使用“其他地区”邮费。</span>
                            </label>
                            <label class="block md:col-span-2">
                                <span class="text-sm font-medium">收货地址</span>
                                <textarea class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="shipping_address" rows="3" required>{{ old('shipping_address', $defaultAddress?->formatted()) }}</textarea>
                            </label>
                        @endif
                    </div>
                </section>

                <section class="rounded-sm border border-slate-300">
                    <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">优惠与备注</h2>
                    <div class="grid gap-4 p-4">
                        <div class="space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-sm font-medium">可用优惠码</span>
                                <a class="text-xs font-medium text-blue-700 hover:text-blue-900" href="{{ route('user.section', 'coupons') }}">管理我的优惠码</a>
                            </div>
                            @foreach($items as $item)
                                @php($lineCoupons = $availableCouponsByVariant[(int) $item['variant']->id] ?? [])
                                @if($lineCoupons !== [])
                                    <label class="block rounded-sm border border-slate-200 bg-white px-3 py-3">
                                        <span class="block text-xs font-medium text-slate-700">{{ $item['product']->title }} / {{ $item['variant']->specLabel() }}</span>
                                        <select class="mt-2 w-full rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm" name="coupon_items[{{ $item['variant']->id }}]">
                                            <option value="">不使用优惠码</option>
                                            @foreach($lineCoupons as $userCoupon)
                                                @php($coupon = $userCoupon->coupon)
                                                <option value="{{ $userCoupon->id }}" @selected((string) old('coupon_items.'.$item['variant']->id) === (string) $userCoupon->id)>
                                                    {{ $coupon->name }} / {{ $coupon->code }} / {{ $coupon->discountLabel() }} / {{ $coupon->scopeLabel() }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </label>
                                @endif
                            @endforeach
                            @error('coupon_items')
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
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
                    @if($requiresShipping)
                        <div class="mt-2 flex justify-between text-sm">
                            <span>邮费{{ $shippingProvince ? '（'.$shippingProvince.'）' : '' }}</span>
                            <span class="font-semibold">@money($shippingQuote['shipping_fee_cents'])</span>
                        </div>
                        @if($shippingQuote['notice'])
                            <div class="mt-3 rounded-sm border border-amber-300 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-900">
                                {{ $shippingQuote['notice'] }}
                            </div>
                        @endif
                        @if(! empty($shippingQuote['shipments']))
                            <div class="mt-3 space-y-2 text-xs text-slate-600">
                                @foreach($shippingQuote['shipments'] as $shipment)
                                    <div class="rounded-sm border border-slate-200 bg-white px-3 py-2">
                                        <div class="flex justify-between gap-3 font-medium text-slate-800">
                                            <span>{{ $shipment['warehouse_name'] }}</span>
                                            <span>@money($shipment['fee_cents'])</span>
                                        </div>
                                        <p class="mt-1">
                                            @foreach($shipment['items'] as $shipmentItem)
                                                <span>{{ $shipmentItem['title'] }} x {{ $shipmentItem['quantity'] }}</span>@if(! $loop->last)<span>，</span>@endif
                                            @endforeach
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @endif
                    <div class="mt-2 flex justify-between border-t border-slate-200 pt-3 text-sm">
                        <span>预计应付</span>
                        <span class="font-semibold">@money($subtotalCents + (int) $shippingQuote['shipping_fee_cents'])</span>
                    </div>
                </div>
            </aside>
        </div>
    </section>
</x-layouts.app>
