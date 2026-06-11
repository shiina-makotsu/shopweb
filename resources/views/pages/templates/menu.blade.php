<section class="space-y-4">
    <article class="rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
            <h1 class="text-lg font-semibold">{{ $page->title }}</h1>
            @if($page->excerpt)
                <p class="mt-1 text-sm text-slate-600">{{ $page->excerpt }}</p>
            @endif
        </div>
        @if($page->body)
            <div class="content-body px-4 py-4 text-sm">
                {{ \App\Support\Markdown::render($page->body) }}
            </div>
        @endif
    </article>

    <section class="rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
            <h2 class="text-base font-semibold">站点菜单</h2>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($menuItems as $item)
                <div class="px-4 py-3 text-sm">
                    <a class="font-medium hover:text-blue-800" href="{{ $item->resolvedUrl() }}">{{ $item->label }}</a>
                    @if($item->children->isNotEmpty())
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach($item->children as $child)
                                <a class="rounded-sm border border-slate-300 px-3 py-1 text-xs hover:bg-blue-50 hover:text-blue-800" href="{{ $child->resolvedUrl() }}">{{ $child->label }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <p class="px-4 py-8 text-sm text-slate-600">暂无菜单项。</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
            <h2 class="text-base font-semibold">已发布页面</h2>
        </div>
        <div class="grid gap-px bg-slate-200 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($publishedPages as $publishedPage)
                <a class="block bg-white px-4 py-4 text-sm hover:bg-blue-50" href="{{ \App\Support\Url::route('pages.show', $publishedPage) }}">
                    <h3 class="font-medium text-slate-900">{{ $publishedPage->title }}</h3>
                    @if($publishedPage->excerpt)
                        <p class="mt-1 line-clamp-3 leading-6 text-slate-600">{{ $publishedPage->excerpt }}</p>
                    @endif
                </a>
            @empty
                <p class="col-span-full bg-white px-4 py-8 text-sm text-slate-600">暂无已发布页面。</p>
            @endforelse
        </div>
    </section>
</section>
