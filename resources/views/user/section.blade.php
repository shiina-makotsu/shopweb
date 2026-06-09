@php
    $titles = [
        'wishlists' => '愿望单',
        'favorites' => '收藏商品',
        'addresses' => '地址设置',
        'privacy' => '隐私设置',
        'interface' => '界面设置',
        'membership' => '注册会员',
    ];
@endphp

<x-layouts.app :title="$titles[$section] ?? '用户中心'">
    <section class="rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-2">
            <h1 class="text-lg font-semibold">{{ $titles[$section] ?? '用户中心' }}</h1>
        </div>

        @if($section === 'wishlists')
            <div class="grid gap-px bg-slate-200 sm:grid-cols-2 xl:grid-cols-4">
                @forelse($wishlists as $item)
                    @if($item->product)
                        <x-product-card :product="$item->product" />
                    @endif
                @empty
                    <p class="col-span-full bg-white px-4 py-8 text-sm text-slate-600">暂无愿望单商品。</p>
                @endforelse
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $wishlists->links() }}</div>
        @elseif($section === 'favorites')
            <div class="grid gap-px bg-slate-200 sm:grid-cols-2 xl:grid-cols-4">
                @forelse($favorites as $item)
                    @if($item->product)
                        <x-product-card :product="$item->product" />
                    @endif
                @empty
                    <p class="col-span-full bg-white px-4 py-8 text-sm text-slate-600">暂无收藏商品。</p>
                @endforelse
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $favorites->links() }}</div>
        @else
            <div class="px-4 py-10 text-sm leading-6 text-slate-600">
                功能暂未开放。
            </div>
        @endif
    </section>
</x-layouts.app>
