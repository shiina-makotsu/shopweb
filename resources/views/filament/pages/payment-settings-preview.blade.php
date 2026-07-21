@php
    $paymentQrUrl = $preview['payment_qr_url'] ?? null;
    $fallbackQrUrl = $preview['fallback_qr_url'] ?? null;
    $friendQrUrl = $preview['friend_qr_url'] ?? null;
    $paypalEmail = $preview['paypal_email'] ?? '';
    $hasRedPacket = (bool) ($preview['red_packet_enabled'] ?? false);
    $redPacketNote = $preview['red_packet_note'] ?: '请在下方填写口令红包内容，后台会人工确认收款。';
    $instructions = $preview['instructions'] ?: '请按页面显示的付款备注单号完成转账，并上传付款截图。';
@endphp

<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <div class="space-y-3">
            <div class="rounded-xl border border-sky-100 bg-sky-50/70 px-4 py-3 text-sm text-sky-950">
                <div class="flex items-center justify-between gap-3">
                    <span class="font-semibold">示例订单 SW2026070712340001</span>
                    <span class="rounded-full bg-white px-3 py-1 text-xs font-medium text-sky-800">待支付 ¥128.00</span>
                </div>
                <p class="mt-2 text-xs text-sky-800">用户付款页会先展示主付款区；未设置主二维码时，PayPal 或口令红包会自动上移到主付款位置。</p>
            </div>

            @if($paymentQrUrl)
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-sm font-semibold text-slate-900">付款二维码</p>
                    <img class="mt-3 h-40 w-40 rounded-xl border border-slate-200 bg-white object-contain p-2" src="{{ $paymentQrUrl }}" alt="付款二维码预览">
                </div>
            @elseif($paypalEmail)
                <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sky-950">
                    <p class="text-sm font-semibold">PayPal 收款邮箱</p>
                    <p class="mt-2 break-all text-sm">{{ $paypalEmail }}</p>
                    <p class="mt-2 text-xs">完成 PayPal 付款后，用户会在下方上传付款凭证或填写付款说明。</p>
                </div>
            @elseif($hasRedPacket)
                <div class="rounded-xl border border-pink-200 bg-pink-50 px-4 py-3 text-pink-950">
                    <p class="text-sm font-semibold">口令红包付款</p>
                    <p class="mt-2 whitespace-pre-line text-sm">{{ $redPacketNote }}</p>
                </div>
            @else
                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-600">
                    尚未设置主付款二维码、PayPal 收款邮箱或口令红包，用户付款页会主要显示付款说明和联系客服入口。
                </div>
            @endif

            <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-950">
                <p class="font-semibold">付款备注单号：SW2026070712340001</p>
                @if($preview['account_name'] ?? '')
                    <p class="mt-1">收款账户：{{ $preview['account_name'] }}</p>
                @endif
                @if($preview['account_note'] ?? '')
                    <p class="mt-1 whitespace-pre-line">{{ $preview['account_note'] }}</p>
                @endif
            </div>

            <div class="rounded-xl border border-slate-200 px-4 py-3 text-sm leading-6 text-slate-700">
                <p class="mb-2 font-semibold text-slate-900">付款说明</p>
                <p class="whitespace-pre-line">{{ \Illuminate\Support\Str::limit(strip_tags($instructions), 260) }}</p>
            </div>
        </div>

        <aside class="space-y-3">
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-sm font-semibold text-slate-900">付款凭证上传入口</p>
                <div class="mt-3 rounded-xl border border-dashed border-slate-300 bg-white px-4 py-6 text-center text-xs text-slate-500">
                    上传截图或填写付款说明
                </div>
                <button type="button" class="mt-3 w-full rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white opacity-80">提交付款凭证</button>
            </div>

            @if($paymentQrUrl && $paypalEmail)
                <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950">
                    <p class="font-semibold">备用 PayPal</p>
                    <p class="mt-1 break-all">{{ $paypalEmail }}</p>
                </div>
            @endif

            @if($paymentQrUrl && $hasRedPacket)
                <div class="rounded-xl border border-pink-200 bg-pink-50 px-4 py-3 text-sm text-pink-950">
                    <p class="font-semibold">备用口令红包</p>
                    <p class="mt-1 whitespace-pre-line">{{ $redPacketNote }}</p>
                </div>
            @endif

            @if($fallbackQrUrl || $friendQrUrl || ($preview['support_enabled'] ?? true))
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                    <p class="font-semibold">支付失败兜底区</p>
                    <div class="mt-3 flex flex-wrap gap-3">
                        @if($fallbackQrUrl)
                            <img class="h-20 w-20 rounded-lg border border-amber-200 bg-white object-contain p-1" src="{{ $fallbackQrUrl }}" alt="备用付款码预览">
                        @endif
                        @if($friendQrUrl)
                            <img class="h-20 w-20 rounded-lg border border-amber-200 bg-white object-contain p-1" src="{{ $friendQrUrl }}" alt="好友码预览">
                        @endif
                    </div>
                    @if($preview['support_enabled'] ?? true)
                        <p class="mt-3 text-xs">显示联系客服入口</p>
                        <x-support.contact-methods compact class="mt-2" />
                    @endif
                </div>
            @endif
        </aside>
    </div>
</div>
