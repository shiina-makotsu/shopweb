<x-layouts.app title="论坛">
    <section class="mb-4 rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-2">
            <h1 class="text-lg font-semibold">论坛</h1>
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
                                <p class="mt-2 text-xs text-slate-500">
                                    版主：
                                    {{ $section->moderators->map(fn ($user) => $user->displayName())->join('、') }}
                                </p>
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
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-2">
            <h2 class="text-base font-semibold">最近讨论</h2>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($latestThreads as $thread)
                <a class="block px-4 py-3 text-sm hover:bg-blue-50" href="{{ route('forum.threads.show', [$thread->section, $thread]) }}">
                    <div class="flex flex-wrap items-center gap-2">
                        @if($thread->is_pinned)
                            <span class="rounded-sm bg-pink-100 px-2 py-0.5 text-xs text-pink-700">置顶</span>
                        @endif
                        <span class="font-medium">{{ $thread->title }}</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ $thread->section->name }} / {{ $thread->user->displayName() }} / {{ $thread->created_at->format('Y-m-d H:i') }}</p>
                </a>
            @empty
                <p class="px-4 py-8 text-sm text-slate-600">暂无帖子。</p>
            @endforelse
        </div>
    </section>
</x-layouts.app>
