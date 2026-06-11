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
    <div class="grid gap-px bg-slate-200 sm:grid-cols-2 xl:grid-cols-3">
        @forelse($resources as $resource)
            <article class="bg-white px-4 py-4">
                <a class="block overflow-hidden rounded-sm border border-slate-200 bg-slate-50" href="{{ $resource->url() }}" target="_blank" rel="noopener noreferrer">
                    @if($resource->isImage())
                        <img class="aspect-video w-full object-cover" src="{{ $resource->url() }}" alt="{{ $resource->alt ?: $resource->name }}">
                    @elseif($resource->isVideo())
                        <video class="aspect-video w-full bg-black object-contain" src="{{ $resource->url() }}" controls preload="metadata"></video>
                    @else
                        <div class="flex aspect-video items-center justify-center px-4 text-center text-sm text-slate-500">
                            {{ $resource->mime_type ?: '文件资源' }}
                        </div>
                    @endif
                </a>
                <div class="mt-3 min-w-0">
                    <h2 class="truncate text-sm font-semibold text-slate-900">{{ $resource->name }}</h2>
                    @if($resource->notes)
                        <p class="mt-1 line-clamp-3 text-sm leading-6 text-slate-600">{{ $resource->notes }}</p>
                    @endif
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                        <span>{{ \App\Models\MediaAsset::usageOptions()[$resource->usage] ?? $resource->usage }}</span>
                        <span>{{ $resource->sizeForHumans() }}</span>
                    </div>
                    <a class="mt-3 inline-flex rounded-sm border border-blue-700 bg-blue-700 px-3 py-2 text-xs font-medium text-white hover:bg-blue-800" href="{{ $resource->url() }}" target="_blank" rel="noopener noreferrer">
                        打开资源
                    </a>
                </div>
            </article>
        @empty
            <p class="col-span-full bg-white px-4 py-8 text-sm text-slate-600">暂无公开资源。</p>
        @endforelse
    </div>
    <div class="border-t border-slate-200 px-4 py-3">
        {{ $resources->links() }}
    </div>
</section>
