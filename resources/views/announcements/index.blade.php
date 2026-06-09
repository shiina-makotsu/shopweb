<x-layouts.app title="公告">
    <section class="rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
            <h1 class="text-lg font-semibold">公告</h1>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($announcements as $announcement)
                <a class="block px-4 py-4 hover:bg-blue-50" href="{{ route('announcements.show', $announcement) }}">
                    <div class="flex flex-wrap items-center gap-2">
                        @if($announcement->is_pinned)
                            <span class="rounded-sm border border-pink-200 bg-pink-50 px-2 py-1 text-xs text-pink-700">置顶</span>
                        @endif
                        @auth
                            @unless(in_array($announcement->id, $readIds, true))
                                <span class="rounded-sm border border-red-200 bg-red-50 px-2 py-1 text-xs text-red-700">未读</span>
                            @endunless
                        @endauth
                        <h2 class="text-base font-semibold">{{ $announcement->title }}</h2>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">{{ $announcement->published_at?->format('Y-m-d H:i') ?? $announcement->created_at->format('Y-m-d H:i') }}</p>
                </a>
            @empty
                <p class="px-4 py-8 text-sm text-slate-600">暂无公告。</p>
            @endforelse
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $announcements->links() }}</div>
    </section>
</x-layouts.app>
