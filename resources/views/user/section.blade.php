@php
    $titles = [
        'profile' => '个人资料',
        'wishlists' => '愿望单',
        'favorites' => '收藏商品',
        'addresses' => '地址设置',
        'coupons' => '优惠码',
        'chat' => '聊天',
        'ai' => 'AI 配额',
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
                        <input id="avatar-input" class="mt-2 block w-full max-w-sm text-sm text-slate-700 file:mr-3 file:rounded-sm file:border-0 file:bg-blue-700 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-blue-800" type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp">
                        <input id="avatar-cropped" type="hidden" name="avatar_cropped">
                        <span class="mt-1 block text-xs text-slate-500">选择图片后会先打开裁剪窗口，保存裁剪后的圆形头像。</span>
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
                        <span class="text-sm font-medium text-slate-700">用户昵称</span>
                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" type="text" name="name" value="{{ old('name', $user->name) }}" required>
                        @error('name')
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

            <dialog id="avatar-crop-dialog" class="rounded-sm border border-slate-300 p-0 shadow-xl backdrop:bg-slate-900/40">
                <div class="w-[min(92vw,520px)] space-y-4 bg-white p-5">
                    <div>
                        <h2 class="text-lg font-semibold">裁剪头像</h2>
                        <p class="mt-1 text-sm text-slate-600">拖动滑块调整缩放，系统会保存中间方形区域作为头像。</p>
                    </div>
                    <div class="grid place-items-center rounded-sm border border-slate-200 bg-slate-50 p-3">
                        <canvas id="avatar-crop-canvas" width="320" height="320" class="h-80 w-80 rounded-full border border-slate-300 bg-white"></canvas>
                    </div>
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">缩放</span>
                        <input id="avatar-crop-zoom" class="mt-2 w-full" type="range" min="1" max="3" step="0.05" value="1">
                    </label>
                    <div class="flex justify-end gap-2">
                        <button id="avatar-crop-cancel" class="rounded-sm border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50" type="button">取消</button>
                        <button id="avatar-crop-apply" class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800" type="button">使用裁剪头像</button>
                    </div>
                </div>
            </dialog>

            <script>
                (() => {
                    const input = document.getElementById('avatar-input');
                    const hidden = document.getElementById('avatar-cropped');
                    const dialog = document.getElementById('avatar-crop-dialog');
                    const canvas = document.getElementById('avatar-crop-canvas');
                    const zoom = document.getElementById('avatar-crop-zoom');
                    const cancel = document.getElementById('avatar-crop-cancel');
                    const apply = document.getElementById('avatar-crop-apply');
                    const ctx = canvas?.getContext('2d');
                    let image = null;

                    const draw = () => {
                        if (!ctx || !image) return;
                        const scale = Number(zoom.value || 1);
                        const size = Math.min(image.width, image.height) / scale;
                        const sx = (image.width - size) / 2;
                        const sy = (image.height - size) / 2;
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                        ctx.drawImage(image, sx, sy, size, size, 0, 0, canvas.width, canvas.height);
                    };

                    input?.addEventListener('change', () => {
                        const file = input.files?.[0];
                        if (!file || !file.type.startsWith('image/')) return;
                        const reader = new FileReader();
                        reader.onload = () => {
                            image = new Image();
                            image.onload = () => {
                                zoom.value = '1';
                                draw();
                                dialog?.showModal();
                            };
                            image.src = String(reader.result);
                        };
                        reader.readAsDataURL(file);
                    });

                    zoom?.addEventListener('input', draw);
                    cancel?.addEventListener('click', () => {
                        hidden.value = '';
                        input.value = '';
                        dialog?.close();
                    });
                    apply?.addEventListener('click', () => {
                        draw();
                        hidden.value = canvas.toDataURL('image/png');
                        dialog?.close();
                    });
                })();
            </script>
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
        @elseif($section === 'coupons')
            <div class="space-y-5 px-4 py-5">
                @if(session('status'))
                    <div class="rounded-sm border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                        {{ session('status') }}
                    </div>
                @endif

                <section class="rounded-sm border border-slate-300">
                    <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">添加优惠码</h2>
                    <form class="grid gap-3 p-4 sm:grid-cols-[1fr_auto]" method="POST" action="{{ route('user.coupons.store') }}">
                        @csrf
                        <label class="block">
                            <span class="sr-only">优惠码</span>
                            <input class="w-full rounded-sm border border-slate-300 px-3 py-2 text-sm uppercase" name="coupon_code" value="{{ old('coupon_code') }}" placeholder="输入优惠码" required>
                            @error('coupon_code')
                                <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                            @enderror
                        </label>
                        <button class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800" type="submit">添加</button>
                    </form>
                </section>

                <section class="rounded-sm border border-slate-300">
                    <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">我的优惠码</h2>
                    <div class="divide-y divide-slate-100">
                        @forelse($coupons as $userCoupon)
                            @php
                                $coupon = $userCoupon->coupon;
                            @endphp
                            @if($coupon)
                                <article class="grid gap-3 px-4 py-4 text-sm md:grid-cols-[1fr_auto]">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="font-semibold">{{ $coupon->name }}</h3>
                                            <span class="rounded-sm border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs">{{ $userCoupon->statusLabel() }}</span>
                                        </div>
                                        <p class="mt-1 text-slate-600">代码：<code class="rounded-sm bg-slate-100 px-1.5 py-0.5">{{ $coupon->code }}</code></p>
                                        <p class="mt-1 text-slate-600">范围：{{ $coupon->scopeLabel() }}</p>
                                        <p class="mt-1 text-slate-600">
                                            时间：
                                            {{ $coupon->starts_at?->format('Y-m-d H:i') ?? '不限开始' }}
                                            -
                                            {{ $coupon->ends_at?->format('Y-m-d H:i') ?? '不限结束' }}
                                        </p>
                                    </div>
                                    <div class="text-left md:text-right">
                                        <p class="text-lg font-semibold text-red-700">{{ $coupon->discountLabel() }}</p>
                                        @if($coupon->minimum_order_cents > 0)
                                            <p class="mt-1 text-xs text-slate-500">满 @money($coupon->minimum_order_cents) 可用</p>
                                        @else
                                            <p class="mt-1 text-xs text-slate-500">无最低金额</p>
                                        @endif
                                    </div>
                                </article>
                            @endif
                        @empty
                            <p class="px-4 py-8 text-sm text-slate-600">暂无已添加的优惠码。</p>
                        @endforelse
                    </div>
                </section>
            </div>
        @elseif($section === 'chat')
            @php
                $chatThreads = collect($chatThreads ?? []);
            @endphp
            <div class="space-y-4 px-4 py-5">
                <div class="rounded-sm border border-slate-300">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <div>
                            <h2 class="text-sm font-semibold">私聊会话</h2>
                            <p class="mt-1 text-xs text-slate-500">只显示你参与过的私聊，点击会话进入聊天页面。</p>
                        </div>
                        @if(($privateUnreadCount ?? 0) > 0)
                            <span class="inline-flex items-center rounded-full bg-red-600 px-2.5 py-1 text-xs font-semibold text-white">
                                未读 {{ (int) $privateUnreadCount > 99 ? '99+' : (int) $privateUnreadCount }}
                            </span>
                        @endif
                    </div>
                    <div class="divide-y divide-slate-100">
                        @forelse($chatThreads as $thread)
                            @php
                                $threadUser = $thread['user'];
                                $lastMessage = $thread['last_message'];
                                $unreadCount = (int) $thread['unread_count'];
                                $messageText = $lastMessage->body !== null && $lastMessage->body !== ''
                                    ? $lastMessage->body
                                    : ($lastMessage->attachment_original_name ? '附件：'.$lastMessage->attachment_original_name : '暂无文字内容');
                            @endphp
                            <a class="grid gap-3 px-4 py-4 hover:bg-blue-50 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center" href="{{ route('messages.thread', $threadUser) }}">
                                <div class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-full border border-slate-300 bg-white text-sm font-semibold text-blue-700">
                                    @if($threadUser->avatar_path)
                                        <img class="h-full w-full object-cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public_uploads')->url($threadUser->avatar_path) }}" alt="{{ $threadUser->displayName() }}">
                                    @else
                                        {{ mb_substr($threadUser->displayName(), 0, 1) }}
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $threadUser->displayName() }}</p>
                                        <span class="shrink-0 text-xs text-slate-500">ID：{{ $threadUser->public_id }}</span>
                                    </div>
                                    <p class="mt-1 truncate text-sm text-slate-600">{{ $lastMessage->sender_id === auth()->id() ? '我：' : '' }}{{ \Illuminate\Support\Str::limit($messageText, 80) }}</p>
                                </div>
                                <div class="flex items-center gap-2 text-xs text-slate-500 sm:flex-col sm:items-end">
                                    <span>{{ $lastMessage->created_at?->format('Y-m-d H:i') }}</span>
                                    @if($unreadCount > 0)
                                        <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 text-[10px] font-semibold leading-none text-white">
                                            {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                                        </span>
                                    @endif
                                </div>
                            </a>
                        @empty
                            <p class="px-4 py-10 text-center text-sm text-slate-600">暂无私聊会话。</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @elseif($section === 'ai')
            @php
                $breakdown = collect($aiQuota['model_breakdown'] ?? []);
                $maxTokens = max(1, (int) $breakdown->max('total_tokens'));
                $quotaUnlimited = (bool) ($aiQuota['quota_unlimited'] ?? false);
            @endphp
            <div class="space-y-5 px-4 py-5">
                <div class="grid gap-3 md:grid-cols-4">
                    <div class="rounded-sm border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs text-slate-500">AI 余额</p>
                        <p class="mt-1 text-2xl font-semibold">{{ $quotaUnlimited ? '不限额' : number_format((int) ($aiQuota['remaining_k'] ?? 0)).'k' }}</p>
                    </div>
                    <div class="rounded-sm border border-slate-200 bg-white px-4 py-3">
                        <p class="text-xs text-slate-500">配额上限</p>
                        <p class="mt-1 text-2xl font-semibold">{{ $quotaUnlimited ? '不限额' : number_format((int) ($aiQuota['limit_k'] ?? 0)).'k' }}</p>
                    </div>
                    <div class="rounded-sm border border-slate-200 bg-white px-4 py-3">
                        <p class="text-xs text-slate-500">总用量</p>
                        <p class="mt-1 text-2xl font-semibold">{{ number_format((int) ($aiQuota['total_tokens'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-sm border border-slate-200 bg-white px-4 py-3">
                        <p class="text-xs text-slate-500">24h 用量</p>
                        <p class="mt-1 text-2xl font-semibold">{{ number_format((int) ($aiQuota['tokens_24h'] ?? 0)) }}</p>
                    </div>
                </div>

                <section class="rounded-sm border border-slate-300">
                    <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">模型用量</h2>
                    <div class="space-y-3 p-4">
                        @forelse($breakdown as $row)
                            <div class="grid gap-2 text-sm md:grid-cols-[12rem_1fr_7rem] md:items-center">
                                <div class="truncate font-medium text-slate-700">{{ $row->model }}</div>
                                <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-blue-600" style="width: {{ max(4, min(100, ((int) $row->total_tokens / $maxTokens) * 100)) }}%"></div>
                                </div>
                                <div class="text-right text-slate-600">{{ number_format((int) $row->total_tokens) }}</div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-600">暂无 AI 使用记录。</p>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-sm border border-slate-300">
                    <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold">使用记录</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-4 py-2">请求时间</th>
                                    <th class="px-4 py-2">请求耗时</th>
                                    <th class="px-4 py-2">模型</th>
                                    <th class="px-4 py-2">token 参数</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse(($aiQuota['recent_logs'] ?? []) as $log)
                                    <tr>
                                        <td class="px-4 py-3 text-slate-700">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                                        <td class="px-4 py-3 text-slate-700">{{ $log->request_ms ? number_format($log->request_ms / 1000, 2).'s' : '-' }}</td>
                                        <td class="px-4 py-3 text-slate-700">{{ $log->model ?: '-' }}</td>
                                        <td class="px-4 py-3 text-slate-700">
                                            {{ number_format((int) $log->token_count) }}
                                            @if($log->config_name)
                                                <span class="ml-2 rounded-sm bg-slate-100 px-2 py-0.5 text-xs text-slate-500">{{ $log->config_name }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-4 py-8 text-center text-slate-600" colspan="4">暂无使用记录。</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
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
