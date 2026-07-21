@php
    use App\Models\SupportChatSession;

    $statusLabels = [
        SupportChatSession::STATUS_ACTIVE => '接待中',
        SupportChatSession::STATUS_ENDED => '已结束',
        SupportChatSession::STATUS_OPEN => '等待接入',
        SupportChatSession::STATUS_CLOSED => '已关闭',
    ];
@endphp

<x-layouts.app title="客服会话" :wide="true">
    <section class="grid min-h-[720px] overflow-hidden rounded-2xl border border-slate-300 bg-white lg:grid-cols-[300px_minmax(0,1fr)]">
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
                        <button class="rounded-full border border-blue-700 bg-blue-700 px-3 py-2 text-xs font-medium text-white hover:bg-blue-800" type="submit">新会话</button>
                    </form>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                    <a class="relative rounded-xl border border-slate-300 bg-white px-3 py-2 text-center font-medium hover:bg-blue-50 hover:text-blue-800" href="{{ route('support.index') }}">
                        即时会话
                        @if(($supportChatUnreadMessageCount ?? 0) > 0)
                            <span class="absolute right-1 top-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[9px] text-white">{{ $supportChatUnreadMessageCount > 99 ? '99+' : $supportChatUnreadMessageCount }}</span>
                        @endif
                    </a>
                    <a class="relative rounded-xl border border-slate-300 bg-white px-3 py-2 text-center font-medium hover:bg-blue-50 hover:text-blue-800" href="{{ route('support.demands') }}">
                        工单 / 售后
                        @if(($supportCaseUnreadCount ?? 0) > 0)
                            <span class="absolute right-1 top-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[9px] text-white">{{ $supportCaseUnreadCount > 99 ? '99+' : $supportCaseUnreadCount }}</span>
                        @endif
                    </a>
                </div>
                <x-support.contact-methods compact class="mt-3" />
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
                            <span class="rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[11px] text-slate-600">{{ $label }}</span>
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
                    <p class="mt-1 text-xs text-slate-600" data-support-session-meta>
                        状态：<span data-support-session-status>{{ $statusLabels[$session->status] ?? '等待接入' }}</span>
                        / 客服：<span data-support-session-admin>{{ $session->assignedAdmin?->displayName() ?? '尚未接入' }}</span>
                        / 完成接待：{{ $session->served_count }} 次
                    </p>
                    @if($session->order)
                        <p class="mt-1 text-xs text-slate-600">关联订单：{{ $session->order->order_number }}</p>
                    @endif
                    @if($session->isEnded())
                        <p class="mt-1 text-xs text-amber-700">本次接待已结束。继续发送消息会重新发起会话。</p>
                    @endif
                    @if(! $session->assigned_admin_id && $session->status === SupportChatSession::STATUS_OPEN)
                        <div class="mt-3 rounded-2xl border border-blue-200 bg-blue-50 px-3 py-2 text-xs leading-5 text-blue-900">
                            正在排队等待客服接入。最长等待约 {{ $supportAiIdleMinutes }} 分钟。
                            @if($supportAiEnabled)
                                超过后会自动进入 AI 安抚接待，你仍然可以继续补充订单、截图或文件。
                            @else
                                客服会按队列尽快处理，你可以继续补充订单、截图或文件。
                            @endif
                        </div>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2 text-xs">
                    <a class="rounded-full border border-slate-300 bg-white px-3 py-2 font-medium hover:bg-slate-50" href="{{ route('support.demands') }}">提交客服工单</a>
                    <button
                        class="rounded-full border border-red-200 bg-white px-3 py-2 font-medium text-red-700 hover:bg-red-50"
                        type="button"
                        data-open-support-delete
                    >
                        删除当前窗口
                    </button>
                </div>
            </div>

            <div class="border-b border-slate-200 bg-white px-4 py-3">
                <label class="block text-xs font-medium text-slate-600">
                    搜索聊天记录
                    <input
                        class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                        type="search"
                        placeholder="搜索消息内容、客服或客户..."
                        data-chat-search
                    >
                </label>
            </div>

            <div
                class="flex-1 space-y-4 overflow-y-auto bg-slate-50 px-4 py-4"
                data-support-messages
                data-support-messages-url="{{ \App\Support\Url::route('support.sessions.messages', $session) }}"
                data-last-message-id="{{ $session->messages->max('id') ?? 0 }}"
            >
                @include('support.partials.messages', [
                    'session' => $session,
                    'mineMode' => 'customer',
                    'emptyText' => '暂无消息。点击底部 + 可以附带图片或文件。',
                ])
            </div>

            <x-chat.composer
                :action="route('support.messages.store')"
                message-name="message"
                attachment-name="attachment"
                placeholder="输入消息..."
                :hidden="['support_chat_session_id' => $session->id]"
                :value="old('message', $selectedOrder ? '我想咨询这个订单。' : '')"
            >
                <div class="mb-3 grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-700 md:grid-cols-2">
                    @auth
                        <label class="block">
                            <span class="font-medium">订单信息</span>
                            <select class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" name="order_id">
                                <option value="">不发送订单信息</option>
                                @foreach($orders as $order)
                                    <option value="{{ $order->id }}" @selected((string) old('order_id', $selectedOrder?->id ?? $session->order_id) === (string) $order->id)>
                                        {{ $order->order_number }} / @money($order->total_cents)
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label class="flex items-center gap-2 self-end rounded-xl border border-slate-200 bg-white px-3 py-2">
                            <input type="checkbox" name="include_order" value="1" @checked($selectedOrder || $session->order_id)>
                            <span>随消息发送订单信息</span>
                        </label>
                    @endauth
                    @guest
                        <label class="block">
                            <span class="font-medium">联系邮箱（可选）</span>
                            <input class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" type="email" name="guest_email" value="{{ old('guest_email', $session->guest_email) }}" maxlength="255">
                        </label>
                    @endguest
                </div>
            </x-chat.composer>
        </div>
    </section>

    <dialog id="support-delete-dialog" class="rounded-2xl border border-slate-300 p-0 shadow-xl backdrop:bg-slate-900/30">
        <form method="post" action="{{ route('support.sessions.destroy', $session) }}" class="w-[min(92vw,420px)] space-y-4 bg-white p-5 text-sm">
            @csrf
            @method('DELETE')
            <div>
                <h2 class="text-lg font-semibold">删除当前会话窗口？</h2>
                <p class="mt-2 leading-6 text-slate-600">删除后会话会被关闭并从你的列表中隐藏，后台客服也不能继续回复这个窗口。</p>
            </div>
            <div class="flex justify-end gap-2">
                <button class="rounded-full border border-slate-300 px-4 py-2 font-medium hover:bg-slate-50" type="button" data-close-support-delete>取消</button>
                <button class="rounded-full border border-red-600 bg-red-600 px-4 py-2 font-medium text-white hover:bg-red-700" type="submit">确认删除</button>
            </div>
        </form>
    </dialog>

    <script>
        (() => {
            const dialog = document.getElementById('support-delete-dialog');
            document.querySelector('[data-open-support-delete]')?.addEventListener('click', () => dialog?.showModal());
            document.querySelector('[data-close-support-delete]')?.addEventListener('click', () => dialog?.close());

            const container = document.querySelector('[data-support-messages]');
            const search = document.querySelector('[data-chat-search]');
            const status = document.querySelector('[data-support-session-status]');
            const admin = document.querySelector('[data-support-session-admin]');
            let isPolling = false;

            const applySearch = () => {
                const query = (search?.value || '').trim().toLowerCase();
                container?.querySelectorAll('[data-chat-message]').forEach((message) => {
                    const text = message.dataset.chatSearchText || '';
                    message.classList.toggle('hidden', query !== '' && !text.includes(query));
                });
            };

            const pollMessages = async () => {
                if (!container || isPolling || document.hidden) return;

                isPolling = true;
                try {
                    const url = new URL(container.dataset.supportMessagesUrl, window.location.href);
                    url.searchParams.set('last_message_id', container.dataset.lastMessageId || '0');
                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    const data = await response.json();

                    if (!response.ok) return;

                    if (data.html && String(data.last_message_id) !== container.dataset.lastMessageId) {
                        const pinnedBottom = container.scrollTop + container.clientHeight >= container.scrollHeight - 80;
                        container.innerHTML = data.html;
                        container.dataset.lastMessageId = String(data.last_message_id || 0);
                        if (status) status.textContent = data.status_label || status.textContent;
                        if (admin) admin.textContent = data.assigned_admin || admin.textContent;
                        applySearch();
                        if (pinnedBottom) container.scrollTop = container.scrollHeight;
                    }
                } catch (error) {
                    // Polling is best-effort; the normal form flow still works.
                } finally {
                    isPolling = false;
                }
            };

            search?.addEventListener('input', applySearch);
            container?.scrollTo({ top: container.scrollHeight });
            window.setInterval(pollMessages, 3000);
        })();
    </script>
</x-layouts.app>
