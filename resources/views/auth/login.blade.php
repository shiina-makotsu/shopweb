<x-layouts.app title="登录" :wide="true" :settings="$settings ?? null">
    <div class="mx-auto max-w-md rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-5 py-3">
            <h1 class="text-lg font-semibold">用户登录</h1>
        </div>
        <form method="post" action="{{ route('login') }}" class="space-y-4 px-5 py-5">
            @csrf
            <label class="block">
                <span class="text-sm font-medium">邮箱</span>
                <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" type="email" name="email" value="{{ old('email') }}" required autofocus>
            </label>
            <label class="block">
                <span class="text-sm font-medium">密码</span>
                <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" type="password" name="password" required>
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" value="1">
                <span>保持登录</span>
            </label>
            <button class="w-full rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800" type="submit">登录</button>
        </form>
        <div class="border-t border-slate-200 bg-slate-50 px-5 py-3 text-sm text-slate-600">
            还没有账号？
            <a class="text-blue-700 hover:text-blue-900" href="{{ route('register') }}">注册</a>
        </div>
    </div>
</x-layouts.app>
