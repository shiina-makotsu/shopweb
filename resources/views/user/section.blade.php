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
        @elseif($section === 'addresses')
            <div class="space-y-5 px-4 py-5">
                @if(session('status'))
                    <div class="rounded-sm border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                        {{ session('status') }}
                    </div>
                @endif

                <section class="rounded-sm border border-slate-300">
                    <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">新增地址</h2>
                    <form class="grid gap-4 p-4 md:grid-cols-2" method="POST" action="{{ route('user.addresses.store') }}">
                        @csrf
                        <label class="block md:col-span-2">
                            <span class="text-sm font-medium text-slate-700">智能识别地址</span>
                            <textarea class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm leading-6" name="raw_text" rows="2" placeholder="例如：中国北京市朝阳区某某街道 88 号">{{ old('raw_text') }}</textarea>
                            <span class="mt-1 block text-xs text-slate-500">提交时会尝试从这段文字里识别国家、省、市、区/县和街道；你也可以手动填写或修正下面的字段。</span>
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">收件人</span>
                            <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="recipient_name" value="{{ old('recipient_name') }}" required>
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">电话号码</span>
                            <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="phone" value="{{ old('phone') }}" required>
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">国家</span>
                            <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="country" value="{{ old('country', '中国') }}" required>
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">省</span>
                            <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="province" value="{{ old('province') }}" required>
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">市</span>
                            <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="city" value="{{ old('city') }}" required>
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">区/县</span>
                            <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="district" value="{{ old('district') }}" required>
                        </label>
                        <label class="block md:col-span-2">
                            <span class="text-sm font-medium text-slate-700">街道</span>
                            <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="street" value="{{ old('street') }}">
                        </label>
                        <div class="flex flex-wrap gap-4 md:col-span-2">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" name="is_default" value="1">
                                设为默认地址
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" name="is_visible" value="1">
                                在公开主页显示该地址
                            </label>
                        </div>
                        @if($errors->any())
                            <div class="rounded-sm border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 md:col-span-2">
                                {{ $errors->first() }}
                            </div>
                        @endif
                        <div class="md:col-span-2">
                            <button class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800" type="submit">保存地址</button>
                        </div>
                    </form>
                </section>

                <section class="rounded-sm border border-slate-300">
                    <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">我的地址</h2>
                    <div class="divide-y divide-slate-100">
                        @forelse($addresses as $address)
                            <article class="p-4">
                                <form class="grid gap-3 md:grid-cols-2" method="POST" action="{{ route('user.addresses.update', $address) }}">
                                    @csrf
                                    @method('PATCH')
                                    <label class="block">
                                        <span class="text-xs text-slate-500">收件人</span>
                                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="recipient_name" value="{{ old('recipient_name', $address->recipient_name) }}" required>
                                    </label>
                                    <label class="block">
                                        <span class="text-xs text-slate-500">电话</span>
                                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="phone" value="{{ old('phone', $address->phone) }}" required>
                                    </label>
                                    <label class="block">
                                        <span class="text-xs text-slate-500">国家</span>
                                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="country" value="{{ old('country', $address->country) }}" required>
                                    </label>
                                    <label class="block">
                                        <span class="text-xs text-slate-500">省</span>
                                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="province" value="{{ old('province', $address->province) }}" required>
                                    </label>
                                    <label class="block">
                                        <span class="text-xs text-slate-500">市</span>
                                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="city" value="{{ old('city', $address->city) }}" required>
                                    </label>
                                    <label class="block">
                                        <span class="text-xs text-slate-500">区/县</span>
                                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="district" value="{{ old('district', $address->district) }}" required>
                                    </label>
                                    <label class="block md:col-span-2">
                                        <span class="text-xs text-slate-500">街道</span>
                                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="street" value="{{ old('street', $address->street) }}">
                                    </label>
                                    <div class="flex flex-wrap items-center gap-4 md:col-span-2">
                                        <label class="inline-flex items-center gap-2 text-sm">
                                            <input type="checkbox" name="is_default" value="1" @checked($address->is_default)>
                                            默认地址
                                        </label>
                                        <label class="inline-flex items-center gap-2 text-sm">
                                            <input type="checkbox" name="is_visible" value="1" @checked($address->is_visible)>
                                            公开可见
                                        </label>
                                        <button class="rounded-sm border border-blue-700 bg-blue-700 px-3 py-2 text-sm font-medium text-white hover:bg-blue-800" type="submit">保存</button>
                                    </div>
                                </form>
                                <div class="mt-3 flex flex-wrap gap-2 text-sm">
                                    @unless($address->is_default)
                                        <form method="POST" action="{{ route('user.addresses.default', $address) }}">
                                            @csrf
                                            <button class="rounded-sm border border-slate-300 px-3 py-2 hover:bg-slate-50" type="submit">设为默认</button>
                                        </form>
                                    @endunless
                                    <form method="POST" action="{{ route('user.addresses.destroy', $address) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded-sm border border-red-200 px-3 py-2 text-red-700 hover:bg-red-50" type="submit">删除</button>
                                    </form>
                                </div>
                            </article>
                        @empty
                            <p class="px-4 py-8 text-sm text-slate-600">暂无地址。</p>
                        @endforelse
                    </div>
                </section>
            </div>
        @else
            <div class="px-4 py-10 text-sm leading-6 text-slate-600">
                功能暂未开放。
            </div>
        @endif
    </section>
</x-layouts.app>
