<section class="space-y-4">
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
        <form method="get" action="{{ \App\Support\Url::route('pages.show', $page) }}" class="grid gap-2 px-4 py-4 sm:grid-cols-[1fr_auto]">
            <input class="rounded-sm border border-slate-300 px-3 py-2 text-sm" type="search" name="q" value="{{ $keyword }}" placeholder="搜索商品、用户 ID 或用户昵称">
            <button class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800" type="submit">搜索</button>
        </form>
    </section>

    <section class="rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
            <h2 class="text-base font-semibold">商品</h2>
        </div>
        <div class="grid gap-px bg-slate-200 sm:grid-cols-2 xl:grid-cols-4">
            @forelse($products as $product)
                <x-product-card :product="$product" />
            @empty
                <p class="col-span-full bg-white px-4 py-8 text-sm text-slate-600">暂无商品结果。</p>
            @endforelse
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $products->links() }}</div>
    </section>

    <section class="rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
            <h2 class="text-base font-semibold">用户</h2>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($users as $user)
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                    <a class="font-medium hover:text-blue-800" href="{{ \App\Support\Url::route('users.show', $user) }}">{{ $user->displayName() }}</a>
                    <span class="text-slate-600">ID：{{ $user->public_id }}</span>
                    @auth
                        @if(auth()->id() !== $user->id)
                            <a class="rounded-sm border border-blue-700 bg-blue-700 px-3 py-2 text-xs font-medium text-white hover:bg-blue-800" href="{{ \App\Support\Url::route('messages.thread', $user) }}">私聊</a>
                        @endif
                    @endauth
                </div>
            @empty
                <p class="px-4 py-8 text-sm text-slate-600">暂无用户结果。</p>
            @endforelse
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $users->links() }}</div>
    </section>
</section>
