<x-layouts.app title="售后/客服需求">
    <section class="grid gap-4 lg:grid-cols-[1fr_360px]">
        <div class="rounded-sm border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
                <h1 class="text-lg font-semibold">售后/客服需求</h1>
                @if($guestId ?? null)
                    <p class="mt-1 text-xs text-slate-600">游客 ID：{{ $guestId }}。请保存该浏览器会话以查看客服回复。</p>
                @endif
            </div>

            @if($tickets->isEmpty())
                <div class="px-4 py-10 text-sm text-slate-600">暂无需求记录。</div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach($tickets as $ticket)
                        <article class="px-4 py-4 text-sm">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 class="font-semibold">{{ $ticket->subject }}</h2>
                                    <p class="mt-1 text-xs text-slate-500">{{ $ticket->created_at->format('Y-m-d H:i') }} / {{ match($ticket->category) {
                                        'complaint' => '投诉反馈',
                                        'after_sale' => '退款售后',
                                        'consultation' => '商品咨询',
                                        default => '其他问题',
                                    } }}</p>
                                    @if($ticket->order)
                                        <p class="mt-1 text-xs text-slate-500">关联订单：{{ $ticket->order->order_number }}</p>
                                    @endif
                                </div>
                                <span class="rounded-sm border border-blue-200 bg-blue-50 px-2 py-1 text-xs text-blue-700">{{ match($ticket->status) {
                                    'replied' => '已回复',
                                    'closed' => '已关闭',
                                    default => '待处理',
                                } }}</span>
                            </div>
                            <p class="mt-3 whitespace-pre-line text-slate-700">{{ $ticket->message }}</p>
                            @if($ticket->admin_reply)
                                <div class="mt-3 rounded-sm border border-emerald-200 bg-emerald-50 px-3 py-3 text-emerald-900">
                                    <p class="font-medium">客服回复</p>
                                    <p class="mt-1 whitespace-pre-line">{{ $ticket->admin_reply }}</p>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
                <div class="border-t border-slate-200 px-4 py-3">{{ $tickets->links() }}</div>
            @endif
        </div>

        <aside class="rounded-sm border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
                <h2 class="text-base font-semibold">提交需求</h2>
            </div>
            <form method="post" action="{{ route('support.store') }}" class="space-y-3 px-4 py-4 text-sm">
                @csrf
                <label class="block">
                    <span class="font-medium">分类</span>
                    <select class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-2" name="category" required>
                        <option value="consultation" @selected(old('category', $selectedOrder ? 'after_sale' : 'consultation') === 'consultation')>商品咨询</option>
                        <option value="complaint" @selected(old('category', $selectedOrder ? 'after_sale' : 'consultation') === 'complaint')>投诉反馈</option>
                        <option value="after_sale" @selected(old('category', $selectedOrder ? 'after_sale' : 'consultation') === 'after_sale')>退款售后</option>
                        <option value="other" @selected(old('category', $selectedOrder ? 'after_sale' : 'consultation') === 'other')>其他问题</option>
                    </select>
                </label>
                @auth
                    <label class="block">
                        <span class="font-medium">关联订单（可选）</span>
                        <select class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-2" name="order_id">
                            <option value="">不关联订单</option>
                            @foreach($orders as $order)
                                <option value="{{ $order->id }}" @selected((string) old('order_id', $selectedOrder?->id) === (string) $order->id)>
                                    {{ $order->order_number }} / @money($order->total_cents)
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endauth
                <label class="block">
                    <span class="font-medium">主题</span>
                    <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2" type="text" name="subject" value="{{ old('subject', $selectedOrder ? '订单 '.$selectedOrder->order_number.' 售后咨询' : '') }}" maxlength="120" required>
                </label>
                <label class="block">
                    <span class="font-medium">内容</span>
                    <textarea class="mt-1 min-h-36 w-full rounded-sm border border-slate-300 px-3 py-2" name="message" maxlength="3000" required>{{ old('message', $selectedOrder ? "我想咨询这个订单的售后问题。\n订单号：{$selectedOrder->order_number}" : '') }}</textarea>
                </label>
                @guest
                    <label class="block">
                        <span class="font-medium">联系邮箱（可选）</span>
                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2" type="email" name="guest_email" value="{{ old('guest_email') }}" maxlength="255">
                    </label>
                @endguest
                <button class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 font-medium text-white hover:bg-blue-800" type="submit">提交</button>
            </form>
        </aside>
    </section>
</x-layouts.app>
