<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>安装 ShopWeb</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <main class="mx-auto max-w-4xl px-4 py-10">
        <div class="mb-6">
            <h1 class="text-2xl font-semibold">安装 ShopWeb</h1>
            <p class="mt-2 text-sm text-slate-600">填写数据库、站点和管理员信息，系统会自动初始化并锁定安装向导。</p>
        </div>

        <section class="mb-5 rounded-sm border border-slate-300 bg-white">
            <h2 class="border-b border-slate-200 bg-slate-100 px-4 py-2 text-sm font-semibold">环境检查</h2>
            <div class="grid gap-2 p-4 sm:grid-cols-2">
                @foreach($checks as $label => $ok)
                    <div class="flex items-center justify-between rounded-sm border border-slate-200 px-3 py-2 text-sm">
                        <span>{{ $label }}</span>
                        <span class="{{ $ok ? 'text-emerald-700' : 'text-red-700' }}">{{ $ok ? '通过' : '失败' }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        @if($errors->any())
            <div class="mb-5 rounded-sm border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-inside list-disc">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="{{ route('install.store') }}" class="space-y-5 rounded-sm border border-slate-300 bg-white p-5">
            @csrf
            <section>
                <h2 class="mb-3 text-sm font-semibold">数据库</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block">
                        <span class="text-sm font-medium">主机</span>
                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="db_host" value="{{ old('db_host', '127.0.0.1') }}" required>
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium">端口</span>
                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" type="number" name="db_port" value="{{ old('db_port', 3306) }}" required>
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium">数据库名</span>
                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="db_database" value="{{ old('db_database', 'shopweb') }}" required>
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium">用户名</span>
                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="db_username" value="{{ old('db_username', 'root') }}" required>
                    </label>
                    <label class="block md:col-span-2">
                        <span class="text-sm font-medium">密码</span>
                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" type="password" name="db_password" value="{{ old('db_password') }}">
                    </label>
                </div>
            </section>

            <section class="border-t border-slate-200 pt-5">
                <h2 class="mb-3 text-sm font-semibold">站点</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block">
                        <span class="text-sm font-medium">站点地址</span>
                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="app_url" value="{{ old('app_url', url('/')) }}" required>
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium">站点名称</span>
                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="site_name" value="{{ old('site_name', 'ShopWeb') }}" required>
                    </label>
                    <label class="block md:col-span-2">
                        <span class="text-sm font-medium">联系方式</span>
                        <textarea class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="contact_info" rows="3">{{ old('contact_info') }}</textarea>
                    </label>
                    <label class="block md:col-span-2">
                        <span class="text-sm font-medium">付款说明</span>
                        <textarea class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="payment_instructions" rows="5">{{ old('payment_instructions') }}</textarea>
                    </label>
                </div>
            </section>

            <section class="border-t border-slate-200 pt-5">
                <h2 class="mb-3 text-sm font-semibold">管理员</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block">
                        <span class="text-sm font-medium">名称</span>
                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="admin_name" value="{{ old('admin_name', 'Admin') }}" required>
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium">邮箱</span>
                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" type="email" name="admin_email" value="{{ old('admin_email', 'admin@example.com') }}" required>
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium">密码</span>
                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" type="password" name="admin_password" required>
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium">确认密码</span>
                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" type="password" name="admin_password_confirmation" required>
                    </label>
                </div>
            </section>

            <button class="rounded-sm border border-blue-700 bg-blue-700 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800" type="submit">开始安装</button>
        </form>
    </main>
</body>
</html>
