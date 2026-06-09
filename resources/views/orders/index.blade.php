<x-layouts.app title="我的订单">
    @php($statusPresenter = app(\App\Support\OrderStatusPresenter::class))

    <section class="rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
            <h1 class="text-lg font-semibold">我的订单</h1>
        </div>

        @if($orders->isEmpty())
            <div class="px-4 py-10 text-sm text-slate-600">
                暂无订单。
                <a class="ml-2 text-blue-700 hover:text-blue-900" href="{{ route('products.index') }}">去选购商品</a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[680px] text-sm">
                    <thead class="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">订单</th>
                            <th class="px-4 py-3 font-medium">下单时间</th>
                            <th class="px-4 py-3 text-right font-medium">金额</th>
                            <th class="px-4 py-3 font-medium">订单状态</th>
                            <th class="px-4 py-3 font-medium">付款状态</th>
                            <th class="px-4 py-3 text-right font-medium">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($orders as $order)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $privacy->displayOrderNumber($order, auth()->user(), $settings) }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $order->created_at->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-3 text-right font-medium">@money($order->total_cents)</td>
                                <td class="px-4 py-3">{{ $statusPresenter->label($order->status) }}</td>
                                <td class="px-4 py-3">{{ $order->userPaymentLabel() }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('orders.show', $order) }}" class="rounded-sm border border-blue-700 bg-blue-700 px-3 py-2 text-xs font-medium text-white hover:bg-blue-800">查看</a>
                                        <a href="{{ route('orders.after-sales', $order) }}" class="rounded-sm border border-slate-300 px-3 py-2 text-xs font-medium hover:bg-slate-50">售后</a>
                                        <form method="post" action="{{ route('orders.contact-support', $order) }}">
                                            @csrf
                                            <button class="rounded-sm border border-slate-300 px-3 py-2 text-xs font-medium hover:bg-slate-50" type="submit">联系客服</button>
                                        </form>
                                        <form method="post" action="{{ route('orders.destroy', $order) }}" onsubmit="return confirm('删除后该订单会从你的订单列表中隐藏，后台仍可保留记录。确定删除吗？')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="rounded-sm border border-red-200 px-3 py-2 text-xs font-medium text-red-700 hover:bg-red-50" type="submit">删除订单</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $orders->links() }}</div>
        @endif
    </section>
</x-layouts.app>
