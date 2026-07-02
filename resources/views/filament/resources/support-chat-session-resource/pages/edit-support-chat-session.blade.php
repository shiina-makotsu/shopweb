@php
    use App\Models\SupportChatSession;

    $record = $this->record->loadMissing(['messages.sender', 'assignedAdmin', 'order', 'user']);
    $statusLabels = [
        SupportChatSession::STATUS_OPEN => '等待接入',
        SupportChatSession::STATUS_ACTIVE => '接待中',
        SupportChatSession::STATUS_ENDED => '已结束',
        SupportChatSession::STATUS_CLOSED => '用户已关闭',
    ];
@endphp

<x-filament-panels::page>
    <div class="space-y-4">
        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">会话 #{{ $record->id }}</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        客户：{{ $record->user?->displayName() ?? $record->guest_id ?? '游客' }}
                        / 状态：{{ $statusLabels[$record->status] ?? $record->status }}
                        / 当前客服：{{ $record->assignedAdmin?->displayName() ?? '尚未接入' }}
                    </p>
                    @if($record->order)
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">关联订单：{{ $record->order->order_number }}</p>
                    @endif
                </div>
                @if($record->isClosed())
                    <span class="rounded-full border border-red-200 bg-red-50 px-3 py-1 text-sm text-red-700">用户已删除并关闭窗口，客服不可继续回复</span>
                @endif
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">
                    搜索聊天记录
                    <input
                        class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950"
                        type="search"
                        placeholder="搜索消息内容、客服或客户..."
                        data-admin-chat-search
                    >
                </label>
            </div>
            <div class="max-h-[560px] space-y-4 overflow-y-auto bg-gray-50 p-4 dark:bg-gray-950" data-admin-support-messages>
                @include('support.partials.messages', [
                    'session' => $record,
                    'mineMode' => 'admin',
                    'dark' => false,
                    'emptyText' => '暂无消息。',
                ])
            </div>

            @if($this->quickReplies()->isNotEmpty())
                <div class="border-t border-gray-200 bg-gray-50 p-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                    <p class="mb-2 font-medium text-gray-700 dark:text-gray-200">预设回复</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($this->quickReplies() as $reply)
                            <button
                                class="rounded-full border border-gray-300 bg-white px-3 py-1.5 text-xs hover:bg-blue-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800"
                                type="button"
                                wire:click="useQuickReply({{ $reply->id }})"
                            >
                                {{ $reply->title }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            <x-chat.composer
                mode="livewire"
                wire-submit="sendReply"
                message-model="replyMessage"
                attachment-model="replyAttachment"
                placeholder="{{ $record->isClosed() ? '用户已关闭该窗口，不能继续回复。' : '输入给客户的回复...' }}"
                submit-label="发送回复"
                :disabled="$record->isClosed()"
            />
        </section>

        <script>
            (() => {
                const search = document.querySelector('[data-admin-chat-search]');
                const container = document.querySelector('[data-admin-support-messages]');
                const applySearch = () => {
                    const query = (search?.value || '').trim().toLowerCase();
                    container?.querySelectorAll('[data-chat-message]').forEach((message) => {
                        const text = message.dataset.chatSearchText || '';
                        message.classList.toggle('hidden', query !== '' && !text.includes(query));
                    });
                };

                search?.addEventListener('input', applySearch);
                container?.scrollTo({ top: container.scrollHeight });
                document.addEventListener('livewire:navigated', applySearch);
            })();
        </script>
    </div>
</x-filament-panels::page>
