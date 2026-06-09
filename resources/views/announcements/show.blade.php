<x-layouts.app :title="$announcement->title">
    <article class="rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
            <div class="flex flex-wrap items-center gap-2">
                @if($announcement->is_pinned)
                    <span class="rounded-sm border border-pink-200 bg-pink-50 px-2 py-1 text-xs text-pink-700">置顶</span>
                @endif
                <h1 class="text-lg font-semibold">{{ $announcement->title }}</h1>
            </div>
            <p class="mt-1 text-xs text-slate-500">{{ $announcement->published_at?->format('Y-m-d H:i') ?? $announcement->created_at->format('Y-m-d H:i') }}</p>
        </div>
        <div class="content-body px-4 py-4 text-sm text-slate-700">
            {{ \App\Support\Markdown::render($announcement->body) }}
        </div>
        @if($announcement->comments_enabled)
            <div class="border-t border-slate-200 px-4 py-4 text-sm text-slate-600">
                公告评论功能已预留，后续会与站内评论体系统一。
            </div>
        @endif
    </article>
</x-layouts.app>
