@props(['product'])

@php
    $activeVariants = $product->variants->where('is_active', true);
    $discountVariant = $activeVariants->first(fn ($variant) => $variant->hasActiveDiscount());
    $variant = $discountVariant ?: $activeVariants->sortBy(fn ($variant) => $variant->effectivePriceCents())->first();
    $stock = $activeVariants->sum('stock');
    $unlimitedStock = $product->hasUnlimitedStock();
    $stockLabel = $product->isPresale() ? '预售不限库存' : ($unlimitedStock ? '不限库存' : '库存 '.$stock);
    $isSoldOut = $product->isSoldOut() || ($product->status === \App\Models\Product::STATUS_PUBLISHED && ! $unlimitedStock && $stock <= 0);
    $statusBadge = match ($product->status) {
        \App\Models\Product::STATUS_CONCEPT => '概念',
        \App\Models\Product::STATUS_PRESALE => '预售',
        \App\Models\Product::STATUS_INCOMING => '进货中',
        default => null,
    };
    $statusBadgeClass = match ($product->status) {
        \App\Models\Product::STATUS_CONCEPT => 'border-pink-200 bg-pink-50 text-pink-700',
        \App\Models\Product::STATUS_PRESALE => 'border-blue-200 bg-blue-50 text-blue-700',
        \App\Models\Product::STATUS_INCOMING => 'border-amber-200 bg-amber-50 text-amber-700',
        default => '',
    };
    $statusNote = match ($product->status) {
        \App\Models\Product::STATUS_CONCEPT => '概念投票',
        \App\Models\Product::STATUS_PRESALE => '预售',
        \App\Models\Product::STATUS_INCOMING => '进货 '.$product->incoming_quantity,
        \App\Models\Product::STATUS_SOLD_OUT => '售罄',
        default => $isSoldOut ? '售罄' : $stockLabel,
    };
    $actionLabel = match ($product->status) {
        \App\Models\Product::STATUS_CONCEPT => '参与投票',
        \App\Models\Product::STATUS_PRESALE => '预售下单',
        \App\Models\Product::STATUS_INCOMING => '查看物流',
        default => '查看',
    };
    $canCart = $variant && $product->isDirectlyPurchasable();
    $canCrowdfund = $variant && $product->allowsCrowdfunding();
    $wishlistActive = auth()->check()
        ? auth()->user()->wishlists()->where('product_id', $product->id)->exists()
        : false;
    $favoriteActive = auth()->check()
        ? auth()->user()->favorites()->where('product_id', $product->id)->exists()
        : false;
    $productUrl = $product->showUrl();
    $productRoute = $product->showRouteParameters();
    $comparePriceCents = $variant?->hasActiveDiscount()
        ? (int) $variant->price_cents
        : ($variant?->compare_at_price_cents ? (int) $variant->compare_at_price_cents : null);
@endphp

