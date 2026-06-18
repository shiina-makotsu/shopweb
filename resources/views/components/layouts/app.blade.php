@props(['title' => null, 'wide' => false, 'description' => null, 'settings' => null])

@php
    $siteSettings = $siteSettings ?? $settings ?? null;
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
    $storeName = $siteSettings?->site_name ?? config('app.name');
    $categories = $storeCategories ?? collect();
    $infoMenuItems = $storeHomeInfoMenuItems ?? collect();
    $menuItems = $storeTopNavItems ?? $storeMenuItems ?? collect();
    $path = fn (string $name, mixed $parameters = []): string => \App\Support\Url::route($name, $parameters);
    $topNavItems = $menuItems->isNotEmpty()
        ? $menuItems
        : collect([
            ['label' => '首页', 'url' => $path('home'), 'opens_new_tab' => false, 'children' => collect([
                ['label' => '标签', 'url' => $path('tags.index'), 'opens_new_tab' => false, 'children' => collect()],
            ])],
            ['label' => '全部商品', 'url' => $path('products.index'), 'opens_new_tab' => false, 'children' => collect()],
            ['label' => 'AI', 'url' => $path('ai-image.index'), 'opens_new_tab' => false, 'children' => collect()],
            ['label' => '友情链接', 'url' => $path('friend-links.index'), 'opens_new_tab' => false, 'children' => collect()],
            ['label' => '论坛', 'url' => $path('forum.index'), 'opens_new_tab' => false, 'children' => collect()],
            ['label' => '物流查询', 'url' => $path('shipments.show'), 'opens_new_tab' => false, 'children' => collect()],
            ['label' => '客服会话', 'url' => $path('support.index'), 'opens_new_tab' => false, 'children' => collect()],
            ['label' => '客服工单', 'url' => $path('support.demands'), 'opens_new_tab' => false, 'children' => collect()],
            ['label' => '订单查询', 'url' => $path(auth()->check() ? 'orders.index' : 'login'), 'opens_new_tab' => false, 'children' => collect()],
        ]);
    $menuLabel = fn ($item): string => is_array($item) ? $item['label'] : $item->label;
    $menuUrl = fn ($item): string => is_array($item) ? $item['url'] : $item->resolvedUrl();
    $menuTarget = fn ($item): ?string => (is_array($item) ? ($item['opens_new_tab'] ?? false) : $item->opens_new_tab) ? '_blank' : null;
    $menuTooltip = fn ($item): ?string => filled(is_array($item) ? ($item['tooltip_text'] ?? null) : $item->tooltip_text) ? (string) (is_array($item) ? $item['tooltip_text'] : $item->tooltip_text) : null;
    $menuChildren = fn ($item) => is_array($item) ? collect($item['children'] ?? []) : ($item->children ?? collect());
    $menuHasDestination = fn ($item): bool => is_array($item) ? filled($item['url'] ?? null) : $item->hasDestination();
    $menuVisible = fn ($item): bool => $menuHasDestination($item) || $menuChildren($item)->isNotEmpty();
    $mobileStandaloneMenuUrls = collect([
        $path('home'),
        $path('products.index'),
        $path('ai-image.index'),
        $path('friend-links.index'),
        $path('forum.index'),
        $path('support.index'),
        $path('support.demands'),
    ])->map(fn (string $url): string => rtrim($url, '/'))->all();
    $mobileSkipMenuItem = fn ($item): bool => $menuHasDestination($item)
        && in_array(rtrim($menuUrl($item), '/'), $mobileStandaloneMenuUrls, true);
    $cartCount = $cartItemCount ?? 0;
    $cartSubtotal = $cartSubtotalCents ?? 0;
    $appearance = $siteSettings?->appearance() ?? [
        'theme_template' => 'default',
        'primary_color' => '#2D9CDB',
        'accent_color' => '#F5A9B8',
        'background_color' => '#FFF7FB',
        'button_radius' => '2px',
        'product_card_padding' => '0.75rem',
    ];
    $backgroundUrl = match (true) {
        request()->routeIs('home') => $siteSettings?->homeBackgroundUrl(),
        request()->routeIs('login', 'register') => $siteSettings?->authBackgroundUrl(),
        default => null,
    };
    $backgroundCss = $backgroundUrl ? 'url('.str_replace([')', "\r", "\n"], ['\\)', '', ''], $backgroundUrl).')' : 'none';
