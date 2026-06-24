<?php

namespace App\Services;

use App\Models\AiChatSession;
use App\Models\AiImageTask;
use App\Models\AiUsageLog;
use App\Models\SiteSetting;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AiUsageService
{
    public function settings(): SiteSetting
    {
        return SiteSetting::query()->firstOrCreate([], ['site_name' => config('app.name', 'ShopWeb')]);
    }

    public function aiTrashRetentionDays(): int
    {
        return max(1, min(365, (int) ($this->settings()->ai_trash_retention_days ?: 30)));
    }

    /**
     * @return array{image_tasks:int,chat_sessions:int,expired_before:string}
     */
    public function purgeExpiredAiTrash(): array
    {
        $expiredBefore = now()->subDays($this->aiTrashRetentionDays());

        $imageTasks = AiImageTask::query()
            ->whereNotNull('deleted_at')
            ->where('deleted_at', '<', $expiredBefore)
            ->delete();

        $chatSessions = AiChatSession::query()
            ->whereNotNull('deleted_at')
            ->where('deleted_at', '<', $expiredBefore)
            ->delete();

        return [
            'image_tasks' => (int) $imageTasks,
            'chat_sessions' => (int) $chatSessions,
            'expired_before' => $expiredBefore->toIso8601String(),
        ];
    }

    /**
     * @return array{endpoint:string,api_key:?string,source:string,config_name:string,tracked:bool,feature:string}
     */
    public function resolveConfig(?User $user, array $data, string $feature = 'image'): array
    {
        $feature = $feature === 'chat' ? 'chat' : 'image';
        $mode = (string) ($data['config_mode'] ?? 'default');
        $endpoint = trim((string) (($feature === 'chat' ? ($data['chat_endpoint'] ?? null) : null) ?? ($data['endpoint'] ?? '')));
        $apiKey = trim((string) (($feature === 'chat' ? ($data['chat_api_key'] ?? null) : null) ?? ($data['api_key'] ?? '')));
        $configName = trim((string) ($data['config_name'] ?? ''));

        if ($mode === 'custom' || $endpoint !== '' || $apiKey !== '') {
            if ($endpoint === '') {
                throw ValidationException::withMessages([
                    'endpoint' => '使用自定义配置时需要填写 API URL。',
                ]);
            }

            return [
                'endpoint' => $endpoint,
                'api_key' => $apiKey !== '' ? $apiKey : null,
                'source' => ($user?->isBackofficeUser() ?? false) ? 'backoffice_custom' : 'front_custom',
                'config_name' => $configName !== '' ? $configName : '自定义配置',
                'tracked' => $user?->isBackofficeUser() ?? false,
                'feature' => $feature,
            ];
        }

        if (! $user) {
            throw ValidationException::withMessages([
                'endpoint' => 'AI 功能需要先登录后使用。',
            ]);
        }

        $settings = $this->settings();
        $managedEndpoint = trim((string) $this->managedEndpoint($user, $settings, $feature));
        $managedApiKey = trim((string) $this->managedApiKey($user, $settings, $feature));

        if ($managedEndpoint === '') {
            throw ValidationException::withMessages([
                'endpoint' => $feature === 'chat'
                    ? '后台尚未配置默认 AI 聊天 API URL，请联系管理员或使用自定义配置。'
                    : '后台尚未配置默认 AI 生图 API URL，请联系管理员或使用自定义配置。',
            ]);
        }

        return [
            'endpoint' => $managedEndpoint,
            'api_key' => $managedApiKey !== '' ? $managedApiKey : null,
            'source' => $this->hasUserManagedConfig($user, $feature) ? 'user_managed' : 'site_default',
            'config_name' => $configName !== '' ? $configName : '默认配置',
            'tracked' => true,
            'feature' => $feature,
        ];
    }

    private function managedEndpoint(User $user, SiteSetting $settings, string $feature): ?string
    {
        if ($feature === 'chat') {
            return $user->ai_chat_endpoint
                ?: $user->ai_endpoint
                ?: $settings->ai_default_chat_endpoint
                ?: $settings->ai_default_endpoint;
        }

        return $user->ai_image_endpoint
            ?: $user->ai_endpoint
            ?: $settings->ai_default_image_endpoint
            ?: $settings->ai_default_endpoint;
    }

    private function managedApiKey(User $user, SiteSetting $settings, string $feature): ?string
    {
        if ($feature === 'chat') {
            return $user->ai_chat_api_key
                ?: $user->ai_api_key
                ?: $settings->ai_default_chat_api_key
                ?: $settings->ai_default_api_key;
        }

        return $user->ai_image_api_key
            ?: $user->ai_api_key
            ?: $settings->ai_default_image_api_key
            ?: $settings->ai_default_api_key;
    }

    private function hasUserManagedConfig(User $user, string $feature): bool
    {
        if ($feature === 'chat') {
            return filled($user->ai_chat_endpoint) || filled($user->ai_chat_api_key) || filled($user->ai_endpoint) || filled($user->ai_api_key);
        }

        return filled($user->ai_image_endpoint) || filled($user->ai_image_api_key) || filled($user->ai_endpoint) || filled($user->ai_api_key);
    }

    public function quotaLimitK(User $user): int
    {
        if ($user->ai_quota_k !== null) {
            return (int) $user->ai_quota_k;
        }

        $defaultLimit = $this->settings()->ai_default_user_quota_k;

        return $defaultLimit !== null ? (int) $defaultLimit : 100;
    }

    public function usedTokens(?User $user = null, ?CarbonInterface $since = null): int
    {
        return (int) AiUsageLog::query()
            ->when($user, fn ($query) => $query->whereBelongsTo($user))
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->sum('token_count');
    }

    /**
     * @return array{total_tokens:int,prompt_tokens:int,completion_tokens:int}
     */
    public function tokenSums(?User $user = null, ?CarbonInterface $since = null): array
    {
        $row = AiUsageLog::query()
            ->selectRaw('coalesce(sum(token_count), 0) as total_tokens')
            ->selectRaw('coalesce(sum(prompt_tokens), 0) as prompt_tokens')
            ->selectRaw('coalesce(sum(completion_tokens), 0) as completion_tokens')
            ->when($user, fn ($query) => $query->whereBelongsTo($user))
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->first();

        return [
            'total_tokens' => (int) ($row?->total_tokens ?? 0),
            'prompt_tokens' => (int) ($row?->prompt_tokens ?? 0),
            'completion_tokens' => (int) ($row?->completion_tokens ?? 0),
        ];
    }

    public function remainingK(User $user): int
    {
        $limitTokens = $this->quotaLimitK($user) * 1000;

        return max(0, (int) floor(($limitTokens - $this->quotaUsedTokens($user)) / 1000));
    }

    /**
     * @return Collection<int, object{model:string,total_tokens:int}>
     */
    public function modelBreakdown(?User $user = null, ?CarbonInterface $since = null): Collection
    {
        return AiUsageLog::query()
            ->selectRaw("coalesce(nullif(model, ''), 'unknown') as model, sum(token_count) as total_tokens")
            ->when($user, fn ($query) => $query->whereBelongsTo($user))
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->groupBy(DB::raw("coalesce(nullif(model, ''), 'unknown')"))
            ->orderByDesc('total_tokens')
            ->get();
    }

    /**
     * @return array{buckets:Collection<int, array{label:string,short_label:string,total_tokens:int,prompt_tokens:int,completion_tokens:int,models:array<int, array{model:string,total_tokens:int,prompt_tokens:int,completion_tokens:int}>}>,models:Collection<int, string>,max_tokens:int}
     */
    public function hourlyModelUsage(?User $user = null, int $hours = 24): array
    {
        $hours = max(1, min(72, $hours));
        $start = now()->copy()->startOfHour()->subHours($hours - 1);

        $buckets = collect(range(0, $hours - 1))
            ->mapWithKeys(function (int $offset) use ($start): array {
                $time = $start->copy()->addHours($offset);

                return [
                    $time->format('Y-m-d H:00') => [
                        'label' => $time->format('Y-m-d H:00'),
                        'short_label' => $time->format('H:00'),
                        'total_tokens' => 0,
                        'prompt_tokens' => 0,
                        'completion_tokens' => 0,
                        'models' => [],
                    ],
                ];
            });

        $models = [];

        AiUsageLog::query()
            ->when($user, fn ($query) => $query->whereBelongsTo($user))
            ->where('created_at', '>=', $start)
            ->orderBy('created_at')
            ->get()
            ->each(function (AiUsageLog $log) use (&$buckets, &$models): void {
                $key = $log->created_at?->copy()->startOfHour()->format('Y-m-d H:00');

                if (! $key || ! $buckets->has($key)) {
                    return;
                }

                $model = trim((string) $log->model) !== '' ? (string) $log->model : 'unknown';
                $tokens = (int) $log->token_count;
                $promptTokens = (int) ($log->prompt_tokens ?? 0);
                $completionTokens = (int) ($log->completion_tokens ?? 0);
                $bucket = $buckets->get($key);

                $bucket['total_tokens'] += $tokens;
                $bucket['prompt_tokens'] += $promptTokens;
                $bucket['completion_tokens'] += $completionTokens;

                $bucket['models'][$model] ??= [
                    'model' => $model,
                    'total_tokens' => 0,
                    'prompt_tokens' => 0,
                    'completion_tokens' => 0,
                ];
                $bucket['models'][$model]['total_tokens'] += $tokens;
                $bucket['models'][$model]['prompt_tokens'] += $promptTokens;
                $bucket['models'][$model]['completion_tokens'] += $completionTokens;

                $models[$model] = $model;
                $buckets->put($key, $bucket);
            });

        $normalized = $buckets
            ->values()
            ->map(function (array $bucket): array {
                $bucket['models'] = array_values($bucket['models']);

                return $bucket;
            });

        return [
            'buckets' => $normalized,
            'models' => collect(array_values($models))->sort()->values(),
            'max_tokens' => max(1, (int) $normalized->max('total_tokens')),
        ];
    }

    /**
     * @return Collection<int, AiUsageLog>
     */
    public function recentLogs(?User $user = null, int $limit = 30): Collection
    {
        return AiUsageLog::query()
            ->with('user')
            ->when($user, fn ($query) => $query->whereBelongsTo($user))
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function assertWithinQuota(User $user, array $data = []): void
    {
        if (! $this->shouldEnforceQuota($user)) {
            return;
        }

        $limitTokens = $this->quotaLimitK($user) * 1000;

        if ($limitTokens <= 0) {
            throw ValidationException::withMessages([
                'quota' => '当前 AI 余额不足，请联系管理员增加配额。',
            ]);
        }

        $remainingTokens = $limitTokens - $this->quotaUsedTokens($user);

        if ($remainingTokens <= 0) {
            throw ValidationException::withMessages([
                'quota' => 'AI 余额不足，请联系管理员增加配额。',
            ]);
        }

        $estimatedTokens = $data !== [] ? $this->estimateTokens($data) : 1;

        if ($estimatedTokens > $remainingTokens) {
            throw ValidationException::withMessages([
                'quota' => sprintf(
                    'AI 余额不足：当前剩余约 %s token，本次预计需要约 %s token。',
                    number_format($remainingTokens),
                    number_format($estimatedTokens),
                ),
            ]);
        }
    }

    public function shouldEnforceQuota(User $user): bool
    {
        return ! $user->isBackofficeUser();
    }

    public function shouldApplyQuota(?User $user, array $config): bool
    {
        if (! $user || ! ($config['tracked'] ?? false)) {
            return false;
        }

        return $this->shouldEnforceQuota($user);
    }

    public function resetUsage(User $user): void
    {
        $user->forceFill([
            'ai_usage_reset_at' => now(),
        ])->save();
    }

    public function quotaUsedTokens(User $user): int
    {
        return $this->usedTokens($user, $this->effectiveUsageSince($user));
    }

    public function record(
        ?User $user,
        array $data,
        array $config,
        array $providerPayload = [],
        int $requestMs = 0,
        string $status = 'success',
        array $metadata = [],
    ): ?AiUsageLog {
        if (! ($config['tracked'] ?? false) || ! $user) {
            return null;
        }

        $usage = $this->extractUsage($providerPayload);
        $tokenCount = (int) ($usage['total_tokens'] ?? 0);

        if ($tokenCount <= 0) {
            $tokenCount = $this->estimateTokens($data);
        }

        return AiUsageLog::query()->create([
            'user_id' => $user->id,
            'feature' => (string) ($metadata['feature'] ?? 'image'),
            'config_name' => (string) ($config['config_name'] ?? '默认配置'),
            'provider_source' => (string) ($config['source'] ?? 'site_default'),
            'model' => (string) ($data['model'] ?? ''),
            'endpoint_host' => parse_url((string) ($config['endpoint'] ?? ''), PHP_URL_HOST) ?: null,
            'token_count' => $tokenCount,
            'prompt_tokens' => $usage['prompt_tokens'] ?? null,
            'completion_tokens' => $usage['completion_tokens'] ?? null,
            'request_ms' => $requestMs,
            'status' => $status,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @return array{total_tokens?:int,prompt_tokens?:int,completion_tokens?:int}
     */
    private function extractUsage(array $payload): array
    {
        $usage = data_get($payload, 'usage');

        if (! is_array($usage)) {
            return [];
        }

        $total = $this->integerValue($usage['total_tokens'] ?? $usage['total'] ?? null);
        $prompt = $this->integerValue($usage['prompt_tokens'] ?? $usage['input_tokens'] ?? null);
        $completion = $this->integerValue($usage['completion_tokens'] ?? $usage['output_tokens'] ?? null);

        return array_filter([
            'total_tokens' => $total,
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
        ], fn ($value): bool => $value !== null);
    }

    private function integerValue(mixed $value): ?int
    {
        return is_numeric($value) ? max(0, (int) $value) : null;
    }

    private function estimateTokens(array $data): int
    {
        $prompt = trim((string) ($data['prompt'] ?? ''));
        $count = max(1, (int) ($data['count'] ?? 1));
        $model = Str::lower((string) ($data['model'] ?? ''));
        $base = (int) ceil(mb_strlen($prompt) / 2);
        $imageWeight = str_contains($model, 'image') || str_contains($model, 'flux') ? 1000 : 250;

        return max(1, $base + ($count * $imageWeight));
    }

    private function effectiveUsageSince(?User $user, ?CarbonInterface $since = null): ?CarbonInterface
    {
        $resetAt = $user?->ai_usage_reset_at;

        if (! $resetAt) {
            return $since;
        }

        if (! $since || $resetAt->greaterThan($since)) {
            return $resetAt;
        }

        return $since;
    }
}
