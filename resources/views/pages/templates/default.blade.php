<article class="rounded-sm border border-slate-300 bg-white">
    @if($cover = $page->coverMediaAsset)
        <img
            src="{{ $cover->url() }}"
            alt="{{ $cover->alt ?: $page->title }}"
            class="h-auto max-h-96 w-full border-b border-slate-200 object-cover"
        >
    @endif
    <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
        <h1 class="text-lg font-semibold">{{ $page->title }}</h1>
        @if($page->excerpt)
            <p class="mt-1 text-sm text-slate-600">{{ $page->excerpt }}</p>
        @endif
    </div>
    <div class="content-body px-4 py-4 text-sm">
        {{ \App\Support\Markdown::render($page->body) }}
    </div>
    @include('pages.partials.blocks')
</article>
