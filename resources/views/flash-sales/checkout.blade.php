<x-layouts.app title="秒杀结算">
    <section class="rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
            <h1 class="text-lg font-semibold">秒杀结算</h1>
            <p class="mt-1 text-xs text-slate-600">你已抢到秒杀名额，请选择规格并提交订单信息。取消订单不会把名额重新放回本场秒杀。</p>
        </div>

        <div class="grid gap-4 p-4 lg:grid-cols-[1fr_340px]">
            <form method="post" action="{{ route('flash-sales.store', $order) }}" class="space-y-5">
                @csrf

                <section class="rounded-sm border border-slate-300">
                    <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">选择规格</h2>
                    <div class="grid gap-4 p-4 md:grid-cols-2">
                        <label class="block md:col-span-2">
                            <span class="text-sm font-medium">规格</span>
                            <select class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm" name="product_variant_id" required>
                                @foreach($variants as $variant)
                                    <option value="{{ $variant->id }}">{{ $variant->specLabel() }} / 原价 @money($variant->effectivePriceCents()) / 库存 {{ $variant->stock }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </section>

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
                        @if($product->requiresShipping())
                            <label class="block md:col-span-2">
                                <span class="text-sm font-medium">收货地址</span>
                                <textarea class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="shipping_address" rows="3" required>{{ old('shipping_address') }}</textarea>
                            </label>
                            <label class="md:col-span-2 flex gap-3 rounded-sm border border-slate-200 bg-white px-3 py-3 text-sm">
                                <input class="mt-1" type="checkbox" name="private_shipping_requested" value="1" @checked((bool) old('private_shipping_requested', $privateShippingDefault ?? false))>
                                <span>
                                    <span class="block font-medium">私密发货</span>
                                    <span class="mt-1 block text-xs leading-5 text-slate-600">选择后会替换原产品包装，后台订单会标记该笔订单需要私密发货。</span>
                                </span>
                            </label>
                        @endif
                    </div>
                </section>

                <section class="rounded-sm border border-slate-300">
                    <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">备注</h2>
                    <div class="p-4">
                        <label class="block">
                            <span class="text-sm font-medium">备注</span>
                            <textarea class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="customer_note" rows="3">{{ old('customer_note') }}</textarea>
                        </label>
                    </div>
                </section>

                <section class="rounded-sm border border-slate-300">
                    <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">支付方式</h2>
                    <div class="grid gap-3 p-4 md:grid-cols-3">
                        @php($selectedPaymentMethod = old('payment_method', \App\Models\Order::PAYMENT_METHOD_QR_CODE))
                        @php($paypalEmail = ($siteSettings ?? null)?->paypalEmail())
                        <label class="flex cursor-pointer gap-3 rounded-sm border border-slate-300 bg-white px-3 py-3 text-sm hover:bg-slate-50">
                            <input class="mt-1" type="radio" name="payment_method" value="{{ \App\Models\Order::PAYMENT_METHOD_QR_CODE }}" @checked($selectedPaymentMethod === \App\Models\Order::PAYMENT_METHOD_QR_CODE)>
                            <span>
                                <span class="block font-medium">二维码支付</span>
                                <span class="mt-1 block text-xs leading-5 text-slate-600">提交后扫码付款，再上传付款凭证。</span>
                            </span>
                        </label>
                        <label class="flex cursor-pointer gap-3 rounded-sm border border-slate-300 bg-white px-3 py-3 text-sm hover:bg-slate-50">
                            <input class="mt-1" type="radio" name="payment_method" value="{{ \App\Models\Order::PAYMENT_METHOD_RED_PACKET }}" @checked($selectedPaymentMethod === \App\Models\Order::PAYMENT_METHOD_RED_PACKET)>
                            <span>
                                <span class="block font-medium">口令红包支付</span>
                                <span class="mt-1 block text-xs leading-5 text-slate-600">提交口令红包内容，由后台人工确认。</span>
                            </span>
                        </label>
                        @if($paypalEmail)
                            <label class="flex cursor-pointer gap-3 rounded-sm border border-slate-300 bg-white px-3 py-3 text-sm hover:bg-slate-50">
                                <input class="mt-1" type="radio" name="payment_method" value="{{ \App\Models\Order::PAYMENT_METHOD_PAYPAL }}" @checked($selectedPaymentMethod === \App\Models\Order::PAYMENT_METHOD_PAYPAL)>
                                <span>
                                    <span class="block font-medium">PayPal 支付</span>
                                    <span class="mt-1 block break-all text-xs leading-5 text-slate-600">收款邮箱：{{ $paypalEmail }}</span>
                                </span>
                            </label>
                        @endif
                    </div>
                    @if((int) auth()->user()->wallet_balance_cents > 0)
                        <div class="mx-4 mb-4 rounded-sm border border-emerald-200 bg-emerald-50 px-3 py-3 text-sm text-emerald-900">
                            钱包余额 @money((int) auth()->user()->wallet_balance_cents) 会在提交订单时自动优先抵扣，余额不足时再使用上方付款方式补齐尾款。
                        </div>
                    @endif
                </section>

                <button class="rounded-sm border border-pink-600 bg-pink-600 px-5 py-2 text-sm font-medium text-white hover:bg-pink-700" type="submit">提交订单信息</button>
            </form>

            <aside class="h-fit rounded-sm border border-slate-300">
                <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">秒杀商品</h2>
                <div class="px-4 py-4 text-sm">
                    <p class="font-medium">{{ $product->title }}</p>
                    <p class="mt-1 text-slate-600">{{ $flash_sale->name }}</p>
                    <p class="mt-3 text-2xl font-semibold text-red-700">@money($flash_sale->sale_price_cents)</p>
                    <p class="mt-1 text-slate-600">已抢名额 {{ $quantity }} 件</p>
                    <p class="mt-1 text-slate-600">开始 {{ $flash_sale->starts_at->format('Y-m-d H:i') }}</p>
                    @if($flash_sale->ends_at)
                        <p class="mt-1 text-slate-600">结束 {{ $flash_sale->ends_at->format('Y-m-d H:i') }}</p>
                    @endif
                </div>
                <div class="border-t border-slate-200 bg-slate-50 px-4 py-4">
                    @php($flashSaleTotalCents = (int) $flash_sale->sale_price_cents * (int) $quantity)
                    @php($flashSaleWalletCents = min((int) auth()->user()->wallet_balance_cents, $flashSaleTotalCents))
                    <div class="flex justify-between text-sm">
                        <span>秒杀小计</span>
                        <span class="font-semibold">@money($flashSaleTotalCents)</span>
                    </div>
                    <div class="mt-2 flex justify-between border-t border-slate-200 pt-3 text-sm">
                        <span>付款总金额</span>
                        <span class="font-semibold">@money($flashSaleTotalCents)</span>
                    </div>
                    <div class="mt-2 flex justify-between text-sm text-emerald-700">
                        <span>钱包支付金额</span>
                        <span class="font-semibold">@money($flashSaleWalletCents)</span>
                    </div>
                    <div class="mt-2 flex justify-between text-base font-semibold text-red-700">
                        <span>待支付金额</span>
                        <span>@money(max(0, $flashSaleTotalCents - $flashSaleWalletCents))</span>
                    </div>
                </div>
            </aside>
        </div>
    </section>
</x-layouts.app>
