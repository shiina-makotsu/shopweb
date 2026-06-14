@php
    $comments = $comments ?? collect();
    $enabled = (bool) ($enabled ?? false);
    $action = $action ?? '#';
@endphp

<section id="comments" class="mt-4 rounded-sm border border-slate-300 bg-white">
    <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
        <h2 class="text-base font-semibold">评论</h2>
    </div>

    @if($enabled)
        <div class="divide-y divide-slate-100">
            @forelse($comments as $comment)
                <article class="px-4 py-4 text-sm">
                    <div class="flex items-start gap-3">
                        <a class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-300 bg-white font-semibold text-blue-700" href="{{ route('users.show', $comment->user) }}">
                            @if($comment->user->avatar_path)
                                <img class="h-full w-full object-cover" src="{{ \App\Support\MediaPath::url($comment->user->avatar_path) }}" alt="{{ $comment->user->displayName() }}">
                            @else
                                {{ mb_substr($comment->user->displayName(), 0, 1) }}
                            @endif
                        </a>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <a class="font-medium hover:text-blue-800" href="{{ route('users.show', $comment->user) }}">{{ $comment->user->displayName() }}</a>
                                <span class="text-xs text-slate-500">{{ $comment->created_at->format('Y-m-d H:i') }}</span>
                                @auth
                                    @if(auth()->id() !== $comment->user_id)
                                        <a class="text-xs text-blue-700 hover:text-blue-900" href="{{ route('messages.thread', $comment->user) }}">私聊</a>
                                    @endif
                                @endauth
                            </div>
                            <p class="mt-2 whitespace-pre-line text-slate-700">{{ $comment->body }}</p>

                            @auth
                                <form method="post" action="{{ $action }}" class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
                                    @csrf
                                    <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                                    <input class="rounded-sm border border-slate-300 px-3 py-2 text-xs" name="body" maxlength="3000" placeholder="回复该评论" required>
                                    <button class="rounded-sm border border-slate-300 px-3 py-2 text-xs hover:bg-slate-50" type="submit">回复</button>
                                </form>
                            @endauth

                            @foreach($comment->replies as $reply)
                                <div class="mt-3 rounded-sm border border-slate-200 bg-slate-50 px-3 py-2">
                                    <p class="text-xs text-slate-500">
                                        <a class="font-medium text-slate-700 hover:text-blue-800" href="{{ route('users.show', $reply->user) }}">{{ $reply->user->displayName() }}</a>
                                        / {{ $reply->created_at->format('Y-m-d H:i') }}
                                    </p>
                                    <p class="mt-1 whitespace-pre-line">{{ $reply->body }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </article>
            @empty
                <p class="px-4 py-8 text-sm text-slate-600">暂无评论。</p>
            @endforelse
        </div>

        @auth
            <form method="post" action="{{ $action }}" class="space-y-3 border-t border-slate-200 px-4 py-4 text-sm">
                @csrf
                <textarea class="min-h-28 w-full rounded-sm border border-slate-300 px-3 py-2" name="body" maxlength="3000" placeholder="写下评论" required></textarea>
                <button class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 font-medium text-white hover:bg-blue-800" type="submit">发布评论</button>
            </form>
        @else
            <div class="border-t border-slate-200 px-4 py-4 text-sm text-slate-600">
                <a class="text-blue-700 hover:text-blue-900" href="{{ route('login') }}">登录</a> 后可评论。
            </div>
        @endauth
    @else
        <p class="px-4 py-8 text-sm text-slate-600">评论已关闭。</p>
    @endif
</section>
