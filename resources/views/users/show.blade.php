<x-layouts.app :title="$profileUser->displayName()">
    <section class="rounded-sm border border-slate-300 bg-white">
        <div class="flex flex-wrap items-center gap-4 border-b border-slate-200 bg-slate-100 px-4 py-4">
            <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-full border border-slate-300 bg-white text-xl font-semibold text-blue-700">
                @if($profileUser->avatar_path)
                    <img class="h-full w-full object-cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public_uploads')->url($profileUser->avatar_path) }}" alt="{{ $profileUser->displayName() }}">
                @else
                    <i class="fa-regular fa-circle-user shop-default-avatar-icon" aria-hidden="true"></i>
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <h1 class="text-xl font-semibold">{{ $profileUser->displayName() }}</h1>
                <p class="mt-1 text-sm text-slate-600">用户 ID：{{ $profileUser->public_id }}</p>
                @if($profileUser->profile_intro)
                    <p class="mt-2 max-w-2xl whitespace-pre-line text-sm leading-6 text-slate-600">{{ $profileUser->profile_intro }}</p>
                @endif
            </div>
            @auth
                @if(auth()->id() !== $profileUser->id)
                    <a class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800" href="{{ route('messages.thread', $profileUser) }}">私聊</a>
                @endif
            @else
                <a class="rounded-sm border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50" href="{{ route('login') }}">登录后私聊</a>
            @endauth
        </div>
        <div class="grid gap-px bg-slate-200 sm:grid-cols-2">
            <div class="bg-white px-4 py-4">
                <p class="text-xs text-slate-500">商品评论</p>
                <p class="mt-1 text-2xl font-semibold">{{ $profileUser->product_comments_count }}</p>
            </div>
            <div class="bg-white px-4 py-4">
                <p class="text-xs text-slate-500">论坛帖子</p>
                <p class="mt-1 text-2xl font-semibold">{{ $profileUser->forum_threads_count }}</p>
            </div>
        </div>
        @if($profileUser->addresses->isNotEmpty())
            <div class="border-t border-slate-200 px-4 py-4">
                <h2 class="text-sm font-semibold">公开地址</h2>
                <div class="mt-3 grid gap-2 md:grid-cols-2">
                    @foreach($profileUser->addresses as $address)
                        <div class="rounded-sm border border-slate-200 bg-slate-50 px-3 py-2 text-sm leading-6 text-slate-700">
                            <p>{{ $address->formatted() }}</p>
                            <p class="text-xs text-slate-500">{{ $address->recipient_name }} / {{ $address->phone }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </section>
</x-layouts.app>