<article class="min-w-0 overflow-hidden bg-white">
    <a href="{{ $productUrl }}" class="relative block border-b border-slate-100 bg-white p-2 sm:p-3">
        @if($isSoldOut)
            <span class="absolute left-4 top-4 z-10 rounded-sm bg-slate-950/85 px-2 py-1 text-xs font-semibold text-white shadow">售罄</span>
        @endif
        @if($statusBadge)
            <span class="absolute right-4 top-4 z-10 rounded-sm border px-1.5 py-0.5 text-[11px] font-medium shadow-sm {{ $statusBadgeClass }}">{{ $statusBadge }}</span>
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
    <div class="shop-product-card-body min-w-0 space-y-2">
        @if($product->category)
            <p class="text-xs text-slate-500">{{ $product->category->name }}</p>
        @endif
        <a href="{{ $productUrl }}" class="block min-h-10 min-w-0 break-words text-sm font-medium leading-5 hover:text-blue-800">{{ $product->title }}</a>
        @if($product->summary)
            <p class="line-clamp-2 min-h-10 text-xs leading-5 text-slate-600">{{ $product->summary }}</p>
        @endif
        <div class="flex min-w-0 items-end justify-between gap-3">
            <div class="min-w-0">
                @if($comparePriceCents)
                    <p class="text-lg font-bold leading-tight text-red-700 line-through decoration-red-600 decoration-2">@money($comparePriceCents)</p>
                    <p class="mt-0.5 text-base font-semibold leading-tight text-red-700">@money($variant?->effectivePriceCents())</p>
                    @if($variant?->hasActiveDiscount())
                        <p class="mt-1 text-xs font-medium text-red-700">限时折扣</p>
                    @endif
                @else
                    <p class="text-base font-semibold text-red-700">{{ $product->priceRangeLabel() }}</p>
                @endif
                <p class="mt-1 text-xs text-slate-500">{{ $statusNote }}</p>
                @if($variant)
                    <p class="mt-1 truncate text-xs text-slate-500">{{ $variant->specLabel() }}</p>
                @endif
            </div>
        </div>
        <div class="grid grid-cols-2 gap-2">
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
                    <button class="inline-flex h-9 w-full items-center justify-center rounded-sm border border-blue-700 bg-blue-700 text-white hover:bg-blue-800" type="submit" aria-label="加入购物车" title="加入购物车">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" aria-hidden="true" fill="currentColor"><path d="M2.5 3a.75.75 0 0 0 0 1.5h1.06l1.45 7.24A2.25 2.25 0 0 0 7.22 13.5h6.86a2.25 2.25 0 0 0 2.16-1.62l1.08-3.79A1.75 1.75 0 0 0 15.64 5.85H5.3l-.35-1.76A1.75 1.75 0 0 0 3.24 3H2.5Zm4.25 13.5a1.25 1.25 0 1 0 0-2.5 1.25 1.25 0 0 0 0 2.5Zm7.5 0a1.25 1.25 0 1 0 0-2.5 1.25 1.25 0 0 0 0 2.5Z"/></svg>
                    </button>
                </form>
            @elseif($product->allowsCrowdfunding())
                <a href="{{ $productUrl }}#concept-votes" class="rounded-sm border border-blue-700 bg-blue-700 px-3 py-2 text-center text-xs font-medium text-white hover:bg-blue-800">投票</a>
                @if($variant)
                    <form method="post" action="{{ route('cart.buy-now') }}">
                        @csrf
                        <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                        <input type="hidden" name="quantity" value="1">
                        <button class="w-full rounded-sm border border-pink-600 bg-pink-600 px-3 py-2 text-xs font-medium text-white hover:bg-pink-700" type="submit">筹款</button>
                    </form>
                @else
                    <a href="{{ $productUrl }}" class="rounded-sm border border-pink-600 bg-pink-600 px-3 py-2 text-center text-xs font-medium text-white hover:bg-pink-700">筹款</a>
                @endif
            @else
                <a href="{{ $productUrl }}" class="rounded-sm border border-blue-700 bg-blue-700 px-3 py-2 text-center text-xs font-medium text-white hover:bg-blue-800">{{ $actionLabel }}</a>
            @endif
        </div>
        <div class="grid grid-cols-2 gap-2">
            @auth
                <form method="post" action="{{ route('products.wishlist.status.toggle', $productRoute) }}" data-product-preference-form>
                    @csrf
                    <button class="inline-flex h-9 w-full items-center justify-center rounded-sm border {{ $wishlistActive ? 'border-pink-300 bg-pink-50 text-pink-800' : 'border-slate-300 bg-white text-slate-700 hover:bg-pink-50 hover:text-pink-800' }}" type="submit" aria-label="{{ $wishlistActive ? '已在愿望单' : '加入愿望单' }}" title="{{ $wishlistActive ? '已在愿望单' : '加入愿望单' }}" data-preference-active="{{ $wishlistActive ? 'true' : 'false' }}">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" aria-hidden="true" fill="currentColor"><path d="M5.5 2A2.5 2.5 0 0 0 3 4.5v11A2.5 2.5 0 0 0 5.5 18h9a2.5 2.5 0 0 0 2.5-2.5V7.62a2.5 2.5 0 0 0-.73-1.77l-3.12-3.12A2.5 2.5 0 0 0 11.38 2H5.5Zm5.75 1.5V6A1.75 1.75 0 0 0 13 7.75h2.5v7.75a1 1 0 0 1-1 1h-9a1 1 0 0 1-1-1v-11a1 1 0 0 1 1-1h5.75Zm1.5.56 2.19 2.19H13a.25.25 0 0 1-.25-.25V4.06Z"/></svg>
                    </button>
                </form>
                <form method="post" action="{{ route('products.favorite.status.toggle', $productRoute) }}" data-product-preference-form>
                    @csrf
                    <button class="inline-flex h-9 w-full items-center justify-center rounded-sm border {{ $favoriteActive ? 'border-blue-300 bg-blue-50 text-blue-800' : 'border-slate-300 bg-white text-slate-700 hover:bg-blue-50 hover:text-blue-800' }}" type="submit" aria-label="{{ $favoriteActive ? '已收藏' : '收藏商品' }}" title="{{ $favoriteActive ? '已收藏' : '收藏商品' }}" data-preference-active="{{ $favoriteActive ? 'true' : 'false' }}">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" aria-hidden="true" fill="currentColor"><path d="M9.1 2.9a1 1 0 0 1 1.8 0l1.72 3.48 3.84.56a1 1 0 0 1 .55 1.7l-2.78 2.71.66 3.83a1 1 0 0 1-1.45 1.05L10 14.42l-3.44 1.81a1 1 0 0 1-1.45-1.05l.66-3.83-2.78-2.71a1 1 0 0 1 .55-1.7l3.84-.56L9.1 2.9Z"/></svg>
                    </button>
                </form>
            @else
                <a class="inline-flex h-9 items-center justify-center rounded-sm border border-slate-300 bg-white text-slate-700 hover:bg-pink-50 hover:text-pink-800" href="{{ route('login') }}" aria-label="登录后加入愿望单" title="登录后加入愿望单">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" aria-hidden="true" fill="currentColor"><path d="M5.5 2A2.5 2.5 0 0 0 3 4.5v11A2.5 2.5 0 0 0 5.5 18h9a2.5 2.5 0 0 0 2.5-2.5V7.62a2.5 2.5 0 0 0-.73-1.77l-3.12-3.12A2.5 2.5 0 0 0 11.38 2H5.5Zm5.75 1.5V6A1.75 1.75 0 0 0 13 7.75h2.5v7.75a1 1 0 0 1-1 1h-9a1 1 0 0 1-1-1v-11a1 1 0 0 1 1-1h5.75Zm1.5.56 2.19 2.19H13a.25.25 0 0 1-.25-.25V4.06Z"/></svg>
                </a>
                <a class="inline-flex h-9 items-center justify-center rounded-sm border border-slate-300 bg-white text-slate-700 hover:bg-blue-50 hover:text-blue-800" href="{{ route('login') }}" aria-label="登录后收藏商品" title="登录后收藏商品">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" aria-hidden="true" fill="currentColor"><path d="M9.1 2.9a1 1 0 0 1 1.8 0l1.72 3.48 3.84.56a1 1 0 0 1 .55 1.7l-2.78 2.71.66 3.83a1 1 0 0 1-1.45 1.05L10 14.42l-3.44 1.81a1 1 0 0 1-1.45-1.05l.66-3.83-2.78-2.71a1 1 0 0 1 .55-1.7l3.84-.56L9.1 2.9Z"/></svg>
                </a>
            @endauth
        </div>
    </div>
</article>
