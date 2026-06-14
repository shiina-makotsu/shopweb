@php
    $articleContent = \App\Support\ArticleContent::render($page->body);
    $rewardQrUrl = \App\Support\MediaPath::url($page->reward_qr_path);
@endphp

<div class="mx-auto max-w-6xl">
    <article class="rounded-sm border border-slate-300 bg-white">
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
            <p class="mt-3 text-xs text-slate-500">
                {{ $page->updated_at?->format('Y-m-d H:i') }} / {{ number_format((int) $page->views_count) }} 次阅读
            </p>
        </header>

        <div class="grid gap-6 px-5 py-6 sm:px-8 lg:grid-cols-[minmax(0,1fr)_16rem]">
            <div class="min-w-0">
                <div class="content-body text-sm leading-7">
                    {{ $articleContent['html'] }}
                </div>
                @include('pages.partials.blocks', ['class' => 'mt-6 border-t border-slate-200 pt-6'])

                @if($rewardQrUrl)
                    <section class="mt-6 rounded-sm border border-amber-200 bg-amber-50 px-4 py-4">
                        <h2 class="text-sm font-semibold text-amber-950">赞赏</h2>
                        <p class="mt-1 text-xs text-amber-800">如果这篇文章对你有帮助，可以使用下方赞赏码支持作者。</p>
                        <img class="mt-3 h-36 w-36 rounded-sm border border-amber-200 bg-white object-cover" src="{{ $rewardQrUrl }}" alt="赞赏码">
                    </section>
                @endif
            </div>

            <aside class="order-first lg:order-none">
                <div class="sticky top-20 rounded-sm border border-slate-200 bg-slate-50 px-4 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">页面目录</h2>
                    @if($articleContent['toc'])
                        <nav class="mt-3 space-y-1 text-sm">
                            @foreach($articleContent['toc'] as $item)
                                <a
                                    class="block truncate text-slate-600 hover:text-blue-800 {{ $item['level'] >= 3 ? 'pl-4 text-xs' : '' }} {{ $item['level'] >= 4 ? 'pl-7' : '' }}"
                                    href="#{{ $item['id'] }}"
                                >
                                    {{ $item['title'] }}
                                </a>
                            @endforeach
                        </nav>
                    @else
                        <p class="mt-2 text-xs leading-5 text-slate-500">正文中使用 Markdown 标题后会自动生成目录。</p>
                    @endif
                </div>
            </aside>
        </div>
    </article>

    @include('pages.partials.comments', [
        'comments' => $page->relationLoaded('topLevelComments') ? $page->topLevelComments : collect(),
        'enabled' => $page->comments_enabled,
        'action' => route('page-comments.store', $page),
    ])
</div>
