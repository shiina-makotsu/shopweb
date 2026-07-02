<x-layouts.app :title="'私聊 - '.$otherUser->displayName()">
    <section class="flex min-h-[680px] flex-col overflow-hidden rounded-2xl border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-lg font-semibold">私聊：{{ $otherUser->displayName() }}</h1>
                    <p class="mt-1 text-xs text-slate-600">用户 ID：{{ $otherUser->public_id }}</p>
                </div>
                <label class="w-full text-xs font-medium text-slate-600 sm:w-72">
                    搜索聊天记录
                    <input class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" type="search" placeholder="搜索消息内容或文件名..." data-private-search>
                </label>
            </div>
        </div>

        <div class="flex-1 space-y-3 overflow-y-auto bg-slate-50 px-4 py-4" data-private-messages>
            <x-chat.messages
                mode="private"
                :messages="$messages"
                :other-user="$otherUser"
                empty-text="还没有消息。"
            />
        </div>

        <x-chat.composer
            :action="route('messages.store', $otherUser)"
            message-name="body"
            attachment-name="attachment"
            placeholder="输入消息..."
            :value="old('body', '')"
        />
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
