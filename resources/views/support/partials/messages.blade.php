@php
    use App\Models\SupportChatMessage;
    use Illuminate\Support\Str;

    $messages = $session->messages;
    $dark = (bool) ($dark ?? false);
    $mineMode = $mineMode ?? 'customer';
    $emptyText = $emptyText ?? '暂无消息。';
@endphp

@forelse($messages as $message)
    @if($message->sender_type === SupportChatMessage::SENDER_SYSTEM)
        <div class="flex items-center gap-3 text-xs {{ $dark ? 'text-gray-500' : 'text-slate-500' }}">
            <span class="h-px flex-1 {{ $dark ? 'bg-gray-200 dark:bg-gray-700' : 'bg-slate-200' }}"></span>
            <span>{{ $message->body }}</span>
            <span class="h-px flex-1 {{ $dark ? 'bg-gray-200 dark:bg-gray-700' : 'bg-slate-200' }}"></span>
        </div>
        @continue
    @endif

    @if($message->sender_type === SupportChatMessage::SENDER_ADMIN && optional($messages[$loop->index - 1] ?? null)->sender_user_id !== $message->sender_user_id)
        <div class="flex items-center gap-3 text-xs {{ $dark ? 'text-gray-500' : 'text-slate-500' }}">
            <span class="h-px flex-1 {{ $dark ? 'bg-gray-200 dark:bg-gray-700' : 'bg-slate-200' }}"></span>
            <span>客服 {{ $message->sender?->displayName() ?? '后台用户' }} 为您服务</span>
            <span class="h-px flex-1 {{ $dark ? 'bg-gray-200 dark:bg-gray-700' : 'bg-slate-200' }}"></span>
        </div>
    @endif

    @php
        $isAdmin = $message->sender_type === SupportChatMessage::SENDER_ADMIN;
        $isCustomerSide = in_array($message->sender_type, [SupportChatMessage::SENDER_CUSTOMER, SupportChatMessage::SENDER_GUEST], true);
        $isMine = $mineMode === 'admin' ? $isAdmin : $isCustomerSide;
        $senderName = $isAdmin
            ? ($message->sender?->displayName() ?? '客服')
            : ($session->user?->displayName() ?? $session->guest_id ?? '客户');
    @endphp

    <article class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}" data-chat-message="{{ $message->id }}" data-chat-search-text="{{ e(Str::lower((string) $message->body.' '.$senderName)) }}">
        <div class="max-w-[84%] rounded-sm border px-3 py-2 text-sm shadow-sm {{ $isMine ? 'border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950' : ($dark ? 'border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900' : 'border-slate-200 bg-white') }}">
            <p class="text-xs {{ $dark ? 'text-gray-500' : 'text-slate-500' }}">
                {{ $isMine ? '我' : $senderName }}
                / {{ $message->created_at->format('Y-m-d H:i') }}
            </p>
            @if($message->body !== null && $message->body !== '')
                <p class="mt-1 whitespace-pre-line {{ $dark ? 'text-gray-800 dark:text-gray-100' : 'text-slate-800' }}">{{ $message->body }}</p>
            @endif
            @if($message->hasAttachment())
                <div class="mt-2">
                    @if($message->isImage())
                        <a href="{{ \App\Support\Url::route('support.messages.attachment', $message) }}" target="_blank" rel="noopener">
                            <img class="max-h-64 rounded-sm border {{ $dark ? 'border-gray-200 dark:border-gray-700' : 'border-slate-200' }} object-contain" src="{{ \App\Support\Url::route('support.messages.attachment', $message) }}" alt="{{ $message->attachment_original_name }}">
                        </a>
                    @else
                        <a class="inline-flex rounded-sm border {{ $dark ? 'border-gray-300 bg-white dark:border-gray-700 dark:bg-gray-900' : 'border-slate-300 bg-white' }} px-3 py-2 text-xs font-medium hover:bg-slate-50" href="{{ \App\Support\Url::route('support.messages.attachment', $message) }}" target="_blank" rel="noopener">
                            下载附件：{{ $message->attachment_original_name }}
                        </a>
                    @endif
                </div>
            @endif
            @if($isMine)
                <p class="mt-1 text-right text-[11px] text-blue-700 dark:text-blue-300">
                    {{ $message->read_at ? '✓✓ 已读' : '✓ 已发送' }}
                </p>
            @endif
        </div>
    </article>
@empty
    <div class="rounded-sm border border-dashed {{ $dark ? 'border-gray-300 bg-white text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300' : 'border-slate-300 bg-white text-slate-600' }} px-4 py-10 text-center text-sm">
        {{ $emptyText }}
    </div>
@endforelse
