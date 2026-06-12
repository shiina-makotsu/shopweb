<x-layouts.app title="购物车">
    <section class="rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
            <h1 class="text-lg font-semibold">购物车</h1>
        </div>

        @if($items->isEmpty())
            <div class="px-4 py-10 text-sm text-slate-600">
                购物车为空。
                <a class="ml-2 text-blue-700 hover:text-blue-900" href="{{ route('products.index') }}">继续购物</a>
            </div>
        @else
            <div class="divide-y divide-slate-100 md:hidden">
                @foreach($items as $item)
                    <article class="p-4">
                        <div class="flex gap-3">
                            @if($item['product']->coverMedia)
                                <img src="{{ $item['product']->coverMedia->url() }}" alt="{{ $item['product']->title }}" class="h-20 w-20 shrink-0 rounded-sm border border-slate-200 object-cover">
                            @else
                                <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-sm bg-slate-100 text-xs text-slate-500">无图</div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <a class="block truncate font-medium hover:text-blue-800" href="{{ route('products.show', $item['product']) }}">{{ $item['product']->title }}</a>
                                <p class="mt-1 text-xs text-slate-500">SKU {{ $item['variant']->sku }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ $item['variant']->specLabel() }}</p>
                                <div class="mt-2 flex items-center justify-between gap-3 text-sm">
                                    <span>@money($item['unit_price_cents'])</span>
                                    <span class="font-semibold">@money($item['line_total_cents'])</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
                            <form method="post" action="{{ route('cart.items.update', $item['variant']) }}" class="grid grid-cols-[1fr_auto] gap-2">
                                @csrf
                                @method('patch')
                                <input class="min-h-10 w-full rounded-sm border border-slate-300 px-2 py-2 text-center" type="number" name="quantity" min="0" max="{{ \App\Services\CartService::MAX_ITEM_QUANTITY }}" value="{{ $item['quantity'] }}">
                                <button class="min-h-10 rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-50" type="submit">更新</button>
                            </form>
                            <form method="post" action="{{ route('cart.items.destroy', $item['variant']) }}">
                                @csrf
                                @method('delete')
                                <button class="min-h-10 w-full rounded-sm border border-red-300 bg-white px-3 py-2 text-sm text-red-700 hover:bg-red-50" type="submit">移除</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[720px] text-sm">
                    <thead class="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">商品</th>
                            <th class="px-4 py-3 font-medium">规格</th>
                            <th class="px-4 py-3 text-right font-medium">单价</th>
                            <th class="px-4 py-3 text-center font-medium">数量</th>
                            <th class="px-4 py-3 text-right font-medium">小计</th>
                            <th class="px-4 py-3 text-right font-medium">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($items as $item)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        @if($item['product']->coverMedia)
                                            <img src="{{ $item['product']->coverMedia->url() }}" alt="{{ $item['product']->title }}" class="h-14 w-14 rounded-sm border border-slate-200 object-cover">
                                        @else
                                            <div class="flex h-14 w-14 items-center justify-center rounded-sm bg-slate-100 text-xs text-slate-500">无图</div>
                                        @endif
                                        <div>
                                            <a class="font-medium hover:text-blue-800" href="{{ route('products.show', $item['product']) }}">{{ $item['product']->title }}</a>
                                            <p class="mt-1 text-xs text-slate-500">SKU {{ $item['variant']->sku }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $item['variant']->specLabel() }}</td>
                                <td class="px-4 py-3 text-right">@money($item['unit_price_cents'])</td>
                                <td class="px-4 py-3">
                                    <form method="post" action="{{ route('cart.items.update', $item['variant']) }}" class="flex justify-center gap-2">
                                        @csrf
                                        @method('patch')
                                        <input class="w-20 rounded-sm border border-slate-300 px-2 py-1 text-center" type="number" name="quantity" min="0" max="{{ \App\Services\CartService::MAX_ITEM_QUANTITY }}" value="{{ $item['quantity'] }}">
                                        <button class="rounded-sm border border-slate-300 bg-white px-3 py-1 hover:bg-slate-50" type="submit">更新</button>
                                    </form>
                                </td>
                                <td class="px-4 py-3 text-right font-medium">@money($item['line_total_cents'])</td>
                                <td class="px-4 py-3 text-right">
                                    <form method="post" action="{{ route('cart.items.destroy', $item['variant']) }}">
                                        @csrf
                                        @method('delete')
                                        <button class="rounded-sm border border-red-300 bg-white px-3 py-1 text-red-700 hover:bg-red-50" type="submit">移除</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="grid gap-4 border-t border-slate-200 bg-slate-50 px-4 py-4 md:grid-cols-[1fr_320px]">
                <div class="text-sm text-slate-600">
                    <p>{{ $requiresShipping ? '此订单包含需要收货地址的商品。' : '此订单只需要联系方式，管理员确认后联系交付。' }}</p>
                    <a class="mt-3 inline-flex rounded-sm border border-slate-300 bg-white px-4 py-2 font-medium text-slate-800 hover:bg-slate-50" href="{{ route('products.index') }}">继续购物</a>
                </div>
                <div class="rounded-sm border border-slate-300 bg-white p-4">
                    <div class="flex justify-between text-sm">
                        <span>商品小计</span>
                        <span class="font-semibold">@money($subtotalCents)</span>
                    </div>
                    <a href="{{ route('checkout.create') }}" class="mt-4 inline-flex w-full justify-center rounded-sm border border-emerald-700 bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">去结算</a>
                </div>
            </div>
        @endif
    </section>
</x-layouts.app>
