@php
    $assetUrl = fn (string $path): string => \Illuminate\Support\Facades\Storage::disk('public_uploads')->url($path);
    $isImage = fn (string $path): bool => preg_match('/\.(jpe?g|png|gif|webp)$/i', $path) === 1;
    $isVideo = fn (string $path): bool => preg_match('/\.(mp4|webm|mov)$/i', $path) === 1;
    $userName = fn ($user): string => $user?->displayName() ?? '已注销用户';
    $profileUrl = fn ($user): ?string => $user ? \App\Support\Url::route('users.show', $user) : null;
@endphp

<x-layouts.app :title="$thread->title">
    <section class="mb-4 rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-2 text-sm text-slate-600">
            <a class="hover:text-blue-800" href="{{ route('forum.index') }}">论坛</a>
            <span class="mx-1">/</span>
            <a class="hover:text-blue-800" href="{{ route('forum.sections.show', $section) }}">{{ $section->name }}</a>
        </div>
        <article class="px-4 py-4">
            <div class="flex flex-wrap items-center gap-2">
                @if($thread->is_pinned)
                    <span class="rounded-sm bg-pink-100 px-2 py-0.5 text-xs text-pink-700">置顶</span>
                @endif
                @if($thread->is_featured)
                    <span class="rounded-sm bg-blue-100 px-2 py-0.5 text-xs text-blue-700">星标</span>
                @endif
                @if($thread->is_locked)
                    <span class="rounded-sm bg-slate-200 px-2 py-0.5 text-xs text-slate-700">锁定</span>
                @endif
                <h1 class="text-xl font-semibold">{{ $thread->title }}</h1>
            </div>
            <p class="mt-2 text-xs text-slate-500">
                贴主
                @if($profileUrl($thread->user))
                    <a class="hover:text-blue-800" href="{{ $profileUrl($thread->user) }}">{{ $userName($thread->user) }}</a>
                @else
                    <span>{{ $userName($thread->user) }}</span>
                @endif
                / {{ $thread->created_at->format('Y-m-d H:i') }}
                / {{ $thread->views_count }} 访问
                / {{ $thread->comments->count() }} 回复
                / {{ $thread->likes_count }} 点赞
                @if($thread->edited_at)
                    / 已编辑 {{ $thread->edited_at->format('Y-m-d H:i') }}
                @endif
            </p>
            <div class="mt-4 whitespace-pre-line text-sm leading-7 text-slate-700">{{ $thread->body }}</div>
            @if($thread->is_locked)
                <div class="mt-4 rounded-sm border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">该帖子已锁定，不能继续回复。</div>
            @endif

            @if($thread->attachment_paths)
                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    @foreach($thread->attachment_paths as $path)
                        <a class="rounded-sm border border-slate-200 bg-slate-50 px-3 py-2 text-xs hover:bg-blue-50" href="{{ $assetUrl($path) }}" download>
                            @if($isImage($path))
                                <img class="mb-2 h-28 w-full rounded-sm object-cover" src="{{ $assetUrl($path) }}" alt="论坛附件">
                            @elseif($isVideo($path))
                                <video class="mb-2 h-28 w-full rounded-sm bg-black object-contain" src="{{ $assetUrl($path) }}" controls preload="metadata"></video>
                            @endif
                            下载附件：{{ basename($path) }}
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                @auth
                    <form method="post" action="{{ route('forum.threads.like', [$section, $thread]) }}">
                        @csrf
                        <button class="rounded-sm border border-slate-300 px-3 py-1 hover:bg-slate-50" type="submit">点赞 {{ $thread->likes_count }}</button>
                    </form>
                    <form method="post" action="{{ route('forum.threads.share', [$section, $thread]) }}">
                        @csrf
                        <button class="rounded-sm border border-slate-300 px-3 py-1 hover:bg-slate-50" type="submit">转发 {{ $thread->shares_count }}</button>
                    </form>
                    @if(auth()->user()->isForumModeratorFor($section))
                        <form method="post" action="{{ route('forum.threads.pin', [$section, $thread]) }}">
                            @csrf
                            <button class="rounded-sm border border-slate-300 px-3 py-1 hover:bg-slate-50" type="submit">{{ $thread->is_pinned ? '取消置顶' : '置顶' }}</button>
                        </form>
                    @endif
                    @if($canManageThread)
                        <form method="post" action="{{ route('forum.threads.destroy', [$section, $thread]) }}" onsubmit="return confirm('确定删除该帖子吗？')">
                            @csrf
                            @method('DELETE')
                            <button class="rounded-sm border border-red-300 px-3 py-1 text-red-700 hover:bg-red-50" type="submit">删除帖子</button>
                        </form>
                    @endif
                @else
                    <span class="text-slate-500">登录后可点赞、转发和回复。</span>
                @endauth
            </div>
        </article>

        @auth
            @if($canManageThread)
                <form method="post" action="{{ route('forum.threads.update', [$section, $thread]) }}" class="space-y-3 border-t border-slate-200 px-4 py-4 text-sm">
                    @csrf
                    @method('PATCH')
                    <h2 class="text-sm font-semibold">管理帖子</h2>
                    <input class="w-full rounded-sm border border-slate-300 px-3 py-2" name="title" maxlength="120" value="{{ old('title', $thread->title) }}" required>
                    <textarea class="min-h-28 w-full rounded-sm border border-slate-300 px-3 py-2" name="body" maxlength="12000" required>{{ old('body', $thread->body) }}</textarea>
                    <button class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 font-medium text-white hover:bg-blue-800" type="submit">保存帖子</button>
                </form>
            @endif
        @endauth
    </section>

    <section class="rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-2">
            <h2 class="text-base font-semibold">回复</h2>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($thread->comments as $comment)
                <article class="px-4 py-4 text-sm">
                    <div class="flex flex-wrap items-center gap-2">
                        @if($profileUrl($comment->user))
                            <a class="font-medium hover:text-blue-800" href="{{ $profileUrl($comment->user) }}">{{ $userName($comment->user) }}</a>
                        @else
                            <span class="font-medium">{{ $userName($comment->user) }}</span>
                        @endif
                        <span class="text-xs text-slate-500">{{ $comment->created_at->format('Y-m-d H:i') }}</span>
                        @if($comment->edited_at)
                            <span class="text-xs text-slate-400">已编辑</span>
                        @endif
                        @auth
                            @if($comment->user && auth()->id() !== $comment->user_id)
                                <a class="text-xs text-blue-700 hover:text-blue-900" href="{{ route('messages.thread', $comment->user) }}">私聊</a>
                            @endif
                        @endauth
                    </div>
                    <p class="mt-2 whitespace-pre-line text-slate-700">{{ $comment->body }}</p>

                    @if($comment->attachment_paths)
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            @foreach($comment->attachment_paths as $path)
                                <a class="rounded-sm border border-slate-200 bg-slate-50 px-3 py-2 text-xs hover:bg-blue-50" href="{{ $assetUrl($path) }}" download>
                                    @if($isImage($path))
                                        <img class="mb-2 h-24 w-full rounded-sm object-cover" src="{{ $assetUrl($path) }}" alt="回复附件">
                                    @elseif($isVideo($path))
                                        <video class="mb-2 h-24 w-full rounded-sm bg-black object-contain" src="{{ $assetUrl($path) }}" controls preload="metadata"></video>
                                    @endif
                                    下载附件：{{ basename($path) }}
                                </a>
                            @endforeach
                        </div>
                    @endif

                    @auth
                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                            <form method="post" action="{{ route('forum.comments.like', [$section, $thread, $comment]) }}">
                                @csrf
                                <button class="rounded-sm border border-slate-300 px-3 py-1 hover:bg-slate-50" type="submit">点赞 {{ $comment->likes_count }}</button>
                            </form>
                            @if($comment->canBeManagedBy(auth()->user()))
                                <form method="post" action="{{ route('forum.comments.destroy', [$section, $thread, $comment]) }}" onsubmit="return confirm('确定删除该回复吗？')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-sm border border-red-300 px-3 py-1 text-red-700 hover:bg-red-50" type="submit">删除回复</button>
                                </form>
                            @endif
                        </div>

                        @if($comment->canBeManagedBy(auth()->user()))
                            <form method="post" action="{{ route('forum.comments.update', [$section, $thread, $comment]) }}" class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
                                @csrf
                                @method('PATCH')
                                <input class="rounded-sm border border-slate-300 px-3 py-2 text-xs" name="body" maxlength="6000" value="{{ $comment->body }}" required>
                                <button class="rounded-sm border border-slate-300 px-3 py-2 text-xs hover:bg-slate-50" type="submit">保存回复</button>
                            </form>
                        @endif

                        @if($thread->canReceiveReplies() && $section->canBePostedBy(auth()->user()))
                            <form method="post" action="{{ route('forum.comments.store', [$section, $thread]) }}" enctype="multipart/form-data" class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
                                @csrf
                                <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                                <input class="rounded-sm border border-slate-300 px-3 py-2 text-xs" name="body" maxlength="6000" placeholder="回复该评论" required>
                                <button class="rounded-sm border border-slate-300 px-3 py-2 text-xs hover:bg-slate-50" type="submit">回复</button>
                            </form>
                        @endif
                    @endauth

                    @foreach($comment->replies as $reply)
                        <div class="mt-3 rounded-sm border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-xs text-slate-500">
                                @if($profileUrl($reply->user))
                                    <a class="font-medium text-slate-700 hover:text-blue-800" href="{{ $profileUrl($reply->user) }}">{{ $userName($reply->user) }}</a>
                                @else
                                    <span class="font-medium text-slate-700">{{ $userName($reply->user) }}</span>
                                @endif
                                / {{ $reply->created_at->format('Y-m-d H:i') }}
                            </p>
                            <p class="mt-1 whitespace-pre-line">{{ $reply->body }}</p>
                        </div>
                    @endforeach
                </article>
            @empty
                <p class="px-4 py-8 text-sm text-slate-600">暂无回复。</p>
            @endforelse
        </div>
        @auth
            @if($thread->canReceiveReplies() && $section->canBePostedBy(auth()->user()))
            <form method="post" action="{{ route('forum.comments.store', [$section, $thread]) }}" enctype="multipart/form-data" class="space-y-3 border-t border-slate-200 px-4 py-4 text-sm">
                @csrf
                <textarea class="min-h-28 w-full rounded-sm border border-slate-300 px-3 py-2" name="body" maxlength="6000" placeholder="写下回复" required></textarea>
                <label class="block">
                    <span class="text-xs font-medium text-slate-600">图片/文件附件</span>
                    <input class="mt-1 block w-full rounded-sm border border-slate-300 px-3 py-2 text-xs" type="file" name="attachments[]" multiple>
                </label>
                <button class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 font-medium text-white hover:bg-blue-800" type="submit">发布回复</button>
            </form>
            @else
                <div class="border-t border-slate-200 px-4 py-4 text-sm text-slate-600">当前帖子或版块不允许继续回复。</div>
            @endif
        @else
            <div class="border-t border-slate-200 px-4 py-4 text-sm text-slate-600">
                <a class="text-blue-700 hover:text-blue-900" href="{{ route('login') }}">登录</a> 后可回复。
            </div>
        @endauth
    </section>
</x-layouts.app>
