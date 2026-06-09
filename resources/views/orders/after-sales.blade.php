<x-layouts.app title="售后需求">
    @php($statusPresenter = app(\App\Support\OrderStatusPresenter::class))

    <section class="grid gap-4 lg:grid-cols-[1fr_360px]">
        <div class="space-y-4">
            <div class="rounded-sm border border-slate-300 bg-white">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 bg-slate-100 px-4 py-3">
                    <div>
                        <h1 class="text-lg font-semibold">售后需求</h1>
                        <p class="mt-1 text-xs text-slate-600">
                            订单：{{ $privacy->displayOrderNumber($order, auth()->user(), $settings) }}
                            / {{ $statusPresenter->label($order->status) }}
                        </p>
                    </div>
                    <form method="post" action="{{ route('orders.contact-support', $order) }}">
                        @csrf
                        <button class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800" type="submit">
                            直接联系客服
                        </button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] text-sm">
                        <thead class="bg-slate-50 text-left text-slate-600">
                            <tr>
                                <th class="px-4 py-3 font-medium">商品</th>
                                <th class="px-4 py-3 font-medium">SKU</th>
                                <th class="px-4 py-3 text-center font-medium">数量</th>
                                <th class="px-4 py-3 text-right font-medium">小计</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($order->items as $item)
                                <tr>
                                    <td class="px-4 py-3 font-medium">{{ $item->product_title }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $item->variant_sku }}</td>
                                    <td class="px-4 py-3 text-center">{{ $item->quantity }}</td>
                                    <td class="px-4 py-3 text-right">@money($item->line_total_cents)</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-sm border border-slate-300 bg-white">
                <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
                    <h2 class="text-base font-semibold">已提交需求</h2>
                </div>
                @if($order->afterSalesRequests->isEmpty())
                    <div class="px-4 py-8 text-sm text-slate-600">暂无售后需求。</div>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach($order->afterSalesRequests as $requestItem)
                            <article class="px-4 py-4 text-sm">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-semibold">{{ $requestItem->subject }}</h3>
                                        <p class="mt-1 text-xs text-slate-500">{{ $requestItem->created_at->format('Y-m-d H:i') }}</p>
                                    </div>
                                    <span class="rounded-sm border border-blue-200 bg-blue-50 px-2 py-1 text-xs text-blue-700">
                                        {{ match($requestItem->status) {
                                            'contacting' => '联系中',
                                            'resolved' => '已处理',
                                            'closed' => '已关闭',
                                            default => '待处理',
                                        } }}
                                    </span>
                                </div>
                                <p class="mt-3 whitespace-pre-line text-slate-700">{{ $requestItem->message }}</p>
                                @if($requestItem->admin_note)
                                    <div class="mt-3 rounded-sm border border-emerald-200 bg-emerald-50 px-3 py-3 text-emerald-900">
                                        <p class="font-medium">处理留言</p>
                                        <p class="mt-1 whitespace-pre-line">{{ $requestItem->admin_note }}</p>
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <aside class="rounded-sm border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
                <h2 class="text-base font-semibold">提交售后需求</h2>
            </div>
            <form method="post" action="{{ route('orders.after-sales.store', $order) }}" class="space-y-3 px-4 py-4 text-sm">
                @csrf
                <label class="block">
                    <span class="font-medium">类型</span>
                    <select class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-2" name="type" required>
                        <option value="refund">退款</option>
                        <option value="repair">补发/维修</option>
                        <option value="shipping">物流问题</option>
                        <option value="other">其他</option>
                    </select>
                </label>
                <label class="block">
                    <span class="font-medium">主题</span>
                    <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2" name="subject" value="{{ old('subject') }}" maxlength="120" required>
                </label>
                <label class="block">
                    <span class="font-medium">说明</span>
                    <textarea class="mt-1 min-h-36 w-full rounded-sm border border-slate-300 px-3 py-2" name="message" maxlength="3000" required>{{ old('message') }}</textarea>
                </label>
                <button class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 font-medium text-white hover:bg-blue-800" type="submit">提交需求</button>
            </form>
        </aside>
    </section>
</x-layouts.app>
