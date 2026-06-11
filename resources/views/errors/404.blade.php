@php
    $siteSettings = null;
    $customPage = null;

    try {
        $siteSettings = \App\Models\SiteSetting::query()->first();
        $customPage = \App\Models\Page::query()
            ->with('coverMediaAsset')
            ->published()
            ->where('slug', '404')
            ->first()
            ?: \App\Models\Page::query()
                ->with('coverMediaAsset')
                ->published()
                ->where('template', \App\Support\PageTemplate::NOT_FOUND)
                ->orderBy('sort_order')
                ->first();
    } catch (\Throwable) {
        $customPage = null;
    }

    $title = $customPage?->title ?: '页面不存在';
    $description = $customPage?->seo_description ?: $customPage?->excerpt ?: '你访问的页面不存在或已经被移动。';
    $cover = $customPage?->coverMediaAsset;
@endphp

<x-layouts.app :title="$title" :description="$description" :wide="true" :settings="$siteSettings">
    <article class="mx-auto max-w-3xl overflow-hidden rounded-sm border border-slate-300 bg-white">
        @if($cover?->isImage())
            <img
                src="{{ $cover->url() }}"
                alt="{{ $cover->alt ?: $title }}"
                class="h-auto max-h-80 w-full border-b border-slate-200 object-cover"
            >
        @endif

        <div class="space-y-4 px-5 py-8 text-center sm:px-8">
            <p class="text-sm font-semibold uppercase tracking-normal text-blue-700">404</p>
            <h1 class="text-2xl font-semibold text-slate-950">{{ $title }}</h1>

            @if($customPage?->body)
                <div class="content-body mx-auto max-w-2xl text-left text-sm text-slate-700">
                    {{ \App\Support\Markdown::render($customPage->body) }}
                </div>
            @else
                <p class="mx-auto max-w-xl text-sm leading-6 text-slate-600">
                    {{ $description }}
                </p>
            @endif

            <a
                href="{{ \App\Support\Url::route('home') }}"
                class="inline-flex rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800"
            >
                回到首页
            </a>
        </div>
    </article>
</x-layouts.app>
