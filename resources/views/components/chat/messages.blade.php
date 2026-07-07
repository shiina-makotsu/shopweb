@props([
    'messages',
    'mode' => 'support',
    'session' => null,
    'mineMode' => 'customer',
    'otherUser' => null,
    'dark' => false,
    'emptyText' => '暂无消息。',
])

@php
    use App\Models\SupportChatMessage;
    use Illuminate\Support\Str;

    $currentUser = auth()->user();
    $mutedText = $dark ? 'text-gray-400' : 'text-slate-500';
    $lineClass = $dark ? 'bg-gray-700' : 'bg-slate-200';
@endphp

@forelse($messages as $message)
    @php
        $isSupport = $mode === 'support';
        $isSystem = $isSupport && $message->sender_type === SupportChatMessage::SENDER_SYSTEM;
    @endphp

    @if($isSystem)
        <div class="flex items-center gap-3 text-xs {{ $mutedText }}">
            <span class="h-px flex-1 {{ $lineClass }}"></span>
            <span>{{ $message->body }}</span>
            <span class="h-px flex-1 {{ $lineClass }}"></span>
        </div>
        @continue
    @endif

    @php
        if ($isSupport) {
            $isAdmin = $message->sender_type === SupportChatMessage::SENDER_ADMIN;
            $isCustomerSide = in_array($message->sender_type, [SupportChatMessage::SENDER_CUSTOMER, SupportChatMessage::SENDER_GUEST], true);
            $isMine = $mineMode === 'admin' ? $isAdmin : $isCustomerSide;
            $senderName = $isAdmin
                ? ($message->sender?->displayName() ?? '客服')
                : ($session?->user?->displayName() ?? $session?->guest_id ?? '客户');
            $avatarUser = $isAdmin ? $message->sender : $session?->user;
            $attachmentUrl = $message->hasAttachment() ? \App\Support\Url::route('support.messages.attachment', $message) : null;
            $attachmentName = $message->attachment_original_name;
        } else {
            $isMine = (int) $message->sender_id === (int) $currentUser?->id;
            $senderName = $isMine ? '我' : ($otherUser?->displayName() ?? $message->sender?->displayName() ?? '对方');
            $avatarUser = $isMine ? $currentUser : ($otherUser ?? $message->sender);
            $attachmentUrl = $message->hasAttachment() ? \App\Support\Url::route('messages.attachment', $message) : null;
            $attachmentName = $message->attachment_original_name;
        }

        $avatarUrl = $avatarUser?->getFilamentAvatarUrl();
        $bubbleClass = $isMine
            ? ($dark ? 'border-blue-900 bg-blue-950 text-gray-100' : 'border-blue-200 bg-blue-50 text-slate-900')
            : ($dark ? 'border-gray-700 bg-gray-900 text-gray-100' : 'border-slate-200 bg-white text-slate-900');
        $searchText = Str::lower((string) $message->body.' '.$senderName.' '.$attachmentName);
    @endphp

    @if($isSupport && $message->sender_type === SupportChatMessage::SENDER_ADMIN && optional($messages[$loop->index - 1] ?? null)->sender_user_id !== $message->sender_user_id)
        <div class="flex items-center gap-3 text-xs {{ $mutedText }}">
            <span class="h-px flex-1 {{ $lineClass }}"></span>
            <span>客服 {{ $senderName }} 为您服务</span>
            <span class="h-px flex-1 {{ $lineClass }}"></span>
        </div>
    @endif

    <article
        class="flex items-start gap-2 {{ $isMine ? 'justify-end' : 'justify-start' }}"
        data-chat-message="{{ $message->id }}"
        data-private-message
        data-chat-search-text="{{ e($searchText) }}"
        data-private-search-text="{{ e($searchText) }}"
    >
        @unless($isMine)
            <div class="mt-1 h-9 w-9 shrink-0 overflow-hidden rounded-full border {{ $dark ? 'border-gray-700 bg-gray-800 text-gray-300' : 'border-slate-200 bg-slate-100 text-slate-500' }} text-xs font-semibold">
                @if($avatarUrl)
                    <img class="h-full w-full object-cover" src="{{ $avatarUrl }}" alt="{{ $senderName }}">
                @else
                    <span class="flex h-full w-full items-center justify-center"><i class="fa-regular fa-circle-user shop-default-avatar-icon" aria-hidden="true"></i></span>
                @endif
            </div>
        @endunless

        <div
            class="max-w-[84%] rounded-3xl border px-4 py-2.5 text-sm shadow-sm {{ $bubbleClass }} {{ $isMine ? 'rounded-br-lg' : 'rounded-bl-lg' }}"
            style="border-radius: 20px; {{ $isMine ? 'border-bottom-right-radius: 8px;' : 'border-bottom-left-radius: 8px;' }}"
        >
            <p class="text-xs {{ $mutedText }}">
                {{ $isMine ? '我' : $senderName }} / {{ $message->created_at->format('Y-m-d H:i') }}
            </p>

            @if(filled($message->body))
                <p class="mt-1 whitespace-pre-line leading-6">{{ $message->body }}</p>
            @endif

            @if($message->hasAttachment())
                <div class="mt-2">
                    @if($message->isImage())
                        <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener">
                            <img class="max-h-64 rounded-2xl border {{ $dark ? 'border-gray-700' : 'border-slate-200' }} object-contain" src="{{ $attachmentUrl }}" alt="{{ $attachmentName }}">
                        </a>
                    @else
                        <a class="inline-flex rounded-full border px-3 py-2 text-xs font-medium {{ $dark ? 'border-gray-700 bg-gray-950 hover:bg-gray-800' : 'border-slate-300 bg-white hover:bg-slate-50' }}" href="{{ $attachmentUrl }}" target="_blank" rel="noopener">
                            下载附件：{{ $attachmentName }}
                        </a>
                    @endif
                </div>
            @endif

            @if($isMine && $isSupport)
                <p class="mt-1 text-right text-[11px] {{ $dark ? 'text-blue-300' : 'text-blue-700' }}">
                    {{ $message->read_at ? '已读' : '已发送' }}
                </p>
            @endif
        </div>
    </article>
@empty
    <div class="rounded-2xl border border-dashed px-4 py-10 text-center text-sm {{ $dark ? 'border-gray-700 bg-gray-900 text-gray-300' : 'border-slate-300 bg-white text-slate-600' }}">
        {{ $emptyText }}
    </div>
@endforelse