@endphp

<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if($description)
        <meta name="description" content="{{ $description }}">
    @endif
    <title>{{ $title ? $title.' - '.$storeName : $storeName }}</title>
    @if($siteSettings?->favicon_path)
        <link rel="icon" href="{{ $siteSettings->faviconUrl() }}">
    @endif
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --shop-primary: {{ $appearance['primary_color'] }};
            --shop-accent: {{ $appearance['accent_color'] }};
            --shop-background: {{ $appearance['background_color'] }};
            --shop-page-gradient: linear-gradient(135deg, color-mix(in srgb, var(--shop-primary), #fff 84%) 0%, #fff 48%, color-mix(in srgb, var(--shop-accent), #fff 80%) 100%);
            --shop-button-radius: {{ $appearance['button_radius'] }};
            --shop-product-card-padding: {{ $appearance['product_card_padding'] }};
            --shop-page-background-image: {{ $backgroundCss }};
        }
    </style>
</head>
<body id="top" class="min-h-screen overflow-x-hidden bg-fixed bg-center bg-cover text-slate-900 theme-{{ $appearance['theme_template'] }}" style="background-color: var(--shop-background); background-image: var(--shop-page-background-image), var(--shop-page-gradient)">
    <header class="border-b border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 text-xs text-slate-700">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2 px-4 py-2">
                <p>{{ $siteSettings?->welcome_message ?: '欢迎来到 '.$storeName }}</p>
                <nav class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <a class="hover:text-blue-700" href="{{ $path('support.index') }}">客服</a>
                    <a class="hover:text-blue-700" href="{{ $path('support.demands') }}">工单</a>
                    @auth
                        <a class="relative inline-flex items-center hover:text-blue-700" href="{{ $path('announcements.index') }}" title="公告">
                            <span aria-hidden="true">🔔</span>
                            @if(($unreadAnnouncementCount ?? 0) > 0)
                                <span class="ml-1 rounded-full bg-red-600 px-1.5 text-[10px] font-semibold text-white">{{ $unreadAnnouncementCount }}</span>
                            @endif
                        </a>
                    @else
                        <a class="hover:text-blue-700" href="{{ $path('announcements.index') }}">公告</a>
                    @endauth
                    @auth
                        <a class="hover:text-blue-700" href="{{ $path('user.center') }}">用户中心</a>
                        <form method="post" action="{{ $path('logout') }}">
                            @csrf
                            <button class="hover:text-blue-700" type="submit">退出</button>
                        </form>
                    @else
                        <a class="hover:text-blue-700" href="{{ $path('login') }}">登录</a>
                        <a class="hover:text-blue-700" href="{{ $path('register') }}">注册</a>
                    @endauth
                </nav>
            </div>
        </div>

        <div class="mx-auto grid max-w-7xl gap-4 px-4 py-4 md:grid-cols-[auto_1fr] md:items-center">
            <a href="{{ $path('home') }}" class="flex min-w-0 items-center gap-3">
                @if($siteSettings?->logo_path)
                    <img src="{{ $siteSettings->logoUrl() }}" alt="{{ $storeName }}" class="h-12 w-12 rounded-sm border border-slate-200 object-cover">
                @else
                    <span class="flex h-12 w-12 items-center justify-center rounded-sm bg-blue-700 text-lg font-semibold text-white">{{ mb_substr($storeName, 0, 1) }}</span>
                @endif
                <span class="truncate text-2xl font-semibold tracking-normal">{{ $storeName }}</span>
            </a>

            <form method="get" action="{{ $path('products.index') }}" class="grid w-full min-w-0 gap-2 sm:grid-cols-[1fr_auto]">
                <input
                    class="min-w-0 rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 sm:rounded-r-none sm:border-r-0"
                    type="search"
                    name="q"
                    value="{{ request('q') }}"
                    placeholder="搜索商品名称、简介或详情"
                >
                <button class="rounded-sm border border-blue-700 bg-blue-700 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800 sm:rounded-l-none" type="submit">搜索</button>
                <a class="text-xs text-blue-700 hover:text-blue-900 sm:col-span-2" href="{{ $path('search.index', request('q') ? ['q' => request('q')] : []) }}">综合搜索用户和商品</a>
            </form>

        </div>

        <nav class="border-t border-slate-200 bg-blue-800 text-sm font-medium text-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-2 md:hidden">
                <span class="font-semibold">导航</span>
                <button
                    class="inline-flex items-center gap-2 rounded-sm border border-blue-200 px-3 py-2 text-sm font-medium text-white hover:bg-blue-900"
                    type="button"
                    data-mobile-menu-open
                    aria-controls="site-mobile-menu"
                    aria-expanded="false"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" aria-hidden="true" fill="currentColor"><path d="M3 5.75A.75.75 0 0 1 3.75 5h12.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 5.75Zm0 4A.75.75 0 0 1 3.75 9h12.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 9.75Zm.75 3.25a.75.75 0 0 0 0 1.5h12.5a.75.75 0 0 0 0-1.5H3.75Z"/></svg>
                    打开
                </button>
            </div>
            <div class="mx-auto hidden max-w-7xl flex-wrap px-4 md:flex">
                @foreach($topNavItems as $menuItem)
                    @php($children = $menuChildren($menuItem))
                    @continue(! $menuVisible($menuItem))
                    <div class="group relative">
                        @if($menuHasDestination($menuItem))
                            <a
                                class="block px-4 py-3 hover:bg-blue-900"
                                href="{{ $menuUrl($menuItem) }}"
                                @if($menuTooltip($menuItem)) title="{{ $menuTooltip($menuItem) }}" @endif
                                @if($menuTarget($menuItem)) target="{{ $menuTarget($menuItem) }}" rel="noopener noreferrer" @endif
                            >
                                {{ $menuLabel($menuItem) }}
                            </a>
                        @else
                            <button class="block px-4 py-3 text-left hover:bg-blue-900" type="button" aria-haspopup="true">
                                {{ $menuLabel($menuItem) }}
                            </button>
                        @endif
                        @if($children->isNotEmpty())
                            <div class="absolute left-0 top-full z-30 hidden min-w-44 border border-slate-300 bg-white text-slate-900 shadow-lg group-hover:block group-focus-within:block">
                                @if(! $menuHasDestination($menuItem))
                                    <span class="block border-b border-slate-100 px-4 py-2 text-xs text-slate-500">请选择下方子菜单</span>
                                @endif
                                @foreach($children as $child)
                                    @continue(! $menuVisible($child))
                                    @if($menuHasDestination($child))
                                        <a
                                            class="block max-w-[18rem] truncate px-4 py-2 text-sm hover:bg-blue-50 hover:text-blue-800"
                                            href="{{ $menuUrl($child) }}"
                                            @if($menuTooltip($child)) title="{{ $menuTooltip($child) }}" @endif
                                            @if($menuTarget($child)) target="{{ $menuTarget($child) }}" rel="noopener noreferrer" @endif
                                        >
                                            {{ $menuLabel($child) }}
                                        </a>
                                    @else
                                        <span class="block max-w-[18rem] truncate px-4 py-2 text-sm text-slate-500">{{ $menuLabel($child) }}</span>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </nav>

        <div id="site-mobile-menu" class="fixed inset-0 z-50 hidden md:hidden" data-mobile-menu>
            <button class="absolute inset-0 bg-slate-950/45" type="button" data-mobile-menu-close aria-label="关闭菜单"></button>
            <aside class="absolute bottom-0 left-0 top-0 flex w-80 max-w-[86vw] flex-col border-r border-slate-300 bg-white text-slate-900 shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                    <span class="text-base font-semibold">页面菜单</span>
                    <button class="rounded-sm border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50" type="button" data-mobile-menu-close>关闭</button>
                </div>
                <nav class="flex-1 space-y-2 overflow-y-auto px-3 py-3 text-sm">
                    <a class="block rounded-sm border border-slate-200 bg-slate-50 px-3 py-2 font-medium hover:bg-blue-50 hover:text-blue-800" href="{{ $path('home') }}">首页</a>
                    <details class="rounded-sm border border-slate-200 bg-white">
                        <summary class="cursor-pointer px-3 py-2 font-medium">商品分类</summary>
                        <div class="border-t border-slate-100 py-1">
                            <a class="block px-5 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('products.index') }}">全部商品</a>
                            @forelse($categories as $category)
                                <a class="block px-5 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('products.index', ['category' => $category->slug]) }}">{{ $category->name }}</a>
                            @empty
                                <span class="block px-5 py-2 text-slate-500">暂无分类</span>
                            @endforelse
                        </div>
                    </details>
                    <details class="rounded-sm border border-slate-200 bg-white">
                        <summary class="cursor-pointer px-3 py-2 font-medium">用户中心</summary>
                        <div class="border-t border-slate-100 py-1">
                            @auth
                                <a class="block px-5 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('user.center') }}">个人信息</a>
                                <a class="block px-5 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('orders.index') }}">我的订单</a>
                                <a class="relative block px-5 py-2 pr-12 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('user.section', 'chat') }}">
                                    聊天
                                    @if(($privateUnreadMessageCount ?? 0) > 0)
                                        <span class="absolute right-5 top-1.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 text-[10px] font-semibold leading-none text-white">{{ (int) $privateUnreadMessageCount > 99 ? '99+' : (int) $privateUnreadMessageCount }}</span>
                                    @endif
                                </a>
                                <a class="block px-5 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('user.section', 'wishlists') }}">愿望单</a>
                                <a class="block px-5 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('user.section', 'favorites') }}">收藏商品</a>
                                <a class="block px-5 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('user.section', 'ai') }}">AI 配额</a>
                            @else
                                <a class="block px-5 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('login') }}">登录</a>
                                <a class="block px-5 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('register') }}">注册新账号</a>
                            @endauth
                        </div>
                    </details>
                    @if($infoMenuItems->isNotEmpty() || $siteSettings?->contact_info)
                        <details class="rounded-sm border border-slate-200 bg-white">
                            <summary class="cursor-pointer px-3 py-2 font-medium">信息</summary>
                            <div class="border-t border-slate-100 py-1">
                                @foreach($infoMenuItems as $infoItem)
                                    @php($children = $menuChildren($infoItem))
                                    @continue(! $menuVisible($infoItem))
                                    @if($menuHasDestination($infoItem))
                                        <a class="block px-5 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $menuUrl($infoItem) }}">{{ $menuLabel($infoItem) }}</a>
                                    @else
                                        <span class="block px-5 py-2 text-slate-500">{{ $menuLabel($infoItem) }}</span>
                                    @endif
                                    @foreach($children as $child)
                                        @continue(! $menuVisible($child) || ! $menuHasDestination($child))
                                        <a class="block px-8 py-2 text-slate-600 hover:bg-blue-50 hover:text-blue-800" href="{{ $menuUrl($child) }}">{{ $menuLabel($child) }}</a>
                                    @endforeach
                                @endforeach
                                @if($siteSettings?->contact_info)
                                    <p class="whitespace-pre-line px-5 py-2 text-xs text-slate-500">{{ $siteSettings->contact_info }}</p>
                                @endif
                            </div>
                        </details>
                    @endif
                    <details class="rounded-sm border border-slate-200 bg-white">
                        <summary class="cursor-pointer px-3 py-2 font-medium">AI</summary>
                        <div class="border-t border-slate-100 py-1">
                            <a class="block px-5 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('ai-image.index') }}">AI 生图</a>
                            <a class="block px-5 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('ai-image.index', ['mode' => 'chat']) }}">AI 聊天</a>
                        </div>
                    </details>
                    <details class="rounded-sm border border-slate-200 bg-white">
                        <summary class="cursor-pointer px-3 py-2 font-medium">客服会话</summary>
                        <div class="border-t border-slate-100 py-1">
                            <a class="block px-5 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('support.index') }}">即时会话</a>
                            <a class="block px-5 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('support.demands') }}">提交工单</a>
                        </div>
                    </details>
                    <details class="rounded-sm border border-slate-200 bg-white">
                        <summary class="cursor-pointer px-3 py-2 font-medium">客服工单</summary>
                        <div class="border-t border-slate-100 py-1">
                            <a class="block px-5 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('support.demands') }}">工单列表</a>
                            <a class="block px-5 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('support.index') }}">转到会话</a>
                        </div>
                    </details>
                    <details class="rounded-sm border border-slate-200 bg-white">
                        <summary class="cursor-pointer px-3 py-2 font-medium">友情链接</summary>
                        <div class="border-t border-slate-100 py-1">
                            <a class="block px-5 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('friend-links.index') }}">友情链接页面</a>
                        </div>
                    </details>
                    <details class="rounded-sm border border-slate-200 bg-white">
                        <summary class="cursor-pointer px-3 py-2 font-medium">论坛</summary>
                        <div class="border-t border-slate-100 py-1">
                            <a class="block px-5 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('forum.index') }}">论坛首页</a>
                            @auth
                                <a class="block px-5 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('forum.threads.create') }}">发帖</a>
                            @else
                                <a class="block px-5 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('login') }}">登录后发帖</a>
                            @endauth
                        </div>
                    </details>
                    @foreach($topNavItems as $menuItem)
                        @php($children = $menuChildren($menuItem))
                        @continue(! $menuVisible($menuItem))
                        @continue($mobileSkipMenuItem($menuItem))
                        <details class="rounded-sm border border-slate-200 bg-white">
                            <summary class="cursor-pointer px-3 py-2 font-medium">{{ $menuLabel($menuItem) }}</summary>
                            <div class="border-t border-slate-100 py-1">
                                @if($menuHasDestination($menuItem))
                                    <a class="block px-5 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $menuUrl($menuItem) }}">进入{{ $menuLabel($menuItem) }}</a>
                                @else
                                    <span class="block px-5 py-2 text-xs text-slate-500">请选择下方子菜单</span>
                                @endif
                                @foreach($children as $child)
                                    @continue(! $menuVisible($child))
                                    @if($menuHasDestination($child))
                                        <a class="block px-5 py-2 text-slate-600 hover:bg-blue-50 hover:text-blue-800" href="{{ $menuUrl($child) }}">{{ $menuLabel($child) }}</a>
                                    @else
                                        <span class="block px-5 py-2 text-slate-500">{{ $menuLabel($child) }}</span>
                                    @endif
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                </nav>
            </aside>
        </div>
    </header>

    <main class="mx-auto w-full max-w-7xl min-w-0 px-4 py-4">
        @if(session('status'))
            <div class="mb-4 rounded-sm border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
        @endif

        @if($errors->any())
            <div class="mb-4 rounded-sm border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-inside list-disc">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($popupAnnouncement ?? null)
            <div class="mb-4 rounded-sm border border-pink-300 bg-pink-50 px-4 py-3 text-sm text-pink-950">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="font-semibold">未读公告：{{ $popupAnnouncement->title }}</p>
                        <p class="mt-1 text-pink-800">点击查看后会自动标记为已读。</p>
                    </div>
                    <a class="rounded-sm border border-pink-700 bg-white px-3 py-2 text-xs font-medium text-pink-800 hover:bg-pink-100" href="{{ $path('announcements.show', $popupAnnouncement) }}">查看公告</a>
                </div>
            </div>
        @endif

        <div class="min-w-0 {{ $wide ? '' : 'grid gap-4 lg:grid-cols-[230px_1fr]' }}">
            @unless($wide)
                <aside class="hidden space-y-4 lg:block">
                    <section class="rounded-sm border border-slate-300 bg-white">
                        <h2 class="border-b border-slate-200 bg-slate-100 px-3 py-2 text-sm font-semibold">商品分类</h2>
                        <nav class="divide-y divide-slate-100 text-sm">
                            <a class="block px-3 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('products.index') }}">全部商品</a>
                            @forelse($categories as $category)
                                <a class="block px-3 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('products.index', ['category' => $category->slug]) }}">{{ $category->name }}</a>
                            @empty
                                <span class="block px-3 py-2 text-slate-500">暂无分类</span>
                            @endforelse
                        </nav>
                    </section>

                    <section class="rounded-sm border border-slate-300 bg-white">
                        <h2 class="border-b border-slate-200 bg-slate-100 px-3 py-2 text-sm font-semibold">购物车</h2>
                        <div class="space-y-2 px-3 py-3 text-sm">
                            <p><span data-cart-count>{{ $cartCount }}</span> 件商品</p>
                            <p class="font-semibold">小计 <span data-cart-subtotal>@money($cartSubtotal)</span></p>
                            <a class="inline-flex w-full justify-center rounded-sm border border-emerald-700 bg-emerald-700 px-3 py-2 font-medium text-white hover:bg-emerald-800" href="{{ $path('cart.show') }}">结算购物车</a>
                        </div>
                    </section>

                    <section class="rounded-sm border border-slate-300 bg-white">
                        <h2 class="border-b border-slate-200 bg-slate-100 px-3 py-2 text-sm font-semibold">用户中心</h2>
                        <nav class="divide-y divide-slate-100 text-sm">
                            @auth
                                <a class="block px-3 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('user.center') }}">个人信息</a>
                                <a class="block px-3 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('orders.index') }}">我的订单</a>
                                <a class="relative block px-3 py-2 pr-12 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('user.section', 'chat') }}">
                                    聊天
                                    @if(($privateUnreadMessageCount ?? 0) > 0)
                                        <span class="absolute right-3 top-1.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 text-[10px] font-semibold leading-none text-white">{{ (int) $privateUnreadMessageCount > 99 ? '99+' : (int) $privateUnreadMessageCount }}</span>
                                    @endif
                                </a>
                                <a class="block px-3 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('support.index') }}">客服会话</a>
                                <a class="block px-3 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('support.demands') }}">客服工单</a>
                            @else
                                <a class="block px-3 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('login') }}">登录</a>
                                <a class="block px-3 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('register') }}">注册新账号</a>
                                <a class="block px-3 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('support.index') }}">游客客服</a>
                                <a class="block px-3 py-2 hover:bg-blue-50 hover:text-blue-800" href="{{ $path('support.demands') }}">客服工单</a>
                            @endauth
                        </nav>
                    </section>

                    @if($infoMenuItems->isNotEmpty() || $siteSettings?->contact_info)
                        <section class="rounded-sm border border-slate-300 bg-white">
                            <h2 class="border-b border-slate-200 bg-slate-100 px-3 py-2 text-sm font-semibold">信息</h2>
                            @if($infoMenuItems->isNotEmpty())
                                <nav class="divide-y divide-slate-100 text-sm">
                                    @foreach($infoMenuItems as $infoItem)
                                        @php($children = $menuChildren($infoItem))
                                        @continue(! $menuVisible($infoItem))
                                        @if($menuHasDestination($infoItem))
                                            <a
                                                class="block px-3 py-2 hover:bg-blue-50 hover:text-blue-800"
                                                href="{{ $menuUrl($infoItem) }}"
                                                @if($menuTooltip($infoItem)) title="{{ $menuTooltip($infoItem) }}" @endif
                                                @if($menuTarget($infoItem)) target="{{ $menuTarget($infoItem) }}" rel="noopener noreferrer" @endif
                                            >
                                                {{ $menuLabel($infoItem) }}
                                            </a>
                                        @else
                                            <div class="px-3 py-2">
                                                <p class="font-medium text-slate-700">{{ $menuLabel($infoItem) }}</p>
                                                <p class="mt-1 text-xs text-slate-500">请选择下方子菜单</p>
                                            </div>
                                        @endif
                                        @foreach($children as $child)
                                            @continue(! $menuVisible($child))
                                            @if($menuHasDestination($child))
                                                <a
                                                    class="block px-6 py-2 text-slate-600 hover:bg-blue-50 hover:text-blue-800"
                                                    href="{{ $menuUrl($child) }}"
                                                    @if($menuTooltip($child)) title="{{ $menuTooltip($child) }}" @endif
                                                    @if($menuTarget($child)) target="{{ $menuTarget($child) }}" rel="noopener noreferrer" @endif
                                                >
                                                    {{ $menuLabel($child) }}
                                                </a>
                                            @endif
                                        @endforeach
                                    @endforeach
                                </nav>
                            @endif
                            @if($siteSettings?->contact_info)
                                <p class="whitespace-pre-line border-t border-slate-100 px-3 py-3 text-sm text-slate-600">{{ $siteSettings->contact_info }}</p>
                            @endif
                        </section>
                    @endif
                </aside>
            @endunless

            <div class="min-w-0">
                {{ $slot }}
            </div>
        </div>
    </main>

    <footer class="mt-6 border-t border-slate-300 bg-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-6 text-sm text-slate-600">
            <span>{{ $siteSettings?->copyright_text ?: '© '.date('Y').' '.$storeName }}</span>
            @if($siteSettings?->contact_info)
                <span class="whitespace-pre-line text-right">{{ $siteSettings->contact_info }}</span>
            @endif
        </div>
    </footer>
    <button
        class="fixed bottom-5 left-5 z-40 inline-flex h-11 w-11 items-center justify-center rounded-full border border-blue-700 bg-white text-blue-800 shadow hover:bg-blue-50 md:hidden"
        type="button"
        data-mobile-menu-open
        aria-controls="site-mobile-menu"
        aria-expanded="false"
        aria-label="打开页面菜单"
    >
        <svg class="h-5 w-5" viewBox="0 0 20 20" aria-hidden="true" fill="currentColor"><path d="M3 5.75A.75.75 0 0 1 3.75 5h12.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 5.75Zm0 4A.75.75 0 0 1 3.75 9h12.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 9.75Zm.75 3.25a.75.75 0 0 0 0 1.5h12.5a.75.75 0 0 0 0-1.5H3.75Z"/></svg>
    </button>
    <button
        id="site-cart-target"
        class="fixed bottom-20 right-5 z-40 inline-flex h-12 w-12 items-center justify-center rounded-full border border-emerald-700 bg-white text-emerald-800 shadow hover:bg-emerald-50"
        type="button"
        data-floating-cart-toggle
        aria-controls="site-floating-cart"
        aria-expanded="false"
        aria-label="购物车 {{ $cartCount }} 件"
    >
        <svg class="h-6 w-6" viewBox="0 0 20 20" aria-hidden="true" fill="currentColor"><path d="M2.5 3a.75.75 0 0 0 0 1.5h1.06l1.45 7.24A2.25 2.25 0 0 0 7.22 13.5h6.86a2.25 2.25 0 0 0 2.16-1.62l1.08-3.79A1.75 1.75 0 0 0 15.64 5.85H5.3l-.35-1.76A1.75 1.75 0 0 0 3.24 3H2.5Zm4.25 13.5a1.25 1.25 0 1 0 0-2.5 1.25 1.25 0 0 0 0 2.5Zm7.5 0a1.25 1.25 0 1 0 0-2.5 1.25 1.25 0 0 0 0 2.5Z"/></svg>
        <span class="absolute -right-1 -top-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold leading-none text-white" data-cart-count>{{ $cartCount }}</span>
    </button>
    <div id="site-floating-cart" class="fixed bottom-36 right-5 z-40 hidden w-64 max-w-[calc(100vw-2rem)] rounded-sm border border-slate-300 bg-white p-4 text-sm text-slate-800 shadow-xl" data-floating-cart-panel>
        <div class="flex items-center justify-between gap-3">
            <p class="font-semibold">购物车</p>
            <button class="text-xs text-slate-500 hover:text-slate-900" type="button" data-floating-cart-close>关闭</button>
        </div>
        <div class="mt-3 space-y-2">
            <p><span data-cart-count>{{ $cartCount }}</span> 件商品</p>
            <p class="font-semibold">小计 <span data-cart-subtotal>@money($cartSubtotal)</span></p>
            <a class="inline-flex w-full justify-center rounded-sm border border-emerald-700 bg-emerald-700 px-3 py-2 font-medium text-white hover:bg-emerald-800" href="{{ $path('cart.show') }}">结算购物车</a>
        </div>
    </div>
    <a href="#top" onclick="window.scrollTo({ top: 0, behavior: 'smooth' }); return false;" class="fixed bottom-5 right-5 z-40 inline-flex h-10 w-10 items-center justify-center rounded-full border border-blue-700 bg-white text-blue-800 shadow hover:bg-blue-50" aria-label="回到顶部">↑</a>
</body>
</html>
