<x-layouts.app title="物流查询">
    <section class="rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
            <h1 class="text-lg font-semibold">物流查询</h1>
            <p class="mt-1 text-xs text-slate-600">输入自己的订单号查看物流状态。系统只会在你的购买记录中查询。</p>
        </div>

        <div class="grid gap-4 p-4 lg:grid-cols-[360px_1fr]">
            <form method="get" action="{{ route('shipments.show') }}" class="space-y-3 rounded-sm border border-slate-200 bg-slate-50 p-4">
                <label class="block text-sm">
                    <span class="font-medium">订单号</span>
                    <input class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-2" name="order_number" value="{{ request('order_number') }}" required>
                </label>
                <button class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800" type="submit">查询物流</button>
            </form>

            <div class="rounded-sm border border-slate-200 bg-white p-4">
                @if($order)
                    <div class="space-y-3 text-sm">
                        <div>
                            <p class="text-slate-500">订单号</p>
                            <p class="font-semibold">{{ $privacy->displayOrderNumber($order, $order->user, $settings) }}</p>
                        </div>
                        <div>
                            <p class="text-slate-500">订单状态</p>
                            <p class="font-semibold">{{ app(\App\Support\OrderStatusPresenter::class)->label($order->status) }}</p>
                        </div>
                        <div>
                            <p class="text-slate-500">物流承运商</p>
                            <p class="font-semibold">{{ $order->shippingCarrier?->name ?? '暂无物流信息' }}</p>
                        </div>
                        <div>
                            <p class="text-slate-500">物流单号</p>
                            <p class="font-semibold">{{ $privacy->displayTrackingNumber($order, $order->user, $settings) }}</p>
                        </div>
                        @if($order->tracking_url && $privacy->canViewTrackingNumberForOrder($order, $order->user, $settings))
                            <a class="inline-flex rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 font-medium text-white hover:bg-blue-800" href="{{ $order->tracking_url }}" target="_blank" rel="noopener">打开承运商查询</a>
                        @endif
                    </div>
                @elseif($searched)
                    <p class="text-sm text-slate-600">未在你的购买记录中找到匹配订单，或该订单还没有物流信息。</p>
                @else
                    <p class="text-sm text-slate-600">提交查询后会显示承运商、物流单号和外部查询链接。</p>
                @endif
            </div>
        </div>
    </section>
</x-layouts.app>
