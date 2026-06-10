@php
    use App\Models\SupportChatMessage;
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
        <section class="rounded-sm border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
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
                    <span class="rounded-sm border border-red-200 bg-red-50 px-3 py-1 text-sm text-red-700">用户已删除并关闭窗口，客服不可继续回复</span>
                @endif
            </div>
        </section>

        <section class="overflow-hidden rounded-sm border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="max-h-[560px] space-y-4 overflow-y-auto bg-gray-50 p-4 dark:bg-gray-950">
                @forelse($record->messages as $message)
                    @if($message->sender_type === SupportChatMessage::SENDER_SYSTEM)
                        <div class="flex items-center gap-3 text-xs text-gray-500">
                            <span class="h-px flex-1 bg-gray-200 dark:bg-gray-700"></span>
                            <span>{{ $message->body }}</span>
                            <span class="h-px flex-1 bg-gray-200 dark:bg-gray-700"></span>
                        </div>
                        @continue
                    @endif

                    @if($message->sender_type === SupportChatMessage::SENDER_ADMIN && optional($record->messages[$loop->index - 1] ?? null)->sender_user_id !== $message->sender_user_id)
                        <div class="flex items-center gap-3 text-xs text-gray-500">
                            <span class="h-px flex-1 bg-gray-200 dark:bg-gray-700"></span>
                            <span>客服 {{ $message->sender?->displayName() ?? '后台用户' }} 为您服务</span>
                            <span class="h-px flex-1 bg-gray-200 dark:bg-gray-700"></span>
                        </div>
                    @endif

                    @php($isAdmin = $message->sender_type === SupportChatMessage::SENDER_ADMIN)
                    <article class="flex {{ $isAdmin ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[78%] rounded-sm border px-3 py-2 text-sm shadow-sm {{ $isAdmin ? 'border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950' : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900' }}">
                            <p class="text-xs text-gray-500">
                                {{ $isAdmin ? ($message->sender?->displayName() ?? '客服') : ($record->user?->displayName() ?? $record->guest_id ?? '客户') }}
                                / {{ $message->created_at->format('Y-m-d H:i') }}
                            </p>
                            @if($message->body)
                                <p class="mt-1 whitespace-pre-line text-gray-800 dark:text-gray-100">{{ $message->body }}</p>
                            @endif
                            @if($message->hasAttachment())
                                <div class="mt-2">
                                    @if($message->isImage())
                                        <a href="{{ route('support.messages.attachment', $message) }}" target="_blank" rel="noopener">
                                            <img class="max-h-64 rounded-sm border border-gray-200 object-contain dark:border-gray-700" src="{{ route('support.messages.attachment', $message) }}" alt="{{ $message->attachment_original_name }}">
                                        </a>
                                    @else
                                        <a class="inline-flex rounded-sm border border-gray-300 bg-white px-3 py-2 text-xs font-medium hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900" href="{{ route('support.messages.attachment', $message) }}" target="_blank" rel="noopener">
                                            下载附件：{{ $message->attachment_original_name }}
                                        </a>
                                    @endif
                                </div>
                            @endif
                            @if($isAdmin)
                                <p class="mt-1 text-right text-[11px] text-blue-700 dark:text-blue-300">
                                    {{ $message->read_at ? '✓✓ 用户已读' : '✓ 已发送' }}
                                </p>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="rounded-sm border border-dashed border-gray-300 bg-white px-4 py-10 text-center text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        暂无消息。
                    </div>
                @endforelse
            </div>

            <form wire:submit="sendReply" class="border-t border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                @if($this->quickReplies()->isNotEmpty())
                    <div class="mb-3 rounded-sm border border-gray-200 bg-gray-50 p-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                        <p class="mb-2 font-medium">预设回复</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($this->quickReplies() as $reply)
                                <button
                                    class="rounded-sm border border-gray-300 bg-white px-3 py-1.5 text-xs hover:bg-blue-50 dark:border-gray-700 dark:bg-gray-900"
                                    type="button"
                                    wire:click="useQuickReply({{ $reply->id }})"
                                >
                                    {{ $reply->title }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
                <label class="block">
                    <span class="text-sm font-medium">回复消息</span>
                    <textarea
                        wire:model="replyMessage"
                        class="mt-2 min-h-24 w-full rounded-sm border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950"
                        @disabled($record->isClosed())
                        placeholder="{{ $record->isClosed() ? '用户已关闭该窗口，不能继续回复。' : '输入给客户的回复...' }}"
                    ></textarea>
                </label>
                <div class="mt-3 flex justify-end">
                    <x-filament::button type="submit" :disabled="$record->isClosed()">
                        发送回复
                    </x-filament::button>
                </div>
            </form>
        </section>

        <section class="rounded-sm border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            {{ $this->form }}
        </section>
    </div>
</x-filament-panels::page>
