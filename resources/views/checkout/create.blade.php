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
                            @php
                                $defaultCountry = old('shipping_country', $defaultAddress?->country ?: '中国');
                                $defaultProvince = old('shipping_province', $shippingProvince);
                                $defaultCity = old('shipping_city', $defaultAddress?->city);
                                $defaultDistrict = old('shipping_district', $defaultAddress?->district);
                                $defaultStreet = old('shipping_street', $defaultAddress?->street);
                                $defaultDetail = old('shipping_detail', $defaultAddress?->detail);
                                $defaultRawAddress = old('shipping_address', $defaultAddress?->formatted());
                            @endphp
                            <div class="md:col-span-2 rounded-sm border border-slate-200 bg-slate-50 p-3" data-address-cascade>
                                <label class="block">
                                    <span class="text-sm font-medium">智能地址识别</span>
                                    <div class="mt-1 flex flex-col gap-2 sm:flex-row">
                                        <textarea class="min-h-20 w-full rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm leading-6" name="shipping_address" data-address-raw rows="2">{{ $defaultRawAddress }}</textarea>
                                        <button class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 sm:self-start" type="button" data-address-parse>识别</button>
                                    </div>
                                </label>
                                <div class="mt-4 grid gap-4 md:grid-cols-2">
                                    <label class="block">
                                        <span class="text-sm font-medium">国家/地区</span>
                                        <input class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm" name="shipping_country" data-address-country value="{{ $defaultCountry }}" required>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium">省份 / 地区</span>
                                        <select class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm" name="shipping_province" data-address-province required>
                                            <option value="">请选择省份</option>
                                            @foreach($provinceOptions as $provinceValue => $provinceLabel)
                                                <option value="{{ $provinceValue }}" @selected($defaultProvince === $provinceValue)>{{ $provinceLabel }}</option>
                                            @endforeach
                                        </select>
                                        <input class="mt-1 hidden w-full rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm" data-address-province-free value="{{ $defaultProvince }}">
                                        <span class="mt-1 block text-xs text-slate-500">邮费按省份匹配；香港、澳门、台湾也作为地区选项参与匹配。</span>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium">市</span>
                                        <select class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm" name="shipping_city" data-address-city data-current="{{ $defaultCity }}" required></select>
                                        <input class="mt-1 hidden w-full rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm" data-address-city-free value="{{ $defaultCity }}">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium">区 / 县</span>
                                        <select class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm" name="shipping_district" data-address-district data-current="{{ $defaultDistrict }}"></select>
                                        <input class="mt-1 hidden w-full rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm" data-address-district-free value="{{ $defaultDistrict }}">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium">街道</span>
                                        <select class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm" name="shipping_street" data-address-street data-current="{{ $defaultStreet }}"></select>
                                        <input class="mt-1 hidden w-full rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm" data-address-street-free value="{{ $defaultStreet }}">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium">详细地址</span>
                                        <input class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm" name="shipping_detail" data-address-detail value="{{ $defaultDetail }}" required>
                                    </label>
                                </div>
                            </div>
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

                @if($requiresShipping && ! empty($shippingQuote['shipments']))
                    <section class="rounded-sm border border-slate-300">
                        <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">物流方式</h2>
                        <div class="space-y-3 p-4">
                            @foreach($shippingQuote['shipments'] as $shipment)
                                @php
                                    $warehouseId = (int) ($shipment['warehouse_id'] ?? 0);
                                    $selectedCarrier = old('shipping_carriers.'.$warehouseId, $shipment['shipping_carrier_id'] ?? null);
                                    $extraFee = (int) ($shipment['extra_fee_cents'] ?? 0);
                                    $options = $shipment['available_carriers'] ?? [];
                                @endphp
                                <div class="rounded-sm border border-slate-200 bg-white px-3 py-3" data-shipping-shipment data-extra-fee="{{ $extraFee }}" data-initial-fee="{{ (int) $shipment['fee_cents'] }}">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="text-sm font-medium">包裹 {{ $loop->iteration }}</span>
                                        <span class="text-sm font-semibold" data-shipment-fee>@money($shipment['fee_cents'])</span>
                                    </div>
                                    @if(count($options) > 1)
                                        <label class="mt-3 block">
                                            <span class="text-xs font-medium text-slate-600">选择物流</span>
                                            <select class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm" name="shipping_carriers[{{ $warehouseId }}]" data-shipping-carrier-select>
                                                @foreach($options as $option)
                                                    @php($optionFee = (int) $option['fee_cents'] + $extraFee)
                                                    <option value="{{ $option['shipping_carrier_id'] }}" data-base-fee="{{ (int) $option['fee_cents'] }}" @selected((string) $selectedCarrier === (string) $option['shipping_carrier_id'])>
                                                        {{ $option['shipping_carrier_name'] }} / @money($optionFee)
                                                    </option>
                                                @endforeach
                                            </select>
                                        </label>
                                    @elseif(count($options) === 1)
                                        @php($option = $options[0])
                                        <input type="hidden" name="shipping_carriers[{{ $warehouseId }}]" value="{{ $option['shipping_carrier_id'] }}">
                                        <p class="mt-2 text-sm text-slate-600">已选择：{{ $option['shipping_carrier_name'] }} / @money(((int) $option['fee_cents']) + $extraFee)</p>
                                    @else
                                        <p class="mt-2 text-sm text-amber-700">当前地址暂无可用物流模板，系统将按 0 邮费提交。</p>
                                    @endif
                                    <p class="mt-2 text-xs text-slate-500">
                                        @foreach($shipment['items'] as $shipmentItem)
                                            <span>{{ $shipmentItem['title'] }} x {{ $shipmentItem['quantity'] }}</span>@if(! $loop->last)<span>；</span>@endif
                                        @endforeach
                                    </p>
                                </div>
                            @endforeach
                            @if($shippingQuote['notice'])
                                <div class="rounded-sm border border-amber-300 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-900">
                                    {{ $shippingQuote['notice'] }}
                                </div>
                            @endif
                        </div>
                    </section>
                @endif

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

                <section class="rounded-sm border border-slate-300">
                    <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">支付方式</h2>
                    <div class="grid gap-3 p-4 md:grid-cols-3">
                        @php($selectedPaymentMethod = old('payment_method', \App\Models\Order::PAYMENT_METHOD_QR_CODE))
                        @php($paypalEmail = ($siteSettings ?? null)?->paypalEmail())
                        @php($checkoutPayableCents = (int) $subtotalCents + (int) $shippingQuote['shipping_fee_cents'])
                        @php($walletCanCoverCheckout = (int) auth()->user()->wallet_balance_cents >= $checkoutPayableCents && $checkoutPayableCents > 0)
                        @if($walletCanCoverCheckout)
                            <label class="flex cursor-pointer gap-3 rounded-sm border border-emerald-300 bg-emerald-50 px-3 py-3 text-sm hover:bg-emerald-100">
                                <input class="mt-1" type="radio" name="payment_method" value="{{ \App\Models\Order::PAYMENT_METHOD_WALLET }}" @checked($selectedPaymentMethod === \App\Models\Order::PAYMENT_METHOD_WALLET)>
                                <span>
                                    <span class="block font-medium">钱包余额支付</span>
                                    <span class="mt-1 block text-xs leading-5 text-emerald-900">当前余额 @money((int) auth()->user()->wallet_balance_cents)，可直接支付本单。</span>
                                </span>
                            </label>
                        @endif
                        <label class="flex cursor-pointer gap-3 rounded-sm border border-slate-300 bg-white px-3 py-3 text-sm hover:bg-slate-50">
                            <input class="mt-1" type="radio" name="payment_method" value="{{ \App\Models\Order::PAYMENT_METHOD_QR_CODE }}" @checked($selectedPaymentMethod === \App\Models\Order::PAYMENT_METHOD_QR_CODE)>
                            <span>
                                <span class="block font-medium">二维码支付</span>
                                <span class="mt-1 block text-xs leading-5 text-slate-600">提交订单后扫码付款，再上传付款凭证。</span>
                            </span>
                        </label>
                        <label class="flex cursor-pointer gap-3 rounded-sm border border-slate-300 bg-white px-3 py-3 text-sm hover:bg-slate-50">
                            <input class="mt-1" type="radio" name="payment_method" value="{{ \App\Models\Order::PAYMENT_METHOD_RED_PACKET }}" @checked($selectedPaymentMethod === \App\Models\Order::PAYMENT_METHOD_RED_PACKET)>
                            <span>
                                <span class="block font-medium">口令红包支付</span>
                                <span class="mt-1 block text-xs leading-5 text-slate-600">提交口令红包内容，由后台人工确认收款。</span>
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
                            @if($walletCanCoverCheckout)
                                钱包余额 @money((int) auth()->user()->wallet_balance_cents)。选择“钱包余额支付”会直接扣除余额并完成付款；选择其他付款方式不会使用钱包余额。
                            @else
                                钱包余额 @money((int) auth()->user()->wallet_balance_cents) 会先抵扣，剩余金额使用所选付款方式补齐，并由后台人工确认。
                            @endif
                        </div>
                    @endif
                    @error('payment_method')
                        <p class="px-4 pb-4 text-sm text-red-600">{{ $message }}</p>
                    @enderror
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
                @php($checkoutTotalCents = (int) $subtotalCents + (int) $shippingQuote['shipping_fee_cents'])
                @php($checkoutWalletBalanceCents = (int) auth()->user()->wallet_balance_cents)
                @php($checkoutWalletCents = $selectedPaymentMethod === \App\Models\Order::PAYMENT_METHOD_WALLET || ($checkoutWalletBalanceCents > 0 && $checkoutWalletBalanceCents < $checkoutTotalCents) ? min($checkoutWalletBalanceCents, $checkoutTotalCents) : 0)
                <div class="border-t border-slate-200 bg-slate-50 px-4 py-4" data-checkout-summary data-subtotal-cents="{{ (int) $subtotalCents }}" data-wallet-balance-cents="{{ (int) auth()->user()->wallet_balance_cents }}">
                    <div class="flex justify-between text-sm">
                        <span>商品金额</span>
                        <span class="font-semibold">@money($subtotalCents)</span>
                    </div>
                    @if($requiresShipping)
                        <div class="mt-2 flex justify-between text-sm">
                            <span>物流金额{{ $shippingProvince ? '（'.$shippingProvince.'）' : '' }}</span>
                            <span class="font-semibold" data-shipping-total>@money($shippingQuote['shipping_fee_cents'])</span>
                        </div>
                        @if(! empty($shippingQuote['shipments']))
                            <div class="mt-3 space-y-2 text-xs text-slate-600">
                                @foreach($shippingQuote['shipments'] as $shipment)
                                    <div class="rounded-sm border border-slate-200 bg-white px-3 py-2">
                                        <div class="flex justify-between gap-3 font-medium text-slate-800">
                                            <span>包裹 {{ $loop->iteration }}{{ $shipment['shipping_carrier_name'] ? ' / '.$shipment['shipping_carrier_name'] : '' }}</span>
                                            <span>@money($shipment['fee_cents'])</span>
                                        </div>
                                        <p class="mt-1">
                                            @foreach($shipment['items'] as $shipmentItem)
                                                <span>{{ $shipmentItem['title'] }} x {{ $shipmentItem['quantity'] }}</span>@if(! $loop->last)<span>；</span>@endif
                                            @endforeach
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @endif
                    <div class="mt-2 flex justify-between border-t border-slate-200 pt-3 text-sm">
                        <span>付款总金额</span>
                        <span class="font-semibold" data-order-total>@money($checkoutTotalCents)</span>
                    </div>
                    <div class="mt-2 flex justify-between text-sm text-emerald-700">
                        <span>钱包支付金额</span>
                        <span class="font-semibold" data-wallet-payment>@money($checkoutWalletCents)</span>
                    </div>
                    <div class="mt-2 flex justify-between text-base font-semibold text-red-700">
                        <span>待支付金额</span>
                        <span data-remaining-payment>@money(max(0, $checkoutTotalCents - $checkoutWalletCents))</span>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    @if($requiresShipping)
        <script type="application/json" id="china-region-tree">@json($regionTree, JSON_UNESCAPED_UNICODE)</script>
        <script>
            (() => {
                const money = (cents) => new Intl.NumberFormat('zh-CN', {
                    style: 'currency',
                    currency: 'CNY',
                }).format(Math.max(0, Number(cents || 0)) / 100);
                const summary = document.querySelector('[data-checkout-summary]');
                const shippingTotalNode = document.querySelector('[data-shipping-total]');
                const orderTotalNode = document.querySelector('[data-order-total]');
                const walletPaymentNode = document.querySelector('[data-wallet-payment]');
                const remainingPaymentNode = document.querySelector('[data-remaining-payment]');
                const updateShippingTotals = () => {
                    let shippingTotal = 0;

                    document.querySelectorAll('[data-shipping-shipment]').forEach((shipment) => {
                        const extraFee = Number(shipment.dataset.extraFee || 0);
                        const select = shipment.querySelector('[data-shipping-carrier-select]');
                        const selectedOption = select?.selectedOptions?.[0];
                        const baseFee = Number(selectedOption?.dataset.baseFee || 0);
                        const fee = baseFee + extraFee;
                        const feeNode = shipment.querySelector('[data-shipment-fee]');

                        if (feeNode && select) {
                            feeNode.textContent = money(fee);
                        }

                        shippingTotal += select ? fee : Number(shipment.dataset.initialFee || 0);
                    });

                    if (! summary || ! shippingTotalNode || ! orderTotalNode) {
                        return;
                    }

                    const subtotal = Number(summary.dataset.subtotalCents || 0);
                    const walletBalance = Number(summary.dataset.walletBalanceCents || 0);
                    const orderTotal = subtotal + shippingTotal;
                    const selectedPayment = document.querySelector('[name="payment_method"]:checked')?.value || '';
                    const shouldUseWallet = selectedPayment === '{{ \App\Models\Order::PAYMENT_METHOD_WALLET }}' || (walletBalance > 0 && walletBalance < orderTotal);
                    const walletPayment = shouldUseWallet ? Math.min(walletBalance, orderTotal) : 0;
                    shippingTotalNode.textContent = money(shippingTotal);
                    orderTotalNode.textContent = money(orderTotal);
                    if (walletPaymentNode) walletPaymentNode.textContent = money(walletPayment);
                    if (remainingPaymentNode) remainingPaymentNode.textContent = money(orderTotal - walletPayment);
                };

                document.querySelectorAll('[data-shipping-carrier-select]').forEach((select) => {
                    select.addEventListener('change', updateShippingTotals);
                });
                document.querySelectorAll('[name="payment_method"]').forEach((input) => {
                    input.addEventListener('change', updateShippingTotals);
                });
                updateShippingTotals();

                const roots = document.querySelectorAll('[data-address-cascade]');
                const dataNode = document.getElementById('china-region-tree');
                const tree = dataNode ? JSON.parse(dataNode.textContent || '{}') : {};

                const addOption = (select, value, label = value) => {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = label;
                    select.appendChild(option);
                };

                const compact = (value) => String(value || '').replace(/\s+/g, '').trim();
                const isChina = (value) => ['中国', '中华人民共和国', 'China', 'CN', 'PRC'].includes(String(value || '').trim());

                roots.forEach((root) => {
                    const country = root.querySelector('[data-address-country]');
                    const province = root.querySelector('[data-address-province]');
                    const city = root.querySelector('[data-address-city]');
                    const district = root.querySelector('[data-address-district]');
                    const street = root.querySelector('[data-address-street]');
                    const provinceFree = root.querySelector('[data-address-province-free]');
                    const cityFree = root.querySelector('[data-address-city-free]');
                    const districtFree = root.querySelector('[data-address-district-free]');
                    const streetFree = root.querySelector('[data-address-street-free]');
                    const detail = root.querySelector('[data-address-detail]');
                    const raw = root.querySelector('[data-address-raw]');
                    const parseButton = root.querySelector('[data-address-parse]');
                    const contactName = document.querySelector('[name="contact_name"]');
                    const contactPhone = document.querySelector('[name="contact_phone"]');
                    const pairs = [
                        [province, provinceFree],
                        [city, cityFree],
                        [district, districtFree],
                        [street, streetFree],
                    ];

                    const syncFreeInputs = () => {
                        pairs.forEach(([select, input]) => {
                            if (!select || !input || !input.classList.contains('hidden')) return;
                            input.value = select.value;
                        });
                    };

                    const syncSelectInputs = () => {
                        pairs.forEach(([select, input]) => {
                            if (!select || !input || input.classList.contains('hidden')) return;
                            select.value = input.value;
                        });
                    };

                    const setChinaMode = () => {
                        const enabled = isChina(country?.value);
                        pairs.forEach(([select, input]) => {
                            if (!select || !input) return;
                            select.dataset.wasRequired = select.dataset.wasRequired || (select.required ? '1' : '0');
                            const shouldRequire = select.dataset.wasRequired === '1';

                            if (enabled) {
                                select.name = input.name || select.name;
                                input.name = '';
                                select.disabled = false;
                                input.disabled = true;
                                select.required = shouldRequire;
                                input.required = false;
                                select.classList.remove('hidden');
                                input.classList.add('hidden');
                            } else {
                                input.name = select.name || input.name;
                                select.name = '';
                                select.disabled = true;
                                input.disabled = false;
                                select.required = false;
                                input.required = shouldRequire;
                                input.classList.remove('hidden');
                                select.classList.add('hidden');
                                input.value = input.value || select.value;
                            }
                        });

                        if (enabled) {
                            fillCities();
                        } else {
                            syncFreeInputs();
                        }
                    };

                    const fillCities = () => {
                        if (!isChina(country?.value)) return;
                        const current = city.dataset.current || city.value;
                        city.innerHTML = '';
                        addOption(city, '', '请选择城市');
                        Object.keys(tree[province.value] || {}).forEach((name) => addOption(city, name));
                        city.value = current && tree[province.value]?.[current] ? current : '';
                        city.dataset.current = '';
                        fillDistricts();
                    };

                    const fillDistricts = () => {
                        if (!isChina(country?.value)) return;
                        const current = district.dataset.current || district.value;
                        district.innerHTML = '';
                        addOption(district, '', '可不选择');
                        const districts = tree[province.value]?.[city.value] || [];
                        const districtNames = Array.isArray(districts) ? districts : Object.keys(districts);
                        districtNames.forEach((name) => addOption(district, name));
                        district.value = current && districtNames.includes(current) ? current : '';
                        district.dataset.current = '';
                        fillStreets();
                    };

                    const fillStreets = () => {
                        if (!isChina(country?.value)) return;
                        const current = street.dataset.current || street.value;
                        street.innerHTML = '';
                        addOption(street, '', '可不选择');
                        const districtNode = tree[province.value]?.[city.value]?.[district.value];
                        const streets = Array.isArray(districtNode) ? districtNode : Object.keys(districtNode || {});
                        streets.forEach((name) => addOption(street, name));
                        street.value = current && streets.includes(current) ? current : '';
                        street.dataset.current = '';
                    };

                    const withoutSuffix = (value) => String(value || '').replace(/(壮族自治区|回族自治区|维吾尔自治区|特别行政区|自治州|自治区|地区|盟|省|市|区|县|旗|街道|镇|乡)$/u, '');
                    const findMatch = (text, candidates) => {
                        const matches = [];
                        candidates.forEach((candidate) => {
                            [candidate, withoutSuffix(candidate)].filter(Boolean).forEach((alias) => {
                                const position = text.indexOf(alias);
                                if (position >= 0) matches.push({ alias, candidate, position, length: alias.length });
                            });
                        });
                        matches.sort((a, b) => a.position - b.position || b.length - a.length);

                        return matches[0] || null;
                    };
                    const removeFirst = (text, value) => {
                        if (!value) return text;
                        const values = [value, withoutSuffix(value)].filter(Boolean);
                        for (const item of values) {
                            const position = text.indexOf(item);
                            if (position >= 0) {
                                return text.slice(0, position) + text.slice(position + item.length);
                            }
                        }

                        return text;
                    };
                    const extractRoad = (text) => {
                        const match = text.match(/([\u4e00-\u9fa5A-Za-z0-9]+?(?:大道|大街|街道|街|路|巷|弄|里|村|镇|乡|屯|庄|道))/u);

                        return match ? match[1] : '';
                    };

                    const parseAddress = () => {
                        let original = String(raw.value || '').trim();
                        const phoneMatch = original.match(/(?<!\d)(\+?\d[\d\s-]{6,18}\d)(?!\d)/u);
                        if (phoneMatch) {
                            contactPhone.value = contactPhone.value || ((phoneMatch[0].trim().startsWith('+') ? '+' : '') + phoneMatch[0].replace(/\D+/g, ''));
                            original = original.replace(phoneMatch[0], '').trim();
                        }
                        const nameMatch = original.match(/^([\u4e00-\u9fa5A-Za-z][\u4e00-\u9fa5A-Za-z·.\s]{0,30}?)\s*(?=(中国|中华人民共和国|北京|天津|上海|重庆|河北|山西|辽宁|吉林|黑龙江|江苏|浙江|安徽|福建|江西|山东|河南|湖北|湖南|广东|广西|海南|四川|贵州|云南|西藏|陕西|甘肃|青海|宁夏|新疆|香港|澳门|台湾))/u);
                        if (nameMatch) {
                            contactName.value = contactName.value || nameMatch[1].trim();
                            original = original.slice(nameMatch[1].length).trim();
                        }
                        const text = compact(original);
                        if (!text) return;

                        country.value = text.includes('中国') ? '中国' : (country.value || '中国');
                        setChinaMode();

                        let remaining = text.replace(/^中华人民共和国|^中国/, '');
                        const provinceMatch = findMatch(remaining, Object.keys(tree));
                        const provinceName = provinceMatch?.candidate || '';
                        if (!provinceName) {
                            detail.value = detail.value || original;
                            return;
                        }

                        province.value = provinceName;
                        remaining = removeFirst(remaining, provinceMatch.alias);
                        fillCities();

                        const cities = tree[provinceName] || {};
                        const cityMatch = findMatch(remaining, Object.keys(cities));
                        const cityName = cityMatch?.candidate || (Object.keys(cities).length === 1 ? Object.keys(cities)[0] : '');
                        city.value = cityName;
                        remaining = removeFirst(remaining, cityMatch?.alias || cityName);
                        fillDistricts();

                        const districtNames = Array.isArray(cities[cityName] || []) ? (cities[cityName] || []) : Object.keys(cities[cityName] || {});
                        const districtMatch = findMatch(remaining, districtNames);
                        const districtName = districtMatch?.candidate || '';
                        district.value = districtName;
                        remaining = removeFirst(remaining, districtMatch?.alias || districtName);
                        fillStreets();

                        const streetNode = tree[province.value]?.[city.value]?.[district.value] || [];
                        const streetNames = Array.isArray(streetNode) ? streetNode : Object.keys(streetNode);
                        const roadName = extractRoad(remaining);
                        const streetMatch = roadName ? null : findMatch(remaining, streetNames);
                        const streetName = roadName || streetMatch?.candidate || '';

                        if (streetName && street) {
                            street.value = streetName;
                            remaining = removeFirst(remaining, streetMatch?.alias || streetName);
                        }

                        detail.value = remaining.trim();
                    };

                    province?.addEventListener('change', fillCities);
                    city?.addEventListener('change', fillDistricts);
                    district?.addEventListener('change', fillStreets);
                    country?.addEventListener('input', setChinaMode);
                    country?.addEventListener('change', setChinaMode);
                    [provinceFree, cityFree, districtFree, streetFree].forEach((input) => input?.addEventListener('input', syncSelectInputs));
                    parseButton?.addEventListener('click', parseAddress);
                    setChinaMode();
                });
            })();
        </script>
    @endif
</x-layouts.app>
