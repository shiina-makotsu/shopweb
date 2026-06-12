@php
    $pageTitle = match (true) {
        isset($currentTag) && $currentTag => '标签：'.$currentTag->name,
        $discountOnly ?? false => '折扣商品',
        $featuredOnly ?? false => '推荐商品',
        ($currentStatus ?? null) === \App\Models\Product::STATUS_CONCEPT => '概念商品',
        ($currentStatus ?? null) === \App\Models\Product::STATUS_PRESALE => '预售商品',
        ($currentStatus ?? null) === \App\Models\Product::STATUS_INCOMING => '进货中商品',
        ($currentStatus ?? null) === \App\Models\Product::STATUS_PUBLISHED => '现货商品',
        ($currentStatus ?? null) === \App\Models\Product::STATUS_SOLD_OUT => '售罄商品',
        default => '商品列表',
    };
@endphp

<x-layouts.app :title="$pageTitle">
    <section class="rounded-sm border border-slate-300 bg-white">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-100 px-4 py-3">
            <div>
                <h1 class="text-lg font-semibold">{{ $pageTitle }}</h1>
                @if(($currentCategory ?? null) || ($currentTag ?? null) || ($discountOnly ?? false) || ($featuredOnly ?? false) || ($currentStatus ?? null) || $keyword)
                    <p class="mt-1 text-xs text-slate-600">
                        @if($currentCategory ?? null)
                            分类：{{ $currentCategory->name }}
                        @endif
                        @if($currentTag ?? null)
                            标签：{{ $currentTag->name }}
                        @endif
                        @if($featuredOnly ?? false)
                            推荐商品
                        @endif
                        @if($discountOnly ?? false)
                            折扣商品
                        @endif
                        @if(($currentStatus ?? null) && array_key_exists($currentStatus, \App\Models\Product::statusOptions()))
                            状态：{{ \App\Models\Product::statusOptions()[$currentStatus] }}
                        @endif
                        @if($keyword)
                            搜索：{{ $keyword }}
                        @endif
                    </p>
                @endif
            </div>
            <form method="get" action="{{ isset($currentTag) && $currentTag ? route('tags.show', $currentTag) : route('products.index') }}" class="grid w-full max-w-md gap-2 sm:grid-cols-[1fr_auto]">
                @if($currentCategory)
                    <input type="hidden" name="category" value="{{ $currentCategory->slug }}">
                @endif
                @if($featuredOnly ?? false)
                    <input type="hidden" name="featured" value="1">
                @endif
                @if($discountOnly ?? false)
                    <input type="hidden" name="discount" value="1">
                @endif
                @if($currentStatus ?? null)
                    <input type="hidden" name="status" value="{{ $currentStatus }}">
                @endif
                <input class="min-w-0 rounded-sm border border-slate-300 px-3 py-2 text-sm sm:rounded-r-none sm:border-r-0" name="q" value="{{ $keyword }}" placeholder="在商品中搜索">
                <button class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 sm:rounded-l-none" type="submit">搜索</button>
            </form>
        </div>

        <div class="border-b border-slate-200 px-4 py-3">
            <div class="flex flex-wrap gap-2">
                <a class="rounded-sm border px-3 py-2 text-sm {{ ($currentCategory ?? null) || ($featuredOnly ?? false) || ($discountOnly ?? false) || ($currentStatus ?? null) ? 'border-slate-300 bg-white hover:bg-blue-50' : 'border-blue-700 bg-blue-700 text-white' }}" href="{{ route('products.index', $keyword ? ['q' => $keyword] : []) }}">全部</a>
                <a class="rounded-sm border px-3 py-2 text-sm {{ ($featuredOnly ?? false) ? 'border-blue-700 bg-blue-700 text-white' : 'border-slate-300 bg-white hover:bg-blue-50' }}" href="{{ route('products.index', array_filter(['featured' => 1, 'q' => $keyword])) }}">推荐商品</a>
                <a class="rounded-sm border px-3 py-2 text-sm {{ ($discountOnly ?? false) ? 'border-blue-700 bg-blue-700 text-white' : 'border-slate-300 bg-white hover:bg-blue-50' }}" href="{{ route('products.index', array_filter(['discount' => 1, 'q' => $keyword])) }}">折扣商品</a>
                <a class="rounded-sm border px-3 py-2 text-sm {{ ($currentStatus ?? null) === \App\Models\Product::STATUS_PRESALE ? 'border-blue-700 bg-blue-700 text-white' : 'border-slate-300 bg-white hover:bg-blue-50' }}" href="{{ route('products.index', array_filter(['status' => \App\Models\Product::STATUS_PRESALE, 'q' => $keyword])) }}">预售商品</a>
                <a class="rounded-sm border px-3 py-2 text-sm {{ ($currentStatus ?? null) === \App\Models\Product::STATUS_INCOMING ? 'border-blue-700 bg-blue-700 text-white' : 'border-slate-300 bg-white hover:bg-blue-50' }}" href="{{ route('products.index', array_filter(['status' => \App\Models\Product::STATUS_INCOMING, 'q' => $keyword])) }}">进货中商品</a>
                <a class="rounded-sm border px-3 py-2 text-sm {{ ($currentStatus ?? null) === \App\Models\Product::STATUS_CONCEPT ? 'border-blue-700 bg-blue-700 text-white' : 'border-slate-300 bg-white hover:bg-blue-50' }}" href="{{ route('products.index', array_filter(['status' => \App\Models\Product::STATUS_CONCEPT, 'q' => $keyword])) }}">概念商品</a>
                @foreach($categories as $category)
                    <a class="rounded-sm border px-3 py-2 text-sm {{ $currentCategory?->id === $category->id ? 'border-blue-700 bg-blue-700 text-white' : 'border-slate-300 bg-white hover:bg-blue-50' }}" href="{{ route('products.index', array_filter(['category' => $category->slug, 'q' => $keyword])) }}">{{ $category->name }}</a>
                @endforeach
            </div>
        </div>

        <div class="grid gap-px bg-slate-200 sm:grid-cols-2 xl:grid-cols-4">
            @forelse($products as $product)
                <x-product-card :product="$product" />
            @empty
                <div class="col-span-full bg-white px-4 py-8 text-sm text-slate-600">暂无符合条件的商品。</div>
            @endforelse
        </div>

        <div class="border-t border-slate-200 px-4 py-3">{{ $products->links() }}</div>
    </section>
</x-layouts.app>
