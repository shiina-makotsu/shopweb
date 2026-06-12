@php
    $userName = fn ($user): string => $user?->displayName() ?? '已注销用户';
@endphp

<x-layouts.app title="论坛">
    <section class="mb-4 rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-lg font-semibold">论坛</h1>
                    <p class="mt-1 text-xs text-slate-600">浏览版块与全站帖子，搜索标题或正文。</p>
                </div>
                <form method="get" action="{{ route('forum.index') }}" class="flex min-w-0 flex-1 justify-end gap-2 sm:max-w-xl">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input class="min-w-0 flex-1 rounded-sm border border-slate-300 px-3 py-2 text-sm" type="search" name="q" value="{{ $search }}" placeholder="搜索帖子">
                    <button class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800" type="submit">搜索</button>
                </form>
            </div>
        </div>
        <div class="grid gap-px bg-slate-200 md:grid-cols-2">
            @forelse($sections as $section)
                <a class="block bg-white px-4 py-4 hover:bg-blue-50" href="{{ route('forum.sections.show', $section) }}">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="font-semibold text-slate-900">{{ $section->name }}</h2>
                            @if($section->description)
                                <p class="mt-1 text-sm leading-6 text-slate-600">{{ $section->description }}</p>
                            @endif
                            @if($section->moderators->isNotEmpty())
                                <p class="mt-2 text-xs text-slate-500">版主：{{ $section->moderators->map(fn ($user) => $user->displayName())->join('、') }}</p>
                            @endif
                        </div>
                        <span class="rounded-sm border border-slate-300 bg-white px-2 py-1 text-xs text-slate-600">{{ $section->threads_count }} 帖</span>
                    </div>
                </a>
            @empty
                <p class="bg-white px-4 py-8 text-sm text-slate-600">暂无论坛版块。</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-base font-semibold">全站帖子</h2>
                <nav class="flex flex-wrap gap-2 text-xs">
                    @foreach($sortOptions as $key => $label)
                        <a class="rounded-sm border px-3 py-1.5 {{ $sort === $key ? 'border-blue-700 bg-blue-700 text-white' : 'border-slate-300 bg-white hover:bg-blue-50' }}" href="{{ route('forum.index', array_filter(['sort' => $key, 'q' => $search])) }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </nav>
            </div>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($threads as $thread)
                <a class="block px-4 py-3 text-sm hover:bg-blue-50" href="{{ route('forum.threads.show', [$thread->section, $thread]) }}">
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
                        {{ $thread->section?->name ?? '未知版块' }} / {{ $userName($thread->user) }} /
                        {{ $thread->comments_count }} 回复 / {{ $thread->likes_count }} 点赞 / {{ $thread->views_count }} 访问 /
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
        <a class="fixed bottom-20 right-5 z-40 inline-flex rounded-sm border border-blue-700 bg-blue-700 px-4 py-3 text-sm font-medium text-white shadow hover:bg-blue-800" href="{{ route('forum.threads.create') }}">
            发布新帖
        </a>
    @else
        <a class="fixed bottom-20 right-5 z-40 inline-flex rounded-sm border border-blue-700 bg-white px-4 py-3 text-sm font-medium text-blue-800 shadow hover:bg-blue-50" href="{{ route('login') }}">
            登录发帖
        </a>
    @endauth
</x-layouts.app>
