<x-layouts.app title="标签">
    <section class="rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
            <h1 class="text-lg font-semibold">标签</h1>
            <p class="mt-1 text-sm text-slate-600">浏览站内商品标签，点击标签查看对应商品。</p>
        </div>

        <div class="grid gap-px bg-slate-200 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @forelse($tags as $tag)
                <a class="block bg-white px-4 py-4 hover:bg-blue-50" href="{{ route('tags.show', $tag) }}">
                    <span class="block text-base font-semibold text-blue-900"># {{ $tag->name }}</span>
                    @if($tag->meta_description)
                        <span class="mt-2 line-clamp-2 block text-sm leading-6 text-slate-600">{{ $tag->meta_description }}</span>
                    @endif
                    <span class="mt-3 inline-flex rounded-sm border border-slate-300 bg-slate-50 px-2 py-1 text-xs text-slate-600">
                        {{ $tag->products_count }} 件商品
                    </span>
                </a>
            @empty
                <div class="col-span-full bg-white px-4 py-8 text-sm text-slate-600">暂无标签。</div>
            @endforelse
        </div>
    </section>
</x-layouts.app>
