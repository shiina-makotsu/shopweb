<x-layouts.app title="注册" :wide="true" :settings="$settings ?? null">
    <div class="mx-auto max-w-md rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-5 py-3">
            <h1 class="text-lg font-semibold">注册账号</h1>
        </div>
        <form method="post" action="{{ route('register') }}" class="space-y-4 px-5 py-5">
            @csrf
            <label class="block">
                <span class="text-sm font-medium">用户 ID</span>
                <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" type="text" name="public_id" value="{{ old('public_id') }}" pattern="[A-Za-z0-9_]+" maxlength="40" required autofocus>
                <span class="mt-1 block text-xs text-slate-500">只能使用英文、数字、下划线，注册后用于个人主页和搜索。</span>
            </label>
            <label class="block">
                <span class="text-sm font-medium">用户昵称</span>
                <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" type="text" name="name" value="{{ old('name') }}" required>
            </label>
            <label class="block">
                <span class="text-sm font-medium">注册邮箱</span>
                <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" type="email" name="email" value="{{ old('email') }}" required>
            </label>
            <label class="block">
                <span class="text-sm font-medium">密码</span>
                <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" type="password" name="password" required>
            </label>
            <label class="block">
                <span class="text-sm font-medium">确认密码</span>
                <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" type="password" name="password_confirmation" required>
            </label>
            <label class="block">
                <span class="text-sm font-medium">人机验证：{{ $captchaQuestion ?? '请刷新页面' }}</span>
                <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" type="number" name="captcha_answer" value="{{ old('captcha_answer') }}" required inputmode="numeric">
            </label>
            <button class="w-full rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800" type="submit">注册</button>
        </form>
        <div class="border-t border-slate-200 bg-slate-50 px-5 py-3 text-sm text-slate-600">
            已有账号？
            <a class="text-blue-700 hover:text-blue-900" href="{{ route('login') }}">登录</a>
        </div>
    </div>
</x-layouts.app>
