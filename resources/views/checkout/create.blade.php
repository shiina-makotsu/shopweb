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
                                        <textarea class="min-h-20 w-full rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm leading-6" name="shipping_address" data-address-raw rows="2" required>{{ $defaultRawAddress }}</textarea>
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
                                        <input class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm" name="shipping_detail" data-address-detail value="{{ $defaultDetail }}">
                                    </label>
                                </div>
                            </div>
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

    @if($requiresShipping)
        <script type="application/json" id="china-region-tree">@json($regionTree, JSON_UNESCAPED_UNICODE)</script>
        <script>
            (() => {
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

                    const parseAddress = () => {
                        const text = compact(raw.value);
                        if (!text) return;

                        country.value = text.includes('中国') ? '中国' : (country.value || '中国');
                        setChinaMode();

                        const provinceName = Object.keys(tree).find((name) => text.includes(name));
                        if (!provinceName) {
                            detail.value = detail.value || raw.value.trim();
                            return;
                        }

                        province.value = provinceName;
                        fillCities();

                        const cities = tree[provinceName] || {};
                        const cityName = Object.keys(cities).find((name) => text.includes(name)) || (Object.keys(cities).length === 1 ? Object.keys(cities)[0] : '');
                        city.value = cityName;
                        fillDistricts();

                        const districtNames = Array.isArray(cities[cityName] || []) ? (cities[cityName] || []) : Object.keys(cities[cityName] || {});
                        const districtName = districtNames.find((name) => text.includes(name)) || '';
                        district.value = districtName;

                        let rest = text
                            .replace('中国', '')
                            .replace(provinceName, '')
                            .replace(cityName, '')
                            .replace(districtName, '');

                        street.value = street.value || '';
                        detail.value = rest || detail.value || raw.value.trim();
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
