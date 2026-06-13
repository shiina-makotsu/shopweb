<x-layouts.app :title="'私聊 - '.$otherUser->displayName()">
    <section class="flex min-h-[680px] flex-col overflow-hidden rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-lg font-semibold">私聊：{{ $otherUser->displayName() }}</h1>
                    <p class="mt-1 text-xs text-slate-600">用户 ID：{{ $otherUser->public_id }}</p>
                </div>
                <label class="w-full text-xs font-medium text-slate-600 sm:w-72">
                    搜索聊天记录
                    <input class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm" type="search" placeholder="搜索消息内容或文件名..." data-private-search>
                </label>
            </div>
        </div>
        <div class="flex-1 space-y-3 overflow-y-auto bg-slate-50 px-4 py-4" data-private-messages>
            @forelse($messages as $message)
                <div
                    class="max-w-2xl rounded-sm border px-3 py-2 text-sm shadow-sm {{ $message->sender_id === auth()->id() ? 'ml-auto border-blue-200 bg-blue-50' : 'border-slate-200 bg-white' }}"
                    data-private-message
                    data-private-search-text="{{ e(\Illuminate\Support\Str::lower((string) $message->body.' '.$message->attachment_original_name)) }}"
                >
                    <p class="text-xs text-slate-500">{{ $message->sender_id === auth()->id() ? '我' : $otherUser->displayName() }} / {{ $message->created_at->format('Y-m-d H:i') }}</p>
                    @if($message->body !== null && $message->body !== '')
                        <p class="mt-1 whitespace-pre-line text-slate-800">{{ $message->body }}</p>
                    @endif
                    @if($message->hasAttachment())
                        <div class="mt-2">
                            @if($message->isImage())
                                <a href="{{ \App\Support\Url::route('messages.attachment', $message) }}" target="_blank" rel="noopener">
                                    <img class="max-h-64 rounded-sm border border-slate-200 object-contain" src="{{ \App\Support\Url::route('messages.attachment', $message) }}" alt="{{ $message->attachment_original_name }}">
                                </a>
                            @else
                                <a class="inline-flex rounded-sm border border-slate-300 bg-white px-3 py-2 text-xs font-medium hover:bg-slate-50" href="{{ \App\Support\Url::route('messages.attachment', $message) }}">
                                    下载附件：{{ $message->attachment_original_name }}
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <p class="rounded-sm border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-600">还没有消息。</p>
            @endforelse
        </div>
        <form method="post" action="{{ route('messages.store', $otherUser) }}" enctype="multipart/form-data" class="border-t border-slate-200 bg-white px-4 py-4">
            @csrf
            <div class="flex items-end gap-2">
                <label class="flex h-10 w-10 cursor-pointer items-center justify-center rounded-full border border-slate-300 bg-slate-50 text-xl font-semibold hover:bg-blue-50" title="添加图片/文件" aria-label="添加图片/文件">
                    +
                    <input class="hidden" type="file" name="attachment">
                </label>
                <textarea class="min-h-10 flex-1 resize-none rounded-sm border border-slate-300 px-3 py-2 text-sm leading-6" name="body" maxlength="2000" rows="1" placeholder="输入消息...">{{ old('body') }}</textarea>
                <button class="h-10 shrink-0 rounded-sm border border-blue-700 bg-blue-700 px-4 text-sm font-medium text-white hover:bg-blue-800" type="submit">发送</button>
            </div>
        </form>
    </section>

    <script>
        (() => {
            const search = document.querySelector('[data-private-search]');
            const container = document.querySelector('[data-private-messages]');
            const applySearch = () => {
                const query = (search?.value || '').trim().toLowerCase();
                container?.querySelectorAll('[data-private-message]').forEach((message) => {
                    const text = message.dataset.privateSearchText || '';
                    message.classList.toggle('hidden', query !== '' && !text.includes(query));
                });
            };

            search?.addEventListener('input', applySearch);
            container?.scrollTo({ top: container.scrollHeight });
        })();
    </script>
</x-layouts.app>
