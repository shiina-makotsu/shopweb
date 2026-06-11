<article class="mx-auto max-w-3xl rounded-sm border border-slate-300 bg-white">
    @if($cover = $page->coverMediaAsset)
        <img
            src="{{ $cover->url() }}"
            alt="{{ $cover->alt ?: $page->title }}"
            class="h-auto max-h-[28rem] w-full border-b border-slate-200 object-cover"
        >
    @endif

    <header class="border-b border-slate-200 px-5 py-6 sm:px-8">
        <p class="text-xs font-medium text-blue-700">{{ \App\Support\PageTemplate::label($page->template) }}</p>
        <h1 class="mt-2 text-2xl font-semibold leading-tight text-slate-950">{{ $page->title }}</h1>
        @if($page->excerpt)
            <p class="mt-3 text-sm leading-6 text-slate-600">{{ $page->excerpt }}</p>
        @endif
        <p class="mt-3 text-xs text-slate-500">{{ $page->updated_at?->format('Y-m-d H:i') }}</p>
    </header>

    <div class="content-body px-5 py-6 text-sm leading-7 sm:px-8">
        {{ \App\Support\Markdown::render($page->body) }}
    </div>
</article>
