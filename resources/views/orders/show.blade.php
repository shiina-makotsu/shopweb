<x-layouts.app :title="$privacy->displayOrderNumber($order, auth()->user(), $settings)">
    @php($statusPresenter = app(\App\Support\OrderStatusPresenter::class))
    @php($pendingFlashSaleItem = $order->items->first(fn ($item) => $item->flash_sale_id && ! $item->product_variant_id))
    @php($productStatuses = \App\Models\Product::statusOptions())
    @php($isPaymentPage = $order->status === \App\Models\Order::STATUS_PENDING_PAYMENT && ! in_array($order->payment_status, ['confirmed'], true))
    @php($fallbackPayment = $settings?->payment_fallback_config ?: [])
    @php($paymentQrUrl = $settings?->paymentQrUrl())
    @php($fallbackQrUrl = $settings?->paymentFallbackQrUrl())
    @php($friendQrUrl = $settings?->paymentFriendQrUrl())
    @php($paypalEmail = $settings?->paypalEmail())
    @php($hasPasswordRedPacket = (bool) ($fallbackPayment['password_red_packet_enabled'] ?? false))
    @php($isWalletRecharge = $order->isWalletRecharge())
    @php($walletRechargeSuccessMessage = $settings?->wallet_recharge_success_message ?: '钱包充值已到账，余额已经更新。')
    @php($paymentTimeoutMinutes = max(1, (int) ($settings?->payment_pending_timeout_minutes ?: 10)))
    @php($paymentExpiresAt = $isPaymentPage ? $order->created_at?->copy()->addMinutes($paymentTimeoutMinutes) : null)

    @if($isPaymentPage)
        @push('head')
            @foreach(array_filter([$paymentQrUrl, $fallbackQrUrl, $friendQrUrl]) as $preloadPaymentImage)
                <link rel="preload" as="image" href="{{ $preloadPaymentImage }}">
            @endforeach
        @endpush
    @endif

    <section class="mb-4 rounded-sm border border-slate-300 bg-white">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-100 px-4 py-3">
            <div>
                <h1 class="text-lg font-semibold">{{ $privacy->displayOrderNumber($order, auth()->user(), $settings) }}</h1>
                <p class="mt-1 text-xs text-slate-600">订单状态：{{ $order->userStatusLabel($statusPresenter->label($order->status)) }} / 付款状态：{{ $order->userPaymentLabel() }}</p>
            </div>
            <p class="text-2xl font-semibold text-red-700">@money($order->paymentTotalCents())</p>
        </div>

        <div class="grid min-w-0 gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_minmax(280px,340px)]">
            <section class="min-w-0 space-y-4">
                @if($pendingFlashSaleItem)
                    <div class="rounded-sm border border-pink-300 bg-pink-50 px-4 py-3 text-sm text-pink-950">
                        该秒杀订单已抢到名额，但还没有选择规格。
                        <a class="ml-2 font-medium text-pink-800 underline" href="{{ route('flash-sales.checkout', $order) }}">去选择规格</a>
                    </div>
                @endif
                @if($order->admin_note)
                    <div class="rounded-sm border border-amber-300 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950">
                        <p class="font-medium">后台处理备注</p>
                        <p class="mt-1 whitespace-pre-line">{{ $order->admin_note }}</p>
                    </div>
                @endif
                @if($isPaymentPage && $paymentExpiresAt)
                    <div
                        class="rounded-sm border border-amber-300 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950"
                        data-payment-countdown
                        data-expires-at="{{ $paymentExpiresAt->toIso8601String() }}"
                    >
                        <p class="font-medium">付款倒计时</p>
                        <p class="mt-1">请在 <span class="font-semibold tabular-nums" data-payment-countdown-text>--:--</span> 内提交付款凭证，超时后订单会自动关闭。</p>
                    </div>
                @endif

                <div class="rounded-sm border border-slate-300">
                    <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">商品</h2>
                    @if($isWalletRecharge)
                        <div class="px-4 py-5 text-sm">
                            <p class="font-semibold">钱包充值</p>
                            <p class="mt-2 text-slate-600">充值金额：@money((int) $order->wallet_recharge_cents)</p>
                            <p class="mt-1 text-slate-600">确认收款后，系统会自动把金额加入钱包余额。</p>
                        </div>
                    @else
                    <div class="divide-y divide-slate-100 md:hidden">
                        @foreach($order->items as $item)
                            <article class="px-4 py-3 text-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-medium">{{ $item->product_title }}</p>
                                        <p class="mt-1 text-slate-600">
                                            {{ $item->variant_sku }} /
                                            {{ \App\Models\ProductVariant::specsLabel($item->variant_specs ?? []) }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-600">
                                            当前商品：{{ $item->incomingProduct?->title ?? $item->product_title }} /
                                            {{ $productStatuses[$item->incomingProduct?->status ?? $item->product_status] ?? ($item->incomingProduct?->status ?? $item->product_status) }} /
                                            {{ $statusPresenter->label($item->status) }}
                                        </p>
                                        @if($item->coupon_code)
                                            <p class="mt-1 text-xs text-emerald-700">优惠码 {{ $item->coupon_code }}：- @money($item->discount_cents)</p>
                                        @endif
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
                        <table class="w-full min-w-[720px] text-sm">
                            <thead class="text-left text-slate-600">
                                <tr>
                                    <th class="px-4 py-3 font-medium">商品</th>
                                    <th class="px-4 py-3 font-medium">规格/SKU</th>
                                    <th class="px-4 py-3 font-medium">当前状态</th>
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
                                            {{ \App\Models\ProductVariant::specsLabel($item->variant_specs ?? []) }}
                                            @if($item->coupon_code)
                                                <p class="mt-1 text-xs text-emerald-700">优惠码 {{ $item->coupon_code }}：- @money($item->discount_cents)</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-slate-600">
                                            <p>{{ $item->incomingProduct?->title ?? $item->product_title }}</p>
                                            <p class="mt-1 text-xs">
                                                {{ $productStatuses[$item->incomingProduct?->status ?? $item->product_status] ?? ($item->incomingProduct?->status ?? $item->product_status) }}
                                                / {{ $statusPresenter->label($item->status) }}
                                            </p>
                                        </td>
                                        <td class="px-4 py-3 text-right">@money($item->unit_price_cents)</td>
                                        <td class="px-4 py-3 text-center">{{ $item->quantity }}</td>
                                        <td class="px-4 py-3 text-right font-medium">@money($item->line_total_cents)</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>

                @if($isPaymentPage)
                    <div class="rounded-sm border border-slate-300">
                        <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">付款说明</h2>
                        <div class="content-body px-4 py-4 text-sm">
                            <div class="mb-3 rounded-sm border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                当前支付方式：<span class="font-semibold">{{ $order->paymentMethodLabel() }}</span>
                            </div>
                            @if((int) $order->wallet_payment_cents > 0)
                                <div class="mb-4 rounded-sm border border-emerald-200 bg-emerald-50 px-3 py-3 text-emerald-900">
                                    <div class="flex justify-between gap-3"><span>付款总金额</span><span class="font-semibold">@money($order->paymentTotalCents())</span></div>
                                    <div class="mt-1 flex justify-between gap-3"><span>钱包支付金额</span><span class="font-semibold">@money($order->walletPaymentCents())</span></div>
                                    <div class="mt-1 flex justify-between gap-3"><span>待支付金额</span><span class="font-semibold">@money($order->remainingPaymentCents())</span></div>
                                </div>
                            @endif
                            @if(! $paymentQrUrl && $paypalEmail)
                                <div class="mb-4 rounded-sm border border-blue-200 bg-blue-50 px-3 py-3 text-blue-900">
                                    <p class="font-medium">PayPal 收款邮箱</p>
                                    <p class="mt-1 break-all">{{ $paypalEmail }}</p>
                                    <p class="mt-2 text-sm">完成 PayPal 付款后，请在付款凭证区上传截图或填写付款说明。</p>
                                    @if($order->payment_method !== \App\Models\Order::PAYMENT_METHOD_PAYPAL)
                                        <form class="mt-2" method="post" action="{{ route('orders.payment-method', $order) }}">
                                            @csrf
                                            <input type="hidden" name="payment_method" value="{{ \App\Models\Order::PAYMENT_METHOD_PAYPAL }}">
                                            <button class="rounded-sm border border-blue-700 bg-white px-3 py-2 text-xs font-medium text-blue-900 hover:bg-blue-50" type="submit">改用 PayPal 支付</button>
                                        </form>
                                    @endif
                                </div>
                                @if($hasPasswordRedPacket)
                                    <div class="mb-4 rounded-sm border border-amber-200 bg-amber-50 px-3 py-3 text-amber-950">
                                        <p class="font-medium">口令红包付款</p>
                                        <p class="mt-1 whitespace-pre-line">{{ $fallbackPayment['password_red_packet_note'] ?? '请在下方填写口令红包内容，后台会人工确认收款。' }}</p>
                                    </div>
                                @endif
                            @elseif($order->payment_method === \App\Models\Order::PAYMENT_METHOD_RED_PACKET && ! $paymentQrUrl)
                                <div class="mb-4 rounded-sm border border-amber-200 bg-amber-50 px-3 py-3 text-amber-950">
                                    <p class="font-medium">口令红包付款</p>
                                    <p class="mt-1 whitespace-pre-line">{{ $fallbackPayment['password_red_packet_note'] ?? '请在下方填写口令红包内容，后台会人工确认收款。' }}</p>
                                </div>
                            @elseif($order->payment_method === \App\Models\Order::PAYMENT_METHOD_FALLBACK_QR && $fallbackQrUrl)
                                <img class="mb-4 h-40 w-40 rounded-sm border border-slate-200 object-contain" src="{{ $fallbackQrUrl }}" alt="备用付款二维码" loading="eager" fetchpriority="high" decoding="async">
                            @elseif($paymentQrUrl && ! in_array($order->payment_method, [\App\Models\Order::PAYMENT_METHOD_RED_PACKET, \App\Models\Order::PAYMENT_METHOD_PAYPAL], true))
                                <img class="mb-4 h-40 w-40 rounded-sm border border-slate-200 object-contain" src="{{ $paymentQrUrl }}" alt="付款二维码" loading="eager" fetchpriority="high" decoding="async">
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
                            {{ \App\Support\Markdown::render($settings?->payment_instructions ?: "请按页面显示的付款备注单号完成转账，并上传付款截图。\n\n截图上传后系统会自动识别并显示付款成功。请联系管理员获取付款方式。") }}
                            @if($fallbackQrUrl || $friendQrUrl || ($paymentQrUrl && $paypalEmail) || ($paymentQrUrl && $hasPasswordRedPacket) || ($fallbackPayment['support_enabled'] ?? true))
                                <button class="mt-4 rounded-sm border border-amber-300 bg-white px-3 py-2 text-sm font-medium text-amber-900 hover:bg-amber-50" type="button" data-payment-fallback-toggle>
                                    支付失败 / 付款码不可用
                                </button>
                                <div class="mt-3 hidden rounded-sm border border-amber-200 bg-amber-50 px-3 py-3 text-amber-950" data-payment-fallback-panel>
                                    <p class="font-medium">支付受限时的备选方案</p>
                                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                        @if($fallbackQrUrl)
                                            <div>
                                                <p class="mb-2 text-xs font-medium text-amber-900">备用付款码</p>
                                                <img class="h-32 w-32 rounded-sm border border-amber-200 bg-white object-contain" src="{{ $fallbackQrUrl }}" alt="备用付款码" loading="eager" fetchpriority="high" decoding="async">
                                                <form class="mt-2" method="post" action="{{ route('orders.payment-method', $order) }}">
                                                    @csrf
                                                    <input type="hidden" name="payment_method" value="{{ \App\Models\Order::PAYMENT_METHOD_FALLBACK_QR }}">
                                                    <button class="rounded-sm border border-amber-700 bg-white px-3 py-2 text-xs font-medium text-amber-900 hover:bg-amber-100" type="submit">切换备用二维码</button>
                                                </form>
                                            </div>
                                        @endif
                                        @if($friendQrUrl)
                                            <div>
                                                <p class="mb-2 text-xs font-medium text-amber-900">好友码 / 联系码</p>
                                                <img class="h-32 w-32 rounded-sm border border-amber-200 bg-white object-contain" src="{{ $friendQrUrl }}" alt="好友码" loading="eager" fetchpriority="high" decoding="async">
                                            </div>
                                        @endif
                                    </div>
                                    @if($hasPasswordRedPacket && $paymentQrUrl)
                                        <p class="mt-3 whitespace-pre-line text-sm">{{ $fallbackPayment['password_red_packet_note'] ?? '如果二维码支付受限，可以提交支付宝口令红包，后台人工确认后会更新订单付款状态。' }}</p>
                                        <form class="mt-2" method="post" action="{{ route('orders.payment-method', $order) }}">
                                            @csrf
                                            <input type="hidden" name="payment_method" value="{{ \App\Models\Order::PAYMENT_METHOD_RED_PACKET }}">
                                            <button class="rounded-sm border border-amber-700 bg-white px-3 py-2 text-xs font-medium text-amber-900 hover:bg-amber-100" type="submit">改用口令红包支付</button>
                                        </form>
                                    @endif
                                    @if($paypalEmail && $paymentQrUrl && $order->payment_method !== \App\Models\Order::PAYMENT_METHOD_PAYPAL)
                                        <p class="mt-3 break-all text-sm">PayPal 收款邮箱：{{ $paypalEmail }}</p>
                                        <form class="mt-2" method="post" action="{{ route('orders.payment-method', $order) }}">
                                            @csrf
                                            <input type="hidden" name="payment_method" value="{{ \App\Models\Order::PAYMENT_METHOD_PAYPAL }}">
                                            <button class="rounded-sm border border-blue-700 bg-white px-3 py-2 text-xs font-medium text-blue-900 hover:bg-blue-50" type="submit">改用 PayPal 支付</button>
                                        </form>
                                    @endif
                                    @if($fallbackPayment['support_enabled'] ?? true)
                                        <a class="mt-3 inline-flex rounded-sm border border-amber-700 bg-white px-3 py-2 text-xs font-medium text-amber-900 hover:bg-amber-100" href="{{ route('support.index') }}">联系客服处理支付问题</a>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                @if($order->hasDigitalDelivery())
                    <div id="digital-delivery" class="rounded-sm border border-emerald-300 bg-emerald-50">
                        <h2 class="border-b border-emerald-200 bg-emerald-100 px-4 py-2 text-sm font-semibold text-emerald-950">线上交付内容</h2>
                        <div class="space-y-4 px-4 py-4 text-sm text-emerald-950">
                            @if($order->digital_delivery_content)
                                <div class="rounded-sm border border-emerald-200 bg-white px-3 py-3">
                                    <p class="font-medium">交付说明</p>
                                    <p class="mt-2 whitespace-pre-line leading-6">{{ $order->digital_delivery_content }}</p>
                                </div>
                            @endif
                            @if($order->digital_delivery_code)
                                <form method="post" action="{{ route('orders.digital-delivery.copied', $order) }}" class="rounded-sm border border-emerald-200 bg-white px-3 py-3" data-copy-delivery-form>
                                    @csrf
                                    <p class="font-medium">兑换码/序列号</p>
                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        <code class="rounded-sm bg-slate-100 px-3 py-2 text-slate-900">{{ $order->digital_delivery_code }}</code>
                                        <button
                                            class="rounded-sm border border-emerald-700 bg-emerald-700 px-3 py-2 text-xs font-medium text-white hover:bg-emerald-800"
                                            type="submit"
                                            data-copy-delivery-code="{{ $order->digital_delivery_code }}"
                                        >
                                            复制交付码
                                        </button>
                                    </div>
                                </form>
                            @endif
                            @if($order->digital_delivery_attachment_paths)
                                <div class="rounded-sm border border-emerald-200 bg-white px-3 py-3">
                                    <p class="font-medium">订单附件</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach($order->digital_delivery_attachment_paths as $index => $path)
                                            <a class="rounded-sm border border-emerald-700 bg-emerald-700 px-3 py-2 text-xs font-medium text-white hover:bg-emerald-800" href="{{ route('orders.digital-delivery.download', [$order, $index]) }}">
                                                下载附件 {{ $loop->iteration }}
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            @if($order->status === \App\Models\Order::STATUS_AWAITING_RECEIPT)
                                <form method="post" action="{{ route('orders.confirm-receipt', $order) }}" class="rounded-sm border border-emerald-200 bg-white px-3 py-3">
                                    @csrf
                                    <p class="text-sm leading-6 text-emerald-900">确认已经领取并检查线上交付内容后，可以完成该订单。</p>
                                    <button class="mt-3 rounded-sm border border-emerald-700 bg-emerald-700 px-3 py-2 text-xs font-medium text-white hover:bg-emerald-800" type="submit">
                                        确认收货并完成订单
                                    </button>
                                </form>
                            @endif
                            <p class="text-xs text-emerald-800">打开、复制或下载只会记录查看时间，请确认内容无误后点击确认收货完成订单。</p>
                        </div>
                    </div>
                @endif
            </section>

            <aside class="min-w-0 space-y-4">
                <div class="rounded-sm border border-slate-300">
                    <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">订单时间</h2>
                    <dl class="space-y-2 px-4 py-4 text-sm text-slate-700">
                        <div class="flex justify-between gap-3">
                            <dt>创建时间</dt>
                            <dd class="text-right">{{ $order->created_at?->format('Y-m-d H:i:s') ?? '-' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt>完成时间</dt>
                            <dd class="text-right">{{ $order->fulfilled_at?->format('Y-m-d H:i:s') ?? '未完成' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-sm border border-slate-300">
                    <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">金额</h2>
                    <dl class="space-y-2 px-4 py-4 text-sm">
                        <div class="flex justify-between"><dt>商品小计</dt><dd>@money($order->subtotal_cents)</dd></div>
                        <div class="flex justify-between"><dt>优惠</dt><dd>- @money($order->discount_cents)</dd></div>
                        @if((int) $order->wallet_recharge_cents > 0)
                            <div class="flex justify-between"><dt>钱包充值</dt><dd>@money((int) $order->wallet_recharge_cents)</dd></div>
                        @endif
                        <div class="flex justify-between border-t border-slate-200 pt-2"><dt>付款总金额</dt><dd>@money($order->paymentTotalCents())</dd></div>
                        <div class="flex justify-between text-emerald-700"><dt>钱包支付金额</dt><dd>@money($order->walletPaymentCents())</dd></div>
                        <div class="flex justify-between text-base font-semibold text-red-700"><dt>待支付金额</dt><dd>@money($order->remainingPaymentCents())</dd></div>
                    </dl>
                    @if($isPaymentPage)
                        <div class="border-t border-slate-200 px-4 py-4">
                            <form method="post" action="{{ route('orders.cancel', $order) }}">
                                @csrf
                                <button class="w-full rounded-sm border border-red-200 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50" type="submit">
                                    取消订单
                                </button>
                            </form>
                        </div>
                    @endif
                </div>

                @if($isPaymentPage)
                    <div class="rounded-sm border border-slate-300">
                        <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">付款凭证</h2>
                        <div class="px-4 py-4 text-sm">
                            @if(session('payment_success'))
                                <div data-payment-success data-payment-redirect-url="{{ route('forum.index') }}" class="mb-3 rounded-sm border border-blue-200 bg-blue-50 px-3 py-3 text-blue-900">
                                    <div data-payment-loading class="flex items-center gap-2">
                                        <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-blue-300 border-t-blue-700"></span>
                                        <span>付款信息识别中...</span>
                                    </div>
                                    <p data-payment-done class="hidden font-medium text-emerald-700">付款成功。</p>
                                </div>
                            @elseif($order->payment_proof_path || $order->payment_text_proof)
                                <p class="mb-3 text-emerald-700">付款成功。</p>
                            @endif
                            @if($pendingFlashSaleItem)
                                <p class="text-slate-600">请先选择规格后再上传付款凭证。</p>
                            @elseif(! in_array($order->payment_status, ['confirmed'], true))
                                <form method="post" action="{{ route('orders.payment-proof', $order) }}" enctype="multipart/form-data" class="space-y-3" data-payment-proof-form>
                                    @csrf
                                    @if($order->payment_method !== \App\Models\Order::PAYMENT_METHOD_RED_PACKET)
                                        <input class="block w-full max-w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" type="file" name="payment_proof" accept="image/*">
                                    @endif
                                    @if($hasPasswordRedPacket || $order->payment_method === \App\Models\Order::PAYMENT_METHOD_RED_PACKET)
                                        <textarea class="w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="payment_text_proof" rows="3" placeholder="{{ $order->payment_method === \App\Models\Order::PAYMENT_METHOD_RED_PACKET ? '请填写口令红包内容，后台会人工确认。' : '可填写支付宝口令红包、转账口令或其他文字付款凭证' }}">{{ old('payment_text_proof') }}</textarea>
                                    @endif
                                    <button class="rounded-sm border border-emerald-700 bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800" type="submit" data-payment-proof-submit>提交付款信息</button>
                                </form>
                            @else
                                <p class="text-emerald-700">付款已确认。</p>
                            @endif
                        </div>
                    </div>
                @endif

                @unless($isPaymentPage || $isWalletRecharge)
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
                        @if($order->status === \App\Models\Order::STATUS_AWAITING_RECEIPT)
                            <form method="post" action="{{ route('orders.confirm-receipt', $order) }}">
                                @csrf
                                <button class="mt-2 w-full rounded-sm border border-emerald-700 bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800" type="submit">确认收货</button>
                            </form>
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
                @endunless
            </aside>
        </div>
    </section>
    @if(session('payment_success'))
        <script>
            (() => {
                const root = document.querySelector('[data-payment-success]');
                const loading = root?.querySelector('[data-payment-loading]');
                const done = root?.querySelector('[data-payment-done]');
                const redirectUrl = root?.dataset.paymentRedirectUrl;

                if (!root || !loading || !done) {
                    return;
                }

                window.setTimeout(() => {
                    loading.classList.add('hidden');
                    done.classList.remove('hidden');
                    if (redirectUrl) {
                        window.setTimeout(() => {
                            window.location.href = redirectUrl;
                        }, 1200);
                    }
                }, 1000 + Math.floor(Math.random() * 2000));
            })();
        </script>
    @endif
    @if($isWalletRecharge && $order->payment_status === \App\Models\Order::PAYMENT_CONFIRMED)
        <div class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 px-4" data-wallet-recharge-success-modal>
            <div class="w-full max-w-md rounded-sm border border-emerald-200 bg-white p-5 shadow-xl">
                <h2 class="text-lg font-semibold text-emerald-800">充值成功</h2>
                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $walletRechargeSuccessMessage }}</p>
                <button class="mt-5 rounded-sm border border-emerald-700 bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800" type="button" data-wallet-recharge-success-close>我知道了</button>
            </div>
        </div>
    @endif
    <script>
        document.querySelector('[data-wallet-recharge-success-close]')?.addEventListener('click', () => {
            document.querySelector('[data-wallet-recharge-success-modal]')?.remove();
        });

        document.querySelectorAll('[data-payment-proof-form]').forEach((form) => {
            form.addEventListener('submit', () => {
                const button = form.querySelector('[data-payment-proof-submit]');

                if (! button) {
                    return;
                }

                button.disabled = true;
                button.classList.add('cursor-not-allowed', 'opacity-60');
            });
        });

        document.querySelectorAll('[data-payment-fallback-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const panel = button.parentElement?.querySelector('[data-payment-fallback-panel]');
                panel?.classList.toggle('hidden');
            });
        });

        document.querySelectorAll('[data-payment-countdown]').forEach((root) => {
            const target = root.querySelector('[data-payment-countdown-text]');
            const expiresAt = Date.parse(root.dataset.expiresAt || '');

            if (! target || Number.isNaN(expiresAt)) {
                return;
            }

            const format = (totalSeconds) => {
                const seconds = Math.max(0, totalSeconds);
                const minutes = Math.floor(seconds / 60);
                const rest = seconds % 60;

                return `${String(minutes).padStart(2, '0')}:${String(rest).padStart(2, '0')}`;
            };

            const tick = () => {
                const remaining = Math.ceil((expiresAt - Date.now()) / 1000);

                if (remaining <= 0) {
                    target.textContent = '已超时';
                    root.classList.remove('border-amber-300', 'bg-amber-50', 'text-amber-950');
                    root.classList.add('border-red-300', 'bg-red-50', 'text-red-950');
                    return;
                }

                target.textContent = format(remaining);
                window.setTimeout(tick, 1000);
            };

            tick();
        });

        document.querySelectorAll('[data-copy-delivery-form]').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                const button = form.querySelector('[data-copy-delivery-code]');
                const code = button?.dataset.copyDeliveryCode;

                if (! code || ! navigator.clipboard) {
                    return;
                }

                event.preventDefault();

                try {
                    await navigator.clipboard.writeText(code);
                } finally {
                    form.submit();
                }
            });
        });
    </script>
</x-layouts.app>
