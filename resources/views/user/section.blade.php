@php
    $titles = [
        'profile' => '个人资料',
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

        @if($section === 'profile')
            <form class="space-y-5 px-4 py-5" method="POST" action="{{ route('user.profile.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PATCH')

                @if(session('status'))
                    <div class="rounded-sm border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                        {{ session('status') }}
                    </div>
                @endif

                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full border border-slate-300 bg-slate-50 text-2xl font-semibold text-blue-700">
                        @if($user->avatar_path)
                            <img class="h-full w-full object-cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public_uploads')->url($user->avatar_path) }}" alt="{{ $user->displayName() }}">
                        @else
                            {{ mb_substr($user->displayName(), 0, 1) }}
                        @endif
                    </div>
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">头像</span>
                        <input class="mt-2 block w-full max-w-sm text-sm text-slate-700 file:mr-3 file:rounded-sm file:border-0 file:bg-blue-700 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-blue-800" type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp">
                        @error('avatar')
                            <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                        @enderror
                    </label>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">用户 ID</span>
                        <input class="mt-1 w-full rounded-sm border border-slate-300 bg-slate-100 px-3 py-2 text-sm text-slate-600" type="text" value="{{ $user->public_id }}" disabled>
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">邮箱</span>
                        <input class="mt-1 w-full rounded-sm border border-slate-300 bg-slate-100 px-3 py-2 text-sm text-slate-600" type="email" value="{{ $user->email }}" disabled>
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">用户名</span>
                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" type="text" name="name" value="{{ old('name', $user->name) }}" required>
                        @error('name')
                            <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                        @enderror
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">昵称</span>
                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" type="text" name="nickname" value="{{ old('nickname', $user->nickname) }}">
                        @error('nickname')
                            <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                        @enderror
                    </label>
                </div>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">个人简介</span>
                    <textarea class="mt-1 min-h-32 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm leading-6" name="profile_intro" maxlength="1000">{{ old('profile_intro', $user->profile_intro) }}</textarea>
                    @error('profile_intro')
                        <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </label>

                <div class="flex flex-wrap gap-3">
                    <button class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800" type="submit">保存资料</button>
                    <a class="rounded-sm border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50" href="{{ route('users.show', $user) }}">查看公开主页</a>
                </div>
            </form>
        @elseif($section === 'wishlists')
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
