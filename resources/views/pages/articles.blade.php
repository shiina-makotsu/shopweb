<x-layouts.app title="文章">
    <section class="rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-lg font-semibold">文章</h1>
                    <p class="mt-1 text-sm text-slate-600">浏览站内使用文章模板发布的页面。</p>
                </div>
                <form method="get" action="{{ route('articles.index') }}" class="flex flex-wrap gap-2 text-sm">
                    <select class="rounded-sm border border-slate-300 bg-white px-3 py-2" name="sort">
                        <option value="latest" @selected($sort === 'latest')>最新文章</option>
                        <option value="views" @selected($sort === 'views')>阅读量</option>
                    </select>
                    <select class="rounded-sm border border-slate-300 bg-white px-3 py-2" name="direction">
                        <option value="desc" @selected($direction === 'desc')>倒序</option>
                        <option value="asc" @selected($direction === 'asc')>正序</option>
                    </select>
                    <button class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 font-medium text-white hover:bg-blue-800" type="submit">排序</button>
                </form>
            </div>
        </div>

        <div class="grid gap-4 bg-slate-50 p-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($articles as $article)
                <a class="block rounded-sm border border-slate-200 bg-white shadow-sm hover:border-blue-200 hover:bg-blue-50" href="{{ route('pages.show', $article) }}">
                    @if($cover = $article->coverMediaAsset)
                        <img class="h-40 w-full border-b border-slate-200 object-cover" src="{{ $cover->url() }}" alt="{{ $cover->alt ?: $article->title }}">
                    @endif
                    <div class="px-4 py-4">
                        <h2 class="font-semibold text-slate-950">{{ $article->title }}</h2>
                        @if($article->excerpt)
                            <p class="mt-2 line-clamp-3 text-sm leading-6 text-slate-600">{{ $article->excerpt }}</p>
                        @endif
                        <p class="mt-3 text-xs text-slate-500">
                            {{ $article->created_at?->format('Y-m-d') }} / {{ number_format((int) $article->views_count) }} 次阅读
                        </p>
                    </div>
                </a>
            @empty
                <p class="col-span-full px-4 py-8 text-sm text-slate-600">暂无文章。</p>
            @endforelse
        </div>

        <div class="border-t border-slate-200 px-4 py-3">
            {{ $articles->links('pagination::tailwind') }}
        </div>
    </section>
</x-layouts.app>
