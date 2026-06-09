@php
    use App\Models\SupportChatMessage;
    use App\Models\SupportChatSession;

    $statusLabels = [
        SupportChatSession::STATUS_ACTIVE => '接待中',
        SupportChatSession::STATUS_ENDED => '已结束',
        SupportChatSession::STATUS_OPEN => '等待接入',
    ];
@endphp

<x-layouts.app title="客服会话" :wide="true">
    <section class="grid min-h-[720px] overflow-hidden rounded-sm border border-slate-300 bg-white lg:grid-cols-[300px_minmax(0,1fr)]">
        <aside class="border-b border-slate-300 bg-slate-50 lg:border-b-0 lg:border-r">
            <div class="border-b border-slate-200 px-4 py-3">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h1 class="text-lg font-semibold">客服会话</h1>
                        @if($guestId ?? null)
                            <p class="mt-1 text-xs text-slate-600">游客 ID：{{ $guestId }}</p>
                        @endif
                    </div>
                    <form method="post" action="{{ route('support.sessions.store') }}">
                        @csrf
                        <button class="rounded-sm border border-blue-700 bg-blue-700 px-3 py-2 text-xs font-medium text-white hover:bg-blue-800" type="submit">新会话</button>
                    </form>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                    <a class="rounded-sm border border-slate-300 bg-white px-3 py-2 text-center font-medium hover:bg-blue-50 hover:text-blue-800" href="{{ route('support.index') }}">即时会话</a>
                    <a class="rounded-sm border border-slate-300 bg-white px-3 py-2 text-center font-medium hover:bg-blue-50 hover:text-blue-800" href="{{ route('support.demands') }}">售后需求</a>
                </div>
            </div>

            <nav class="max-h-[640px] divide-y divide-slate-200 overflow-y-auto">
                @foreach($sessions as $chatSession)
                    @php
                        $active = $chatSession->id === $session->id;
                        $label = $statusLabels[$chatSession->status] ?? '等待接入';
                    @endphp
                    <a
                        class="block px-4 py-3 text-sm {{ $active ? 'bg-blue-50 text-blue-900' : 'hover:bg-white' }}"
                        href="{{ route('support.sessions.show', $chatSession) }}"
                    >
                        <span class="flex items-center justify-between gap-2">
                            <span class="font-semibold">会话 #{{ $chatSession->id }}</span>
                            <span class="rounded-sm border border-slate-200 bg-white px-2 py-0.5 text-[11px] text-slate-600">{{ $label }}</span>
                        </span>
                        <span class="mt-1 block truncate text-xs text-slate-600">
                            @if($chatSession->order)
                                订单号：{{ $chatSession->order->order_number }}
                            @elseif($chatSession->assignedAdmin)
                                客服：{{ $chatSession->assignedAdmin->displayName() }}
                            @else
                                新会话窗口
                            @endif
                        </span>
                        <span class="mt-1 block text-[11px] text-slate-500">{{ optional($chatSession->last_message_at ?? $chatSession->created_at)->format('Y-m-d H:i') }}</span>
                    </a>
                @endforeach
            </nav>
        </aside>

        <div class="flex min-h-[720px] min-w-0 flex-col">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 bg-slate-100 px-4 py-3">
                <div>
                    <h2 class="text-lg font-semibold">会话 #{{ $session->id }}</h2>
                    <p class="mt-1 text-xs text-slate-600">
                        状态：{{ $statusLabels[$session->status] ?? '等待接入' }}
                        / 客服：{{ $session->assignedAdmin?->displayName() ?? '尚未接入' }}
                        / 完成接待：{{ $session->served_count }} 次
                    </p>
                    @if($session->order)
                        <p class="mt-1 text-xs text-slate-600">关联订单：订单号：{{ $session->order->order_number }}</p>
                    @endif
                    @if($session->isEnded())
                        <p class="mt-1 text-xs text-amber-700">本次接待已结束。继续发送消息会重新发起会话。</p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2 text-xs">
                    <a class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-medium hover:bg-slate-50" href="{{ route('support.demands') }}">提交售后需求</a>
                    <button
                        class="rounded-sm border border-red-200 bg-white px-3 py-2 font-medium text-red-700 hover:bg-red-50"
                        type="button"
                        data-open-support-delete
                    >
                        删除当前窗口
                    </button>
                </div>
            </div>

            <div class="flex-1 space-y-4 overflow-y-auto bg-slate-50 px-4 py-4">
                @forelse($session->messages as $message)
                    @if($message->sender_type === SupportChatMessage::SENDER_SYSTEM)
                        <div class="flex items-center gap-3 text-xs text-slate-500">
                            <span class="h-px flex-1 bg-slate-200"></span>
                            <span>{{ $message->body }}</span>
                            <span class="h-px flex-1 bg-slate-200"></span>
                        </div>
                        @continue
                    @endif

                    @if($message->sender_type === SupportChatMessage::SENDER_ADMIN && optional($session->messages[$loop->index - 1] ?? null)->sender_user_id !== $message->sender_user_id)
                        <div class="flex items-center gap-3 text-xs text-slate-500">
                            <span class="h-px flex-1 bg-slate-200"></span>
                            <span>客服 {{ $message->sender?->displayName() ?? '后台用户' }} 为您服务</span>
                            <span class="h-px flex-1 bg-slate-200"></span>
                        </div>
                    @endif

                    @php($isMine = in_array($message->sender_type, [SupportChatMessage::SENDER_CUSTOMER, SupportChatMessage::SENDER_GUEST], true))
                    <article class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[84%] rounded-sm border px-3 py-2 text-sm shadow-sm {{ $isMine ? 'border-blue-200 bg-blue-50' : 'border-slate-200 bg-white' }}">
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
                    <div class="rounded-sm border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-600">
                        暂无消息。点击底部加号可以附带订单、图片或文件。
                    </div>
                @endforelse
            </div>

            <form method="post" action="{{ route('support.messages.store') }}" enctype="multipart/form-data" class="border-t border-slate-200 bg-white px-4 py-3 text-sm">
                @csrf
                <input type="hidden" name="support_chat_session_id" value="{{ $session->id }}">

                <div class="flex items-end gap-2">
                    <details class="relative shrink-0">
                        <summary class="flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-full border border-slate-300 bg-slate-50 text-xl font-semibold hover:bg-blue-50" aria-label="添加内容">+</summary>
                        <div class="absolute bottom-12 left-0 z-20 w-72 rounded-sm border border-slate-300 bg-white p-3 shadow-lg">
                            <div class="space-y-3">
                                @auth
                                    <label class="block">
                                        <span class="text-xs font-medium text-slate-600">订单信息</span>
                                        <select class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm" name="order_id">
                                            <option value="">不发送订单信息</option>
                                            @foreach($orders as $order)
                                                <option value="{{ $order->id }}" @selected((string) old('order_id', $selectedOrder?->id ?? $session->order_id) === (string) $order->id)>
                                                    {{ $order->order_number }} / @money($order->total_cents)
                                                </option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                        <input type="checkbox" name="include_order" value="1" @checked($selectedOrder || $session->order_id)>
                                        随消息发送订单信息
                                    </label>
                                    <button class="w-full rounded-sm border border-slate-300 bg-slate-50 px-3 py-2 text-xs font-medium hover:bg-blue-50" type="submit" name="include_order" value="1">
                                        单独发送订单信息
                                    </button>
                                @endauth
                                <label class="block">
                                    <span class="text-xs font-medium text-slate-600">图片/文件</span>
                                    <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-xs" type="file" name="attachment">
                                </label>
                                @guest
                                    <label class="block">
                                        <span class="text-xs font-medium text-slate-600">联系邮箱（可选）</span>
                                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" type="email" name="guest_email" value="{{ old('guest_email', $session->guest_email) }}" maxlength="255">
                                    </label>
                                @endguest
                            </div>
                        </div>
                    </details>

                    <textarea
                        class="min-h-10 flex-1 resize-none rounded-sm border border-slate-300 px-3 py-2 leading-6"
                        name="message"
                        maxlength="3000"
                        rows="1"
                        placeholder="输入消息..."
                    >{{ old('message', $selectedOrder ? '我想咨询这个订单。' : '') }}</textarea>
                    <button class="h-10 shrink-0 rounded-sm border border-blue-700 bg-blue-700 px-4 font-medium text-white hover:bg-blue-800" type="submit">发送</button>
                </div>
            </form>
        </div>
    </section>

    <dialog id="support-delete-dialog" class="rounded-sm border border-slate-300 p-0 shadow-xl backdrop:bg-slate-900/30">
        <form method="post" action="{{ route('support.sessions.destroy', $session) }}" class="w-[min(92vw,420px)] space-y-4 bg-white p-5 text-sm">
            @csrf
            @method('DELETE')
            <div>
                <h2 class="text-lg font-semibold">删除当前会话窗口？</h2>
                <p class="mt-2 leading-6 text-slate-600">删除后会话会被关闭并从你的列表中隐藏，后台客服也不能继续回复这个窗口。</p>
            </div>
            <div class="flex justify-end gap-2">
                <button class="rounded-sm border border-slate-300 px-4 py-2 font-medium hover:bg-slate-50" type="button" data-close-support-delete>不要！</button>
                <button class="rounded-sm border border-red-600 bg-red-600 px-4 py-2 font-medium text-white hover:bg-red-700" type="submit">嗯！</button>
            </div>
        </form>
    </dialog>

    <script>
        (() => {
            const dialog = document.getElementById('support-delete-dialog');
            document.querySelector('[data-open-support-delete]')?.addEventListener('click', () => dialog?.showModal());
            document.querySelector('[data-close-support-delete]')?.addEventListener('click', () => dialog?.close());
        })();
    </script>
</x-layouts.app>
