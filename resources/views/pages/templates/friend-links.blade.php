<section class="rounded-sm border border-slate-300 bg-white">
    <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
        <h1 class="text-lg font-semibold">{{ $page->title }}</h1>
        @if($page->excerpt)
            <p class="mt-1 text-sm text-slate-600">{{ $page->excerpt }}</p>
        @endif
    </div>
    @if($page->body)
        <div class="content-body border-b border-slate-200 px-4 py-4 text-sm">
            {{ \App\Support\Markdown::render($page->body) }}
        </div>
    @endif
    @include('pages.partials.blocks')
    <div class="grid gap-4 bg-slate-50 p-4 sm:grid-cols-2 md:grid-cols-3">
        @forelse($links as $link)
            <a class="block rounded-sm border border-slate-200 bg-white px-4 py-4 shadow-sm hover:border-blue-200 hover:bg-blue-50" href="{{ $link->url }}" target="_blank" rel="noopener noreferrer">
                <div class="flex gap-3">
                    <span class="{{ $link->image_path ? 'hidden' : 'flex' }} h-16 w-16 shrink-0 items-center justify-center rounded-sm border border-slate-200 bg-slate-100 text-xl font-semibold text-slate-500" data-friend-link-placeholder>{{ mb_substr($link->site_name, 0, 1) }}</span>
                    @if($link->image_path)
                        <img class="h-16 w-16 shrink-0 rounded-sm border border-slate-200 object-cover" src="{{ $link->imageUrl() }}" alt="{{ $link->site_name }}" onerror="this.classList.add('hidden'); this.previousElementSibling?.classList.remove('hidden'); this.previousElementSibling?.classList.add('flex');">
                    @endif
                    <div class="min-w-0">
                        <h2 class="truncate font-semibold text-slate-900">{{ $link->site_name }}</h2>
                        @if($link->description)
                            <p class="mt-1 line-clamp-3 text-sm leading-6 text-slate-600">{{ $link->description }}</p>
                        @endif
                        <p class="mt-2 truncate text-xs text-blue-700">{{ $link->url }}</p>
                    </div>
                </div>
            </a>
        @empty
            <p class="col-span-full bg-white px-4 py-8 text-sm text-slate-600">暂无友情链接。</p>
        @endforelse
    </div>
    <div class="border-t border-slate-200 px-4 py-3">
        {{ $links->links() }}
    </div>
</section>
