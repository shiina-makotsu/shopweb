@props(['product'])

@php
    $variant = $product->variants->where('is_active', true)->sortBy(fn ($variant) => $variant->effectivePriceCents())->first();
    $stock = $product->variants->where('is_active', true)->sum('stock');
    $isSoldOut = $product->isSoldOut() || ($product->status === \App\Models\Product::STATUS_PUBLISHED && $stock <= 0);
    $statusNote = match ($product->status) {
        \App\Models\Product::STATUS_CONCEPT => '概念投票',
        \App\Models\Product::STATUS_PRESALE => '预售',
        \App\Models\Product::STATUS_INCOMING => '进货 '.$product->incoming_quantity,
        \App\Models\Product::STATUS_SOLD_OUT => '售罄',
        default => $isSoldOut ? '售罄' : '库存 '.$stock,
    };
    $actionLabel = match ($product->status) {
        \App\Models\Product::STATUS_CONCEPT => '参与投票',
        \App\Models\Product::STATUS_PRESALE => '预售下单',
        \App\Models\Product::STATUS_INCOMING => '查看物流',
        default => '查看',
    };
    $canCart = $variant && $product->isDirectlyPurchasable();
    $canCrowdfund = $variant && $product->allowsCrowdfunding();
@endphp

<article class="bg-white">
    <a href="{{ route('products.show', $product) }}" class="relative block border-b border-slate-100 bg-white p-3">
        @if($isSoldOut)
            <span class="absolute left-4 top-4 z-10 rounded-sm bg-slate-950/85 px-2 py-1 text-xs font-semibold text-white shadow">售罄</span>
        @endif
        @if($product->coverMedia)
            @if($product->coverMedia->isVideo())
                <video src="{{ $product->coverMedia->url() }}" class="aspect-square w-full rounded-sm bg-black object-contain {{ $isSoldOut ? 'grayscale' : '' }}" muted preload="metadata"></video>
            @else
                <img src="{{ $product->coverMedia->url() }}" alt="{{ $product->coverMedia->alt ?? $product->title }}" class="aspect-square w-full rounded-sm object-cover {{ $isSoldOut ? 'grayscale' : '' }}">
            @endif
        @else
            <div class="flex aspect-square items-center justify-center rounded-sm bg-slate-100 text-sm text-slate-500">暂无图片</div>
        @endif
    </a>
    <div class="shop-product-card-body space-y-2">
        @if($product->category)
            <p class="text-xs text-slate-500">{{ $product->category->name }}</p>
        @endif
        <a href="{{ route('products.show', $product) }}" class="block min-h-10 text-sm font-medium leading-5 hover:text-blue-800">{{ $product->title }}</a>
        @if($product->summary)
            <p class="line-clamp-2 min-h-10 text-xs leading-5 text-slate-600">{{ $product->summary }}</p>
        @endif
        <div class="flex items-end justify-between gap-3">
            <div>
                <p class="text-base font-semibold text-red-700">@money($product->starting_price_cents)</p>
                @if($variant?->hasActiveDiscount())
                    <p class="text-xs text-slate-500 line-through">@money($variant->price_cents)</p>
                    <p class="text-xs font-medium text-pink-700">限时折扣</p>
                @elseif($variant?->compare_at_price_cents)
                    <p class="text-xs text-slate-500 line-through">@money($variant->compare_at_price_cents)</p>
                @endif
                <p class="mt-1 text-xs text-slate-500">{{ $statusNote }}</p>
            </div>
        </div>
        <div class="grid gap-2 sm:grid-cols-2">
            @if($canCart)
                <form method="post" action="{{ route('cart.buy-now') }}">
                    @csrf
                    <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                    <input type="hidden" name="quantity" value="1">
                    <button class="w-full rounded-sm border border-emerald-700 bg-emerald-700 px-3 py-2 text-xs font-medium text-white hover:bg-emerald-800" type="submit">购买</button>
                </form>
                <form method="post" action="{{ route('cart.items.store') }}" data-cart-add-form data-product-title="{{ $product->title }}">
                    @csrf
                    <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                    <input type="hidden" name="quantity" value="1">
                    <button class="w-full rounded-sm border border-blue-700 bg-blue-700 px-3 py-2 text-xs font-medium text-white hover:bg-blue-800" type="submit">加入购物车</button>
                </form>
            @elseif($product->allowsCrowdfunding())
                <a href="{{ route('products.show', $product) }}#concept-votes" class="rounded-sm border border-blue-700 bg-blue-700 px-3 py-2 text-center text-xs font-medium text-white hover:bg-blue-800">投票</a>
                @if($variant)
                    <form method="post" action="{{ route('cart.buy-now') }}">
                        @csrf
                        <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                        <input type="hidden" name="quantity" value="1">
                        <button class="w-full rounded-sm border border-pink-600 bg-pink-600 px-3 py-2 text-xs font-medium text-white hover:bg-pink-700" type="submit">筹款</button>
                    </form>
                @else
                    <a href="{{ route('products.show', $product) }}" class="rounded-sm border border-pink-600 bg-pink-600 px-3 py-2 text-center text-xs font-medium text-white hover:bg-pink-700">筹款</a>
                @endif
            @else
                <a href="{{ route('products.show', $product) }}" class="rounded-sm border border-blue-700 bg-blue-700 px-3 py-2 text-center text-xs font-medium text-white hover:bg-blue-800">{{ $actionLabel }}</a>
            @endif
        </div>
    </div>
</article>
