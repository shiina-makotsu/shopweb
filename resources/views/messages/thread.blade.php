<x-layouts.app :title="'私聊 - '.$otherUser->displayName()">
    <section class="rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
            <h1 class="text-lg font-semibold">私聊：{{ $otherUser->displayName() }}</h1>
            <p class="mt-1 text-xs text-slate-600">用户 ID：{{ $otherUser->public_id }}</p>
        </div>
        <div class="space-y-3 px-4 py-4">
            @forelse($messages as $message)
                <div class="max-w-2xl rounded-sm border px-3 py-2 text-sm {{ $message->sender_id === auth()->id() ? 'ml-auto border-blue-200 bg-blue-50' : 'border-slate-200 bg-slate-50' }}">
                    <p class="text-xs text-slate-500">{{ $message->sender_id === auth()->id() ? '我' : $otherUser->displayName() }} / {{ $message->created_at->format('Y-m-d H:i') }}</p>
                    <p class="mt-1 whitespace-pre-line">{{ $message->body }}</p>
                </div>
            @empty
                <p class="text-sm text-slate-600">还没有消息。</p>
            @endforelse
        </div>
        <form method="post" action="{{ route('messages.store', $otherUser) }}" class="border-t border-slate-200 px-4 py-4">
            @csrf
            <textarea class="min-h-28 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="body" maxlength="2000" required>{{ old('body') }}</textarea>
            <button class="mt-3 rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800" type="submit">发送</button>
        </form>
    </section>
</x-layouts.app>
