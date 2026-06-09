<x-layouts.app title="客服会话">
    <section class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="flex min-h-[620px] flex-col rounded-sm border border-slate-300 bg-white">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 bg-slate-100 px-4 py-3">
                <div>
                    <h1 class="text-lg font-semibold">客服会话</h1>
                    @if($guestId ?? null)
                        <p class="mt-1 text-xs text-slate-600">游客 ID：{{ $guestId }}。当前浏览器会话用于继续查看客服回复。</p>
                    @endif
                    @if($session->isEnded())
                        <p class="mt-1 text-xs text-amber-700">本次接待已结束。继续发送消息会重新发起会话。</p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2 text-xs">
                    <a class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-medium hover:bg-slate-50" href="{{ route('support.demands') }}">售后/客服需求</a>
                    <form method="post" action="{{ route('support.sessions.destroy', $session) }}">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-medium hover:bg-slate-50" type="submit">删除当前窗口</button>
                    </form>
                </div>
            </div>

            <div class="flex-1 space-y-4 overflow-y-auto px-4 py-4">
                @forelse($session->messages as $message)
                    @if($message->sender_type === \App\Models\SupportChatMessage::SENDER_ADMIN && optional($session->messages[$loop->index - 1] ?? null)->sender_user_id !== $message->sender_user_id)
                        <div class="flex items-center gap-3 text-xs text-slate-500">
                            <span class="h-px flex-1 bg-slate-200"></span>
                            <span>客服 {{ $message->sender?->displayName() ?? '后台用户' }} 为您服务</span>
                            <span class="h-px flex-1 bg-slate-200"></span>
                        </div>
                    @endif

                    @php($isMine = in_array($message->sender_type, [\App\Models\SupportChatMessage::SENDER_CUSTOMER, \App\Models\SupportChatMessage::SENDER_GUEST], true))
                    <article class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[78%] rounded-sm border px-3 py-2 text-sm {{ $isMine ? 'border-blue-200 bg-blue-50' : 'border-slate-200 bg-slate-50' }}">
                            <p class="text-xs text-slate-500">
                                {{ $isMine ? '我' : ($message->sender?->displayName() ?? '客服') }}
                                / {{ $message->created_at->format('Y-m-d H:i') }}
                            </p>
                            @if($message->body)
                                <p class="mt-1 whitespace-pre-line text-slate-800">{{ $message->body }}</p>
                            @endif
                            @if($message->hasAttachment())
                                <div class="mt-2">
                                    @if($message->isImage())
                                        <a href="{{ route('support.messages.attachment', $message) }}" target="_blank" rel="noopener">
                                            <img class="max-h-64 rounded-sm border border-slate-200 object-contain" src="{{ route('support.messages.attachment', $message) }}" alt="{{ $message->attachment_original_name }}">
                                        </a>
                                    @else
                                        <a class="inline-flex rounded-sm border border-slate-300 bg-white px-3 py-2 text-xs font-medium hover:bg-slate-50" href="{{ route('support.messages.attachment', $message) }}">
                                            下载附件：{{ $message->attachment_original_name }}
                                        </a>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="rounded-sm border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-600">
                        暂无消息。你可以直接发送文字、图片或文件给客服。
                    </div>
                @endforelse
            </div>

            <form method="post" action="{{ route('support.messages.store') }}" enctype="multipart/form-data" class="space-y-3 border-t border-slate-200 px-4 py-4 text-sm">
                @csrf
                @auth
                    <label class="block">
                        <span class="font-medium">关联订单（可选）</span>
                        <select class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-2" name="order_id">
                            <option value="">不关联订单</option>
                            @foreach($orders as $order)
                                <option value="{{ $order->id }}" @selected((string) old('order_id', $selectedOrder?->id ?? $session->order_id) === (string) $order->id)>
                                    {{ $order->order_number }} / @money($order->total_cents)
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endauth
                <label class="block">
                    <span class="font-medium">消息</span>
                    <textarea class="mt-1 min-h-28 w-full rounded-sm border border-slate-300 px-3 py-2" name="message" maxlength="3000" placeholder="输入要发送给客服的内容">{{ old('message', $selectedOrder ? "我想咨询这个订单。\n订单号：{$selectedOrder->order_number}" : '') }}</textarea>
                </label>
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="block">
                        <span class="font-medium">图片/文件</span>
                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2" type="file" name="attachment">
                    </label>
                    @guest
                        <label class="block">
                            <span class="font-medium">联系邮箱（可选）</span>
                            <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2" type="email" name="guest_email" value="{{ old('guest_email', $session->guest_email) }}" maxlength="255">
                        </label>
                    @endguest
                </div>
                <button class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 font-medium text-white hover:bg-blue-800" type="submit">发送</button>
            </form>
        </div>

        <aside class="space-y-4">
            <div class="rounded-sm border border-slate-300 bg-white">
                <h2 class="border-b border-slate-200 bg-slate-100 px-4 py-3 text-base font-semibold">当前接待</h2>
                <div class="space-y-2 px-4 py-4 text-sm text-slate-700">
                    <p>状态：{{ match($session->status) {
                        'active' => '接待中',
                        'ended' => '已结束',
                        default => '等待客服接入',
                    } }}</p>
                    <p>客服：{{ $session->assignedAdmin?->displayName() ?? '尚未接入' }}</p>
                    <p>完成接待次数：{{ $session->served_count }}</p>
                    @if($session->order)
                        <p>关联订单：{{ $session->order->order_number }}</p>
                    @endif
                </div>
            </div>

            <div class="rounded-sm border border-slate-300 bg-white">
                <h2 class="border-b border-slate-200 bg-slate-100 px-4 py-3 text-base font-semibold">说明</h2>
                <div class="space-y-2 px-4 py-4 text-sm text-slate-700">
                    <p>长时间没有新消息时，系统会自动结束本次接待。</p>
                    <p>你仍然可以在当前窗口继续发送消息，系统会重新发起客服会话。</p>
                </div>
            </div>
        </aside>
    </section>
</x-layouts.app>
