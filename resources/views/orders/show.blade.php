<x-layouts.app :title="$privacy->displayOrderNumber($order, auth()->user(), $settings)">
    @php($statusPresenter = app(\App\Support\OrderStatusPresenter::class))
    @php($pendingFlashSaleItem = $order->items->first(fn ($item) => $item->flash_sale_id && ! $item->product_variant_id))

    <section class="mb-4 rounded-sm border border-slate-300 bg-white">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-100 px-4 py-3">
            <div>
                <h1 class="text-lg font-semibold">{{ $privacy->displayOrderNumber($order, auth()->user(), $settings) }}</h1>
                <p class="mt-1 text-xs text-slate-600">订单状态：{{ $statusPresenter->label($order->status) }} / 付款状态：{{ $order->userPaymentLabel() }}</p>
            </div>
            <p class="text-2xl font-semibold text-red-700">@money($order->total_cents)</p>
        </div>

        <div class="grid gap-4 p-4 lg:grid-cols-[1fr_340px]">
            <section class="space-y-4">
                @if($pendingFlashSaleItem)
                    <div class="rounded-sm border border-pink-300 bg-pink-50 px-4 py-3 text-sm text-pink-950">
                        该秒杀订单已抢到名额，但还没有选择规格。
                        <a class="ml-2 font-medium text-pink-800 underline" href="{{ route('flash-sales.checkout', $order) }}">去选择规格</a>
                    </div>
                @endif

                <div class="rounded-sm border border-slate-300">
                    <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">商品</h2>
                    <div class="divide-y divide-slate-100 md:hidden">
                        @foreach($order->items as $item)
                            <article class="px-4 py-3 text-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-medium">{{ $item->product_title }}</p>
                                        <p class="mt-1 text-slate-600">
                                            {{ $item->variant_sku }} /
                                            {{ collect($item->variant_specs ?? [])->map(fn($v, $k) => "$k: $v")->implode(' / ') ?: '默认规格' }}
                                        </p>
                                    </div>
                                    <p class="shrink-0 font-semibold">@money($item->line_total_cents)</p>
                                </div>
                                <div class="mt-2 flex justify-between text-slate-600">
                                    <span>单价 @money($item->unit_price_cents)</span>
                                    <span>数量 {{ $item->quantity }}</span>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <div class="hidden overflow-x-auto md:block">
                        <table class="w-full min-w-[560px] text-sm">
                            <thead class="text-left text-slate-600">
                                <tr>
                                    <th class="px-4 py-3 font-medium">商品</th>
                                    <th class="px-4 py-3 font-medium">规格/SKU</th>
                                    <th class="px-4 py-3 text-right font-medium">单价</th>
                                    <th class="px-4 py-3 text-center font-medium">数量</th>
                                    <th class="px-4 py-3 text-right font-medium">小计</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($order->items as $item)
                                    <tr>
                                        <td class="px-4 py-3 font-medium">{{ $item->product_title }}</td>
                                        <td class="px-4 py-3 text-slate-600">
                                            {{ $item->variant_sku }} /
                                            {{ collect($item->variant_specs ?? [])->map(fn($v, $k) => "$k: $v")->implode(' / ') ?: '默认规格' }}
                                        </td>
                                        <td class="px-4 py-3 text-right">@money($item->unit_price_cents)</td>
                                        <td class="px-4 py-3 text-center">{{ $item->quantity }}</td>
                                        <td class="px-4 py-3 text-right font-medium">@money($item->line_total_cents)</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-sm border border-slate-300">
                    <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">付款说明</h2>
                    <div class="content-body px-4 py-4 text-sm">
                        @if($settings?->payment_qr_path)
                            <img class="mb-4 h-40 w-40 rounded-sm border border-slate-200 object-contain" src="{{ \Illuminate\Support\Facades\Storage::disk('public_uploads')->url($settings->payment_qr_path) }}" alt="付款二维码">
                        @endif
                        <div class="mb-4 rounded-sm border border-blue-200 bg-blue-50 px-3 py-3 text-blue-900">
                            <p class="font-medium">付款备注单号：{{ $order->order_number }}</p>
                            @if($settings?->payment_account_name)
                                <p class="mt-1">收款账户：{{ $settings->payment_account_name }}</p>
                            @endif
                            @if($settings?->payment_account_note)
                                <p class="mt-1 whitespace-pre-line">{{ $settings->payment_account_note }}</p>
                            @endif
                        </div>
                        {{ \App\Support\Markdown::render($settings?->payment_instructions ?: "浏览商品、加入购物车、提交订单后上传付款凭证，由后台人工确认付款。\n\n请联系管理员获取付款方式。") }}
                    </div>
                </div>
            </section>

            <aside class="space-y-4">
                <div class="rounded-sm border border-slate-300">
                    <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">金额</h2>
                    <dl class="space-y-2 px-4 py-4 text-sm">
                        <div class="flex justify-between"><dt>商品小计</dt><dd>@money($order->subtotal_cents)</dd></div>
                        <div class="flex justify-between"><dt>优惠</dt><dd>- @money($order->discount_cents)</dd></div>
                        <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold"><dt>应付</dt><dd>@money($order->total_cents)</dd></div>
                    </dl>
                </div>

                <div class="rounded-sm border border-slate-300">
                    <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">付款凭证</h2>
                    <div class="px-4 py-4 text-sm">
                        @if($order->payment_proof_path)
                            <p class="mb-3 text-emerald-700">已付款，后台会继续人工复核。</p>
                        @endif
                        @if($pendingFlashSaleItem)
                            <p class="text-slate-600">请先选择规格后再上传付款凭证。</p>
                        @elseif(! in_array($order->payment_status, ['confirmed'], true))
                            <form method="post" action="{{ route('orders.payment-proof', $order) }}" enctype="multipart/form-data" class="space-y-3">
                                @csrf
                                <input class="w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" type="file" name="payment_proof" accept="image/*" required>
                                <button class="rounded-sm border border-emerald-700 bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800" type="submit">上传凭证</button>
                            </form>
                        @else
                            <p class="text-emerald-700">付款已确认。</p>
                        @endif
                    </div>
                </div>

                <div class="rounded-sm border border-slate-300">
                    <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">物流信息</h2>
                    <div class="space-y-2 px-4 py-4 text-sm text-slate-700">
                        <p>承运商：{{ $order->shippingCarrier?->name ?? '暂无' }}</p>
                        <p>物流单号：{{ $privacy->displayTrackingNumber($order, auth()->user(), $settings) }}</p>
                        @if($order->tracking_url && $privacy->canViewTrackingNumberForOrder($order, auth()->user(), $settings))
                            <a class="inline-flex rounded-sm border border-blue-700 bg-blue-700 px-3 py-2 text-xs font-medium text-white hover:bg-blue-800" href="{{ $order->tracking_url }}" target="_blank" rel="noopener">打开物流查询</a>
                        @else
                            <a class="inline-flex rounded-sm border border-slate-300 px-3 py-2 text-xs font-medium hover:bg-slate-50" href="{{ route('shipments.show') }}">查询物流</a>
                        @endif
                    </div>
                </div>

                <div class="rounded-sm border border-slate-300">
                    <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">售后与客服</h2>
                    <div class="space-y-3 px-4 py-4 text-sm">
                        <a class="inline-flex w-full justify-center rounded-sm border border-blue-700 bg-blue-700 px-3 py-2 font-medium text-white hover:bg-blue-800" href="{{ route('orders.after-sales', $order) }}">提交售后需求</a>
                        <form method="post" action="{{ route('orders.contact-support', $order) }}">
                            @csrf
                            <button class="w-full rounded-sm border border-slate-300 px-3 py-2 font-medium hover:bg-slate-50" type="submit">带订单信息联系客服</button>
                        </form>
                    </div>
                </div>

                <div class="rounded-sm border border-slate-300">
                    <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">联系信息</h2>
                    <div class="space-y-1 px-4 py-4 text-sm text-slate-700">
                        <p>{{ $order->contact_name }}</p>
                        <p>{{ $order->contact_phone }}</p>
                        @if($order->contact_email)<p>{{ $order->contact_email }}</p>@endif
                        @if($order->shipping_address)<p class="mt-2 whitespace-pre-line">{{ $order->shipping_address }}</p>@endif
                    </div>
                </div>
            </aside>
        </div>
    </section>
</x-layouts.app>
