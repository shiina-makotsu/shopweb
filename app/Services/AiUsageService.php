<?php

namespace App\Services;

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

    /**
     * @return array{endpoint:string,api_key:?string,source:string,config_name:string,tracked:bool}
     */
    public function resolveConfig(?User $user, array $data): array
    {
        $mode = (string) ($data['config_mode'] ?? 'default');
        $endpoint = trim((string) ($data['endpoint'] ?? ''));
        $apiKey = trim((string) ($data['api_key'] ?? ''));
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
                'source' => 'front_custom',
                'config_name' => $configName !== '' ? $configName : '自定义配置',
                'tracked' => false,
            ];
        }

        if (! $user) {
            throw ValidationException::withMessages([
                'endpoint' => '默认配置需要先登录，或切换为自定义配置填写 API URL。',
            ]);
        }

        $settings = $this->settings();
        $managedEndpoint = trim((string) ($user->ai_endpoint ?: $settings->ai_default_endpoint));
        $managedApiKey = trim((string) ($user->ai_api_key ?: $settings->ai_default_api_key));

        if ($managedEndpoint === '') {
            throw ValidationException::withMessages([
                'endpoint' => '后台尚未配置默认 AI API URL，请联系管理员或使用自定义配置。',
            ]);
        }

        return [
            'endpoint' => $managedEndpoint,
            'api_key' => $managedApiKey !== '' ? $managedApiKey : null,
            'source' => $user->ai_endpoint ? 'user_managed' : 'site_default',
            'config_name' => $configName !== '' ? $configName : '默认配置',
            'tracked' => true,
        ];
    }

    public function quotaLimitK(User $user): int
    {
        return (int) ($user->ai_quota_k ?: ($this->settings()->ai_default_user_quota_k ?: 100));
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

        return max(0, (int) floor(($limitTokens - $this->usedTokens($user)) / 1000));
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

    public function assertWithinQuota(User $user): void
    {
        if ($this->quotaLimitK($user) <= 0) {
            throw ValidationException::withMessages([
                'quota' => '当前 AI 配额未启用，请联系管理员。',
            ]);
        }

        if ($this->remainingK($user) <= 0) {
            throw ValidationException::withMessages([
                'quota' => 'AI 配额已用完，请联系管理员增加配额。',
            ]);
        }
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
}
