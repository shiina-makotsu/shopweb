<article class="mx-auto max-w-4xl overflow-hidden rounded-sm border border-slate-300 bg-white">
    @if($cover = $page->coverMediaAsset)
        <img
            src="{{ $cover->url() }}"
            alt="{{ $cover->alt ?: $page->title }}"
            class="h-auto max-h-[26rem] w-full border-b border-slate-200 object-cover"
        >
    @endif

    <header class="border-b border-slate-200 bg-slate-50 px-5 py-7 sm:px-8">
        <p class="text-xs font-medium uppercase tracking-normal text-blue-700">About</p>
        <h1 class="mt-2 text-2xl font-semibold leading-tight text-slate-950">{{ $page->title }}</h1>
        @if($page->excerpt)
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">{{ $page->excerpt }}</p>
        @endif
    </header>

    <div class="content-body px-5 py-6 text-sm leading-7 sm:px-8">
        {{ \App\Support\Markdown::render($page->body) }}
    </div>

    @include('pages.partials.blocks', ['class' => 'border-t border-slate-200 px-5 py-6 sm:px-8'])

    <footer class="border-t border-slate-200 bg-slate-50 px-5 py-4 text-xs leading-6 text-slate-600 sm:px-8">
        <p>转载许可：除页面另有说明外，本页面原创文字采用 CC BY 4.0 许可协议。</p>
    </footer>
</article>
