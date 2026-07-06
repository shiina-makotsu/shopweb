@php
    $privateUnreadCount = (int) ($privateUnreadCount ?? 0);
    $privateUnreadBadge = $privateUnreadCount > 99 ? '99+' : (string) $privateUnreadCount;
@endphp

<x-layouts.app title="用户中心">
    <section class="mb-4 rounded-sm border border-slate-300 bg-white">
        <div class="flex flex-wrap items-center gap-4 border-b border-slate-200 bg-slate-100 px-4 py-4">
            <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-full border border-slate-300 bg-white text-xl font-semibold text-blue-700">
                @if($user->avatar_path)
                    <img class="h-full w-full object-cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public_uploads')->url($user->avatar_path) }}" alt="{{ $user->name }}">
                @else
                    {{ mb_substr($user->name, 0, 1) }}
                @endif
            </div>
            <div>
                <h1 class="text-xl font-semibold">{{ $user->name }}</h1>
                <p class="mt-1 text-sm text-slate-600">用户 ID：{{ $user->public_id }} / {{ $user->email }}</p>
                @if($user->profile_intro)
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">{{ \Illuminate\Support\Str::limit($user->profile_intro, 120) }}</p>
                @endif
                <a class="mt-2 inline-flex text-sm font-medium text-blue-700 hover:text-blue-900" href="{{ route('user.section', 'profile') }}">编辑个人资料</a>
            </div>
        </div>

        <div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
            <a class="rounded-sm border border-slate-200 bg-white px-4 py-3 hover:border-blue-300 hover:bg-blue-50" href="{{ route('orders.index') }}">
                <p class="text-xs text-slate-500">待付款</p>
                <p class="mt-1 text-2xl font-semibold">{{ $pendingPaymentCount }}</p>
            </a>
            <a class="rounded-sm border border-slate-200 bg-white px-4 py-3 hover:border-blue-300 hover:bg-blue-50" href="{{ route('orders.index') }}">
                <p class="text-xs text-slate-500">待发货 / 进货中</p>
                <p class="mt-1 text-2xl font-semibold">{{ $pendingShipmentCount }}</p>
            </a>
            <a class="rounded-sm border border-slate-200 bg-white px-4 py-3 hover:border-blue-300 hover:bg-blue-50" href="{{ route('orders.index') }}">
                <p class="text-xs text-slate-500">待收货</p>
                <p class="mt-1 text-2xl font-semibold">{{ $awaitingReceiptCount }}</p>
            </a>
            <a class="rounded-sm border border-slate-200 bg-white px-4 py-3 hover:border-blue-300 hover:bg-blue-50" href="{{ route('orders.index') }}">
                <p class="text-xs text-slate-500">已完成</p>
                <p class="mt-1 text-2xl font-semibold">{{ $fulfilledCount }}</p>
            </a>
            <a class="rounded-sm border border-slate-200 bg-white px-4 py-3 hover:border-blue-300 hover:bg-blue-50" href="{{ route('user.section', 'wallet') }}">
                <p class="text-xs text-slate-500">钱包余额</p>
                <p class="mt-1 text-2xl font-semibold">@money((int) $user->wallet_balance_cents)</p>
            </a>
            <a class="rounded-sm border border-slate-200 bg-white px-4 py-3 hover:border-blue-300 hover:bg-blue-50" href="{{ route('user.section', 'invitations') }}">
                <p class="text-xs text-slate-500">已邀请</p>
                <p class="mt-1 text-2xl font-semibold">{{ $user->referrals_count }}</p>
            </a>
        </div>
    </section>

    <section class="mb-4 grid gap-4 lg:grid-cols-[1fr_320px]">
        <div class="rounded-sm border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
                <h2 class="text-base font-semibold">浏览记录</h2>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($recentHistories as $history)
                    @if($history->product)
                        <a class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50" href="{{ $history->product->showUrl() }}">
                            @if($history->product->coverMedia)
                                <img class="h-12 w-12 rounded-sm object-cover" src="{{ $history->product->coverMedia->url() }}" alt="{{ $history->product->title }}">
                            @else
                                <div class="h-12 w-12 rounded-sm bg-slate-100"></div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ $history->product->title }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $history->viewed_at?->format('Y-m-d H:i') }} / {{ $history->view_count }} 次</p>
                            </div>
                        </a>
                    @endif
                @empty
                    <p class="px-4 py-8 text-sm text-slate-600">暂无浏览记录。</p>
                @endforelse
            </div>
        </div>

        <aside class="space-y-4">
            <div class="rounded-sm border border-slate-300 bg-white">
                <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
                    <h2 class="text-base font-semibold">商品偏好</h2>
                </div>
                <dl class="space-y-2 px-4 py-4 text-sm">
                    <a class="flex justify-between rounded-sm px-2 py-2 hover:bg-blue-50" href="{{ route('user.section', 'wishlists') }}"><span>愿望单</span><span>{{ $user->wishlists_count }}</span></a>
                    <a class="flex justify-between rounded-sm px-2 py-2 hover:bg-blue-50" href="{{ route('user.section', 'favorites') }}"><span>收藏商品</span><span>{{ $user->favorites_count }}</span></a>
                    <a class="flex justify-between rounded-sm px-2 py-2 hover:bg-blue-50" href="{{ route('user.section', 'coupons') }}"><span>优惠码</span><span></span></a>
                    <a class="flex justify-between rounded-sm px-2 py-2 hover:bg-blue-50" href="{{ route('user.section', 'invitations') }}"><span>邀请</span><span>{{ $user->referrals_count }}</span></a>
                    <a class="flex justify-between rounded-sm px-2 py-2 hover:bg-blue-50" href="{{ route('user.section', 'wallet') }}"><span>钱包</span><span>@money((int) $user->wallet_balance_cents)</span></a>
                    <a class="relative flex justify-between rounded-sm px-2 py-2 pr-12 hover:bg-blue-50" href="{{ route('user.section', 'chat') }}">
                        <span>聊天</span>
                        @if($privateUnreadCount > 0)
                            <span class="absolute right-2 top-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 text-[10px] font-semibold leading-none text-white">{{ $privateUnreadBadge }}</span>
                        @endif
                    </a>
                    <a class="flex justify-between rounded-sm px-2 py-2 hover:bg-blue-50" href="{{ route('user.section', 'ai') }}"><span>AI 配额</span><span></span></a>
                </dl>
            </div>

            <div class="rounded-sm border border-slate-300 bg-white">
                <div class="border-b border-slate-200 bg-slate-100 px-4 py-3">
                    <h2 class="text-base font-semibold">设置</h2>
                </div>
                <div class="grid gap-2 px-4 py-4 text-sm">
                    <a class="rounded-sm border border-slate-200 px-3 py-2 text-slate-600 hover:bg-blue-50 hover:text-blue-800" href="{{ route('user.section', 'profile') }}">个人资料</a>
                    <a class="rounded-sm border border-slate-200 px-3 py-2 text-slate-600 hover:bg-blue-50 hover:text-blue-800" href="{{ route('user.section', 'coupons') }}">优惠码</a>
                    <a class="rounded-sm border border-slate-200 px-3 py-2 text-slate-600 hover:bg-blue-50 hover:text-blue-800" href="{{ route('user.section', 'invitations') }}">邀请</a>
                    <a class="rounded-sm border border-slate-200 px-3 py-2 text-slate-600 hover:bg-blue-50 hover:text-blue-800" href="{{ route('user.section', 'wallet') }}">钱包</a>
                    <a class="relative rounded-sm border border-slate-200 px-3 py-2 pr-12 text-slate-600 hover:bg-blue-50 hover:text-blue-800" href="{{ route('user.section', 'chat') }}">
                        聊天
                        @if($privateUnreadCount > 0)
                            <span class="absolute right-2 top-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 text-[10px] font-semibold leading-none text-white">{{ $privateUnreadBadge }}</span>
                        @endif
                    </a>
                    <a class="rounded-sm border border-slate-200 px-3 py-2 text-slate-600 hover:bg-blue-50 hover:text-blue-800" href="{{ route('user.section', 'addresses') }}">地址设置</a>
                    <a class="rounded-sm border border-slate-200 px-3 py-2 text-slate-600 hover:bg-blue-50 hover:text-blue-800" href="{{ route('user.section', 'privacy') }}">隐私设置</a>
                    <a class="rounded-sm border border-slate-200 px-3 py-2 text-slate-600 hover:bg-blue-50 hover:text-blue-800" href="{{ route('user.section', 'interface') }}">界面设置</a>
                    <a class="rounded-sm border border-slate-200 px-3 py-2 text-slate-600 hover:bg-blue-50 hover:text-blue-800" href="{{ route('user.section', 'membership') }}">注册会员</a>
                </div>
            </div>
        </aside>
    </section>
</x-layouts.app>
