@php
    $homeInfoMenuItems = $homeInfoMenuItems ?? collect();
    $homeInfoLinks = $homeInfoMenuItems;
    $homeInfoLabel = fn ($item): string => $item->label;
    $homeInfoUrl = fn ($item): string => $item->resolvedUrl();
    $homeInfoTarget = fn ($item): ?string => $item instanceof \App\Models\NavigationMenuItem && $item->opens_new_tab ? '_blank' : null;
    $homeInfoChildren = fn ($item) => $item->children ?? collect();
    $homeInfoHasDestination = fn ($item): bool => $item->hasDestination();
    $homeInfoVisible = fn ($item): bool => $homeInfoHasDestination($item) || $homeInfoChildren($item)->isNotEmpty();
@endphp

<x-layouts.app :title="$settings?->site_name ?? config('app.name')" :settings="$settings">
    @if($settings?->home_welcome_enabled ?? true)
        <section class="mb-4 rounded-sm border border-slate-300 bg-white">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-100 px-4 py-2">
                <h1 class="text-lg font-semibold">{{ $settings?->home_title ?? '欢迎光临' }}</h1>
                @if($homeInfoLinks->isNotEmpty())
                    <nav class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="text-slate-500">商店信息</span>
                        @foreach($homeInfoLinks as $link)
                            @continue(! $homeInfoVisible($link))
                            @if($homeInfoHasDestination($link))
                                <a
                                    href="{{ $homeInfoUrl($link) }}"
                                    class="rounded-sm border border-slate-300 bg-white px-2 py-1 hover:bg-blue-50 hover:text-blue-800"
                                    @if($homeInfoTarget($link)) target="{{ $homeInfoTarget($link) }}" rel="noopener noreferrer" @endif
                                >
                                    {{ $homeInfoLabel($link) }}
                                </a>
                            @else
                                <span class="rounded-sm border border-slate-200 bg-slate-50 px-2 py-1 text-slate-500" title="请选择下方子菜单">{{ $homeInfoLabel($link) }}</span>
                            @endif
                            @foreach($homeInfoChildren($link) as $child)
                                @continue(! $homeInfoVisible($child) || ! $homeInfoHasDestination($child))
                                <a
                                    href="{{ $homeInfoUrl($child) }}"
                                    class="rounded-sm border border-slate-300 bg-white px-2 py-1 hover:bg-blue-50 hover:text-blue-800"
                                    @if($homeInfoTarget($child)) target="{{ $homeInfoTarget($child) }}" rel="noopener noreferrer" @endif
                                >
                                    {{ $homeInfoLabel($child) }}
                                </a>
                            @endforeach
                        @endforeach
                    </nav>
                @endif
            </div>
            <div class="grid gap-4 px-4 py-4 {{ $settings?->home_welcome_image_path ? 'md:grid-cols-[240px_1fr]' : '' }}">
                @if($settings?->home_welcome_image_path)
                    <img class="w-full rounded-sm border border-slate-200 object-cover md:aspect-[4/3]" src="{{ $settings->homeWelcomeImageUrl() }}" alt="{{ $settings?->home_title ?? '欢迎光临' }}">
                @endif
                <div>
                    @if($settings?->home_content)
                        <div class="content-body text-sm text-slate-700">
                            {{ \App\Support\Markdown::render($settings->home_content) }}
                        </div>
                    @else
                        <p class="max-w-3xl text-sm leading-6 text-slate-700">
                            欢迎光临。
                        </p>
                    @endif
                </div>
            </div>
        </section>
    @elseif($homeInfoLinks->isNotEmpty())
        <section class="mb-4 rounded-sm border border-slate-300 bg-white">
            <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                <h1 class="text-base font-semibold">商店信息</h1>
                <nav class="flex flex-wrap gap-2 text-xs">
                    @foreach($homeInfoLinks as $link)
                        @continue(! $homeInfoVisible($link))
                        @if($homeInfoHasDestination($link))
                            <a
                                href="{{ $homeInfoUrl($link) }}"
                                class="rounded-sm border border-slate-300 bg-white px-2 py-1 hover:bg-blue-50 hover:text-blue-800"
                                @if($homeInfoTarget($link)) target="{{ $homeInfoTarget($link) }}" rel="noopener noreferrer" @endif
                            >
                                {{ $homeInfoLabel($link) }}
                            </a>
                        @else
                            <span class="rounded-sm border border-slate-200 bg-slate-50 px-2 py-1 text-slate-500" title="请选择下方子菜单">{{ $homeInfoLabel($link) }}</span>
                        @endif
                        @foreach($homeInfoChildren($link) as $child)
                            @continue(! $homeInfoVisible($child) || ! $homeInfoHasDestination($child))
                            <a
                                href="{{ $homeInfoUrl($child) }}"
                                class="rounded-sm border border-slate-300 bg-white px-2 py-1 hover:bg-blue-50 hover:text-blue-800"
                                @if($homeInfoTarget($child)) target="{{ $homeInfoTarget($child) }}" rel="noopener noreferrer" @endif
                            >
                                {{ $homeInfoLabel($child) }}
                            </a>
                        @endforeach
                    @endforeach
                </nav>
            </div>
        </section>
    @endif

    <section class="mb-4 grid gap-3 md:grid-cols-4">
        <a class="rounded-sm border border-violet-200 bg-white px-4 py-4 hover:bg-violet-50" href="{{ route('ai-image.index') }}">
            <span class="block text-base font-semibold text-violet-900">AI</span>
            <span class="mt-1 block text-sm text-slate-600">接入自定义模型接口，用提示词、参考图和尺寸参数生成图片。</span>
        </a>
        <a class="rounded-sm border border-blue-200 bg-white px-4 py-4 hover:bg-blue-50" href="{{ route('support.index') }}">
            <span class="block text-base font-semibold text-blue-900">客服会话</span>
            <span class="mt-1 block text-sm text-slate-600">像聊天一样和客服即时沟通，可附带订单、图片或文件。</span>
        </a>
        <a class="rounded-sm border border-pink-200 bg-white px-4 py-4 hover:bg-pink-50" href="{{ route('support.demands') }}">
            <span class="block text-base font-semibold text-pink-900">客服工单</span>
            <span class="mt-1 block text-sm text-slate-600">提交投诉反馈、账号问题或其他需要后台处理的问题。</span>
        </a>
        <a class="rounded-sm border border-slate-200 bg-white px-4 py-4 hover:bg-blue-50" href="{{ route('friend-links.index') }}">
            <span class="block text-base font-semibold text-slate-900">友情链接</span>
            <span class="mt-1 block text-sm text-slate-600">查看伙伴社区、资源网站和推荐链接。</span>
        </a>
    </section>

    @if($featuredProducts->isNotEmpty())
        <section class="mb-4 rounded-sm border border-slate-300 bg-white">
            <div class="flex items-center justify-between border-b border-slate-200 bg-slate-100 px-4 py-2">
                <h2 class="text-base font-semibold">推荐商品</h2>
                <a class="text-sm text-blue-700 hover:text-blue-900" href="{{ route('products.index', ['featured' => 1]) }}">查看全部</a>
            </div>
            <div class="grid grid-cols-2 gap-px bg-slate-200 xl:grid-cols-4">
                @foreach($featuredProducts as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        </section>
    @endif

    <section class="mb-4 rounded-sm border border-slate-300 bg-white">
        <div class="flex items-center justify-between border-b border-slate-200 bg-slate-100 px-4 py-2">
            <h2 class="text-base font-semibold">最新商品</h2>
            <a class="text-sm text-blue-700 hover:text-blue-900" href="{{ route('products.index') }}">商品列表</a>
        </div>
        <div class="grid grid-cols-2 gap-px bg-slate-200 xl:grid-cols-4">
            @forelse($latestProducts as $product)
                <x-product-card :product="$product" />
            @empty
                <div class="col-span-full bg-white px-4 py-8 text-sm text-slate-600">暂无商品，管理员可进入后台创建。</div>
            @endforelse
        </div>
    </section>

    <section class="mb-4 rounded-sm border border-slate-300 bg-white">
        <div class="flex items-center justify-between border-b border-slate-200 bg-slate-100 px-4 py-2">
            <h2 class="text-base font-semibold">折扣商品</h2>
            <a class="text-sm text-blue-700 hover:text-blue-900" href="{{ route('products.index', ['discount' => 1]) }}">查看全部</a>
        </div>
        <div class="grid grid-cols-2 gap-px bg-slate-200 xl:grid-cols-4">
            @forelse($discountProducts as $product)
                <x-product-card :product="$product" />
            @empty
                <div class="col-span-full bg-white px-4 py-8 text-sm text-slate-600">暂无折扣商品。</div>
            @endforelse
        </div>
    </section>

    <section class="mb-4 rounded-sm border border-slate-300 bg-white">
        <div class="flex items-center justify-between border-b border-slate-200 bg-slate-100 px-4 py-2">
            <h2 class="text-base font-semibold">秒杀商品</h2>
            <a class="text-sm text-blue-700 hover:text-blue-900" href="{{ route('products.index') }}">查看全部</a>
        </div>
        <div class="grid grid-cols-2 gap-px bg-slate-200 xl:grid-cols-4">
            @forelse($flashSales as $flashSale)
                @php($product = $flashSale->product)
                <article class="bg-white p-3">
                    <a href="{{ route('products.show', $product) }}" class="block">
                        @if($product->coverMedia)
                            @if($product->coverMedia->isVideo())
                                <video src="{{ $product->coverMedia->url() }}" class="aspect-square w-full rounded-sm bg-black object-contain" muted preload="metadata"></video>
                            @else
                                <img src="{{ $product->coverMedia->url() }}" alt="{{ $product->coverMedia->alt ?? $product->title }}" class="aspect-square w-full rounded-sm object-cover">
                            @endif
                        @else
                            <div class="flex aspect-square items-center justify-center rounded-sm bg-slate-100 text-sm text-slate-500">暂无图片</div>
                        @endif
                    </a>
                    <div class="mt-3 space-y-2">
                        <a href="{{ route('products.show', $product) }}" class="block min-h-10 text-sm font-medium leading-5 hover:text-blue-800">{{ $product->title }}</a>
                        <p class="text-base font-semibold text-red-700">@money($flashSale->sale_price_cents)</p>
                        @if($flashSale->starts_at->isFuture())
                            <p class="text-xs text-slate-600">下次秒杀：{{ $flashSale->starts_at->format('m-d H:i') }}</p>
                        @else
                            <p class="text-xs text-slate-600">本场剩余：{{ $flashSale->availableQuantity() }} 件</p>
                        @endif
                        @if($flashSale->starts_at->isFuture())
                            <button class="w-full rounded-sm border border-slate-300 bg-slate-100 px-3 py-2 text-xs font-medium text-slate-600" type="button" disabled>未开始</button>
                        @else
                            @auth
                                @if($flashSale->isAvailable())
                                    <form method="post" action="{{ route('flash-sales.reserve', $flashSale) }}">
                                        @csrf
                                        <input type="hidden" name="quantity" value="1">
                                        <button class="w-full rounded-sm border border-pink-600 bg-pink-600 px-3 py-2 text-xs font-medium text-white hover:bg-pink-700" type="submit">马上抢</button>
                                    </form>
                                @else
                                    <button class="w-full rounded-sm border border-slate-300 bg-slate-100 px-3 py-2 text-xs font-medium text-slate-600" type="button" disabled>已抢完</button>
                                @endif
                            @else
                                @if($flashSale->isAvailable())
                                    <a class="block rounded-sm border border-pink-600 bg-pink-600 px-3 py-2 text-center text-xs font-medium text-white hover:bg-pink-700" href="{{ route('login') }}">登录后抢</a>
                                @else
                                    <button class="w-full rounded-sm border border-slate-300 bg-slate-100 px-3 py-2 text-xs font-medium text-slate-600" type="button" disabled>已抢完</button>
                                @endif
                            @endauth
                        @endif
                    </div>
                </article>
            @empty
                <div class="col-span-full bg-white px-4 py-8 text-sm text-slate-600">暂无秒杀商品。</div>
            @endforelse
        </div>
    </section>

    <section class="mb-4 rounded-sm border border-slate-300 bg-white">
        <div class="flex items-center justify-between border-b border-slate-200 bg-slate-100 px-4 py-2">
            <h2 class="text-base font-semibold">概念商品</h2>
            <a class="text-sm text-blue-700 hover:text-blue-900" href="{{ route('products.index', ['status' => \App\Models\Product::STATUS_CONCEPT]) }}">查看全部</a>
        </div>
        <div class="grid grid-cols-2 gap-px bg-slate-200 xl:grid-cols-4">
            @forelse($conceptProducts as $product)
                <x-product-card :product="$product" />
            @empty
                <div class="col-span-full bg-white px-4 py-8 text-sm text-slate-600">暂无概念商品。</div>
            @endforelse
        </div>
    </section>
</x-layouts.app>
