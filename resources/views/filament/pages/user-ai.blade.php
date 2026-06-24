@php
    $totalStats = $this->totalStats();
    $userStats = $this->selectedUserStats();
    $users = $this->users();
    $selectedUser = $this->selectedUser();
    $totalHourly = $totalStats['hourly_usage'] ?? ['buckets' => collect(), 'models' => collect(), 'max_tokens' => 1];
    $userHourly = $userStats['hourly_usage'] ?? ['buckets' => collect(), 'models' => collect(), 'max_tokens' => 1];
    $chartColors = ['#2563eb', '#16a34a', '#f97316', '#9333ea', '#dc2626', '#0891b2', '#ca8a04', '#4f46e5'];
    $formatTokens = function (int $tokens): string {
        if ($tokens >= 1000000) {
            return rtrim(rtrim(number_format($tokens / 1000000, 2), '0'), '.').'m';
        }

        if ($tokens >= 1000) {
            return rtrim(rtrim(number_format($tokens / 1000, 2), '0'), '.').'k';
        }

        return number_format($tokens);
    };
    $accountLabel = fn (?string $value): string => $value === 'member' ? '会员' : '普通';
    $forumLabel = fn (?string $value): string => $value === 'moderator' ? '版主' : '用户';
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <form wire:submit="saveDefaults" class="space-y-4 rounded-sm border border-slate-300 bg-white p-4">
            {{ $this->defaultsForm }}

            <x-filament::button type="submit">
                保存默认配置
            </x-filament::button>
        </form>

        <section class="rounded-sm border border-slate-300 bg-white">
            <div class="border-b border-slate-200 px-4 py-3">
                <h2 class="text-lg font-semibold">全站用量</h2>
            </div>
            <div class="grid gap-px bg-slate-200 md:grid-cols-4">
                <div class="bg-white px-4 py-5">
                    <p class="text-sm text-slate-600">总用量</p>
                    <p class="mt-2 text-2xl font-semibold">{{ $formatTokens((int) $totalStats['total_tokens']) }}</p>
                </div>
                <div class="bg-white px-4 py-5">
                    <p class="text-sm text-slate-600">24h 用量</p>
                    <p class="mt-2 text-2xl font-semibold">{{ $formatTokens((int) $totalStats['tokens_24h']) }}</p>
                </div>
                <div class="bg-white px-4 py-5">
                    <p class="text-sm text-slate-600">输入 token</p>
                    <p class="mt-2 text-2xl font-semibold">{{ $formatTokens((int) $totalStats['prompt_tokens']) }}</p>
                </div>
                <div class="bg-white px-4 py-5">
                    <p class="text-sm text-slate-600">输出 token</p>
                    <p class="mt-2 text-2xl font-semibold">{{ $formatTokens((int) $totalStats['completion_tokens']) }}</p>
                </div>
            </div>
            <div class="p-4">
                @include('filament.pages.partials.ai-hourly-chart', [
                    'hourly' => $totalHourly,
                    'emptyText' => '暂无 AI 使用记录。',
                    'chartColors' => $chartColors,
                    'formatTokens' => $formatTokens,
                ])
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[22rem_1fr]">
            <section class="rounded-sm border border-slate-300 bg-white">
                <div class="border-b border-slate-200 px-4 py-3">
                    <h2 class="text-lg font-semibold">用户列表</h2>
                </div>
                <div class="space-y-3 border-b border-slate-200 p-4">
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">搜索用户</span>
                        <input
                            class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm"
                            type="search"
                            placeholder="用户昵称、邮箱、用户 ID"
                            wire:model.live.debounce.300ms="search"
                        >
                    </label>
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">用户类型</span>
                        <select class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" wire:model.live="userFilter">
                            <option value="all">全部用户</option>
                            <option value="regular">普通用户</option>
                            <option value="member">会员用户</option>
                            <option value="moderator">版主</option>
                        </select>
                    </label>
                </div>
                <div class="max-h-[42rem] divide-y divide-slate-100 overflow-y-auto">
                    @forelse($users as $user)
                        @php
                            $active = $selectedUser?->is($user) ?? false;
                        @endphp
                        <button
                            class="block w-full px-4 py-3 text-left text-sm hover:bg-slate-50 {{ $active ? 'bg-blue-50' : 'bg-white' }}"
                            type="button"
                            wire:click="selectUser({{ $user->id }})"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-slate-900">{{ $user->displayName() }}</p>
                                    <p class="truncate text-xs text-slate-500">ID：{{ $user->public_id }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-xs font-medium text-slate-700">{{ $formatTokens((int) ($user->ai_total_tokens ?? 0)) }}</p>
                                    <p class="mt-1 text-[11px] text-slate-500">{{ $accountLabel($user->account_type) }} / {{ $forumLabel($user->forum_role) }}</p>
                                </div>
                            </div>
                        </button>
                    @empty
                        <p class="px-4 py-10 text-center text-sm text-slate-600">没有匹配的用户。</p>
                    @endforelse
                </div>
            </section>

            <section class="space-y-6">
                @if($selectedUser && $userStats)
                    <section class="rounded-sm border border-slate-300 bg-white">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                            <div>
                                <h2 class="text-lg font-semibold">{{ $selectedUser->displayName() }}</h2>
                                <p class="mt-1 text-sm text-slate-600">ID：{{ $selectedUser->public_id }} / {{ $selectedUser->email }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2 text-xs">
                                <span class="rounded-sm bg-slate-100 px-2 py-1 text-slate-700">{{ $accountLabel($selectedUser->account_type) }}</span>
                                <span class="rounded-sm bg-slate-100 px-2 py-1 text-slate-700">{{ $forumLabel($selectedUser->forum_role) }}</span>
                            </div>
                        </div>

                        <form wire:submit="saveUser" class="space-y-4 p-4">
                            {{ $this->userForm }}

                            <div class="flex flex-wrap items-center gap-3">
                                <x-filament::button type="submit">
                                    保存用户配置
                                </x-filament::button>
                                <x-filament::button type="button" color="gray" wire:click="resetUserUsage">
                                    重置 token 用量
                                </x-filament::button>
                                @if($userStats['reset_at'] ?? null)
                                    <span class="text-xs text-slate-500 dark:text-slate-400">
                                        余额扣减从 {{ $userStats['reset_at']->format('Y-m-d H:i:s') }} 起计算
                                    </span>
                                @endif
                            </div>
                        </form>
                    </section>

                    <section class="rounded-sm border border-slate-300 bg-white">
                        <div class="border-b border-slate-200 px-4 py-3">
                            <h2 class="text-lg font-semibold">当前用户数据</h2>
                        </div>
                        <div class="grid gap-px bg-slate-200 md:grid-cols-3 xl:grid-cols-6">
                            <div class="bg-white px-4 py-5">
                                <p class="text-sm text-slate-600">AI 余额</p>
                                <p class="mt-2 text-2xl font-semibold">{{ $formatTokens(((int) $userStats['remaining_k']) * 1000) }}</p>
                            </div>
                            <div class="bg-white px-4 py-5">
                                <p class="text-sm text-slate-600">上限</p>
                                <p class="mt-2 text-2xl font-semibold">{{ $formatTokens(((int) $userStats['limit_k']) * 1000) }}</p>
                            </div>
                            <div class="bg-white px-4 py-5">
                                <p class="text-sm text-slate-600">总用量</p>
                                <p class="mt-2 text-2xl font-semibold">{{ $formatTokens((int) $userStats['total_tokens']) }}</p>
                            </div>
                            <div class="bg-white px-4 py-5">
                                <p class="text-sm text-slate-600">24h 用量</p>
                                <p class="mt-2 text-2xl font-semibold">{{ $formatTokens((int) $userStats['tokens_24h']) }}</p>
                            </div>
                            <div class="bg-white px-4 py-5">
                                <p class="text-sm text-slate-600">输入 token</p>
                                <p class="mt-2 text-2xl font-semibold">{{ $formatTokens((int) $userStats['prompt_tokens']) }}</p>
                            </div>
                            <div class="bg-white px-4 py-5">
                                <p class="text-sm text-slate-600">输出 token</p>
                                <p class="mt-2 text-2xl font-semibold">{{ $formatTokens((int) $userStats['completion_tokens']) }}</p>
                            </div>
                        </div>
                        <div class="p-4">
                            @include('filament.pages.partials.ai-hourly-chart', [
                                'hourly' => $userHourly,
                                'emptyText' => '暂无该用户的 AI 使用记录。',
                                'chartColors' => $chartColors,
                                'formatTokens' => $formatTokens,
                            ])
                        </div>
                    </section>

                    <section class="rounded-sm border border-slate-300 bg-white">
                        <div class="border-b border-slate-200 px-4 py-3">
                            <h2 class="text-lg font-semibold">使用记录</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[820px] text-sm">
                                <thead class="bg-slate-50 text-left text-slate-600">
                                    <tr>
                                        <th class="px-4 py-3 font-medium">请求时间</th>
                                        <th class="px-4 py-3 font-medium">模型</th>
                                        <th class="px-4 py-3 text-right font-medium">输入</th>
                                        <th class="px-4 py-3 text-right font-medium">输出</th>
                                        <th class="px-4 py-3 text-right font-medium">总量</th>
                                        <th class="px-4 py-3 text-right font-medium">耗时</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse(($userStats['recent_logs'] ?? []) as $log)
                                        <tr>
                                            <td class="px-4 py-3">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                                            <td class="px-4 py-3">{{ $log->model ?: '-' }}</td>
                                            <td class="px-4 py-3 text-right">{{ $formatTokens((int) ($log->prompt_tokens ?? 0)) }}</td>
                                            <td class="px-4 py-3 text-right">{{ $formatTokens((int) ($log->completion_tokens ?? 0)) }}</td>
                                            <td class="px-4 py-3 text-right">{{ $formatTokens((int) $log->token_count) }}</td>
                                            <td class="px-4 py-3 text-right">{{ $log->request_ms ? number_format($log->request_ms / 1000, 2).'s' : '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="px-4 py-8 text-center text-slate-500" colspan="6">暂无该用户的最近记录。</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                @else
                    <section class="rounded-sm border border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-600">
                        请先从左侧选择用户。
                    </section>
                @endif
            </section>
        </div>
    </div>
</x-filament-panels::page>
