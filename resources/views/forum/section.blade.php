<x-layouts.app :title="$section->name">
    <section class="mb-4 rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-2 text-sm text-slate-600">
            <a class="hover:text-blue-800" href="{{ route('forum.index') }}">论坛</a>
            <span class="mx-1">/</span>
            <span>{{ $section->name }}</span>
        </div>
        <div class="px-4 py-4">
            <h1 class="text-xl font-semibold">{{ $section->name }}</h1>
            @if($section->description)
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $section->description }}</p>
            @endif
            @if($section->moderators->isNotEmpty())
                <p class="mt-2 text-xs text-slate-500">
                    版主：{{ $section->moderators->map(fn ($user) => $user->displayName())->join('、') }}
                </p>
            @endif
        </div>
    </section>

    @auth
        <section class="mb-4 rounded-sm border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-100 px-4 py-2">
                <h2 class="text-base font-semibold">发布新帖</h2>
            </div>
            <form method="post" action="{{ route('forum.threads.store', $section) }}" enctype="multipart/form-data" class="space-y-3 px-4 py-4 text-sm">
                @csrf
                <input class="w-full rounded-sm border border-slate-300 px-3 py-2" name="title" maxlength="120" placeholder="标题" required>
                <textarea class="min-h-32 w-full rounded-sm border border-slate-300 px-3 py-2" name="body" maxlength="12000" placeholder="内容" required></textarea>
                <label class="block">
                    <span class="text-xs font-medium text-slate-600">图片/文件附件</span>
                    <input class="mt-1 block w-full rounded-sm border border-slate-300 px-3 py-2 text-xs" type="file" name="attachments[]" multiple>
                </label>
                <button class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 font-medium text-white hover:bg-blue-800" type="submit">发布</button>
            </form>
        </section>
    @else
        <div class="mb-4 rounded-sm border border-slate-300 bg-white px-4 py-3 text-sm text-slate-600">
            <a class="text-blue-700 hover:text-blue-900" href="{{ route('login') }}">登录</a> 后可发布帖子。
        </div>
    @endauth

    <section class="rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-2">
            <h2 class="text-base font-semibold">帖子</h2>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($threads as $thread)
                <a class="block px-4 py-3 text-sm hover:bg-blue-50" href="{{ route('forum.threads.show', [$section, $thread]) }}">
                    <div class="flex flex-wrap items-center gap-2">
                        @if($thread->is_pinned)
                            <span class="rounded-sm bg-pink-100 px-2 py-0.5 text-xs text-pink-700">置顶</span>
                        @endif
                        <span class="font-medium">{{ $thread->title }}</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        贴主 {{ $thread->user->displayName() }} / {{ $thread->comments_count }} 回复 / {{ $thread->likes_count }} 点赞 / {{ $thread->shares_count }} 转发 / {{ $thread->created_at->format('Y-m-d H:i') }}
                    </p>
                </a>
            @empty
                <p class="px-4 py-8 text-sm text-slate-600">暂无帖子。</p>
            @endforelse
        </div>
        <div class="border-t border-slate-200 px-4 py-3">
            {{ $threads->links() }}
        </div>
    </section>
</x-layouts.app>
