@php
    $userName = fn ($user): string => $user?->displayName() ?? '已注销用户';
@endphp

<x-layouts.app :title="$section->name">
    <section class="mb-4 rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-2 text-sm text-slate-600">
            <a class="hover:text-blue-800" href="{{ route('forum.index') }}">论坛</a>
            <span class="mx-1">/</span>
            <span>{{ $section->name }}</span>
        </div>
        <div class="px-4 py-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">{{ $section->name }}</h1>
                    @if($section->description)
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ $section->description }}</p>
                    @endif
                    @if($section->moderators->isNotEmpty())
                        <p class="mt-2 text-xs text-slate-500">版主：{{ $section->moderators->map(fn ($user) => $user->displayName())->join('、') }}</p>
                    @endif
                </div>
                <span class="rounded-sm border border-slate-300 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                    发帖权限：{{ \App\Models\ForumSection::postingPolicyOptions()[$section->posting_policy] ?? '所有登录用户' }}
                </span>
            </div>
        </div>
    </section>

    @if($pinnedThreads->isNotEmpty())
        <section class="mb-4 rounded-sm border border-pink-200 bg-white">
            <h2 class="border-b border-pink-100 bg-pink-50 px-4 py-2 text-sm font-semibold text-pink-950">置顶帖</h2>
            <div class="divide-y divide-slate-100">
                @foreach($pinnedThreads as $thread)
                    <a class="block px-4 py-3 text-sm hover:bg-pink-50" href="{{ route('forum.threads.show', [$section, $thread]) }}">
                        <p class="font-medium">{{ $thread->title }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $userName($thread->user) }} / {{ $thread->comments_count }} 回复 / {{ $thread->likes_count }} 点赞 / {{ $thread->views_count }} 访问</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-base font-semibold">帖子</h2>
                <form method="get" action="{{ route('forum.sections.show', $section) }}" class="flex min-w-0 flex-1 justify-end gap-2 sm:max-w-xl">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input class="min-w-0 flex-1 rounded-sm border border-slate-300 px-3 py-2 text-sm" type="search" name="q" value="{{ $search }}" placeholder="搜索本版块帖子">
                    <button class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800" type="submit">搜索</button>
                </form>
            </div>
            <nav class="mt-3 flex flex-wrap gap-2 text-xs">
                @foreach($sortOptions as $key => $label)
                    <a class="rounded-sm border px-3 py-1.5 {{ $sort === $key ? 'border-blue-700 bg-blue-700 text-white' : 'border-slate-300 bg-white hover:bg-blue-50' }}" href="{{ route('forum.sections.show', [$section, 'sort' => $key, 'q' => $search]) }}">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($threads as $thread)
                <a class="block px-4 py-3 text-sm hover:bg-blue-50" href="{{ route('forum.threads.show', [$section, $thread]) }}">
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
                        <span class="font-medium">{{ $thread->title }}</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        贴主 {{ $userName($thread->user) }} / {{ $thread->comments_count }} 回复 /
                        {{ $thread->likes_count }} 点赞 / {{ $thread->views_count }} 访问 /
                        {{ $thread->created_at->format('Y-m-d H:i') }}
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

    @auth
        @if($section->canBePostedBy(auth()->user()))
            <a class="fixed bottom-20 right-5 z-40 inline-flex rounded-sm border border-blue-700 bg-blue-700 px-4 py-3 text-sm font-medium text-white shadow hover:bg-blue-800" href="{{ route('forum.sections.threads.create', $section) }}">
                发布新帖
            </a>
        @endif
    @else
        <a class="fixed bottom-20 right-5 z-40 inline-flex rounded-sm border border-blue-700 bg-white px-4 py-3 text-sm font-medium text-blue-800 shadow hover:bg-blue-50" href="{{ route('login') }}">
            登录发帖
        </a>
    @endauth
</x-layouts.app>
