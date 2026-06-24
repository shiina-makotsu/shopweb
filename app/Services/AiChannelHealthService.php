<?php

namespace App\Services;

use App\Models\AiWorkflow;
use App\Models\SiteSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class AiChannelHealthService
{
    public function __construct(
        private readonly AiUsageService $usage,
        private readonly LocalAiModelService $localAi,
    ) {
    }

    /**
     * @return array{chat:array<string,mixed>,image:array<string,mixed>}
     */
    public function status(bool $refresh = false): array
    {
        return [
            'chat' => $this->statusFor('chat', $refresh),
            'image' => $this->statusFor('image', $refresh),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function statusFor(string $feature, bool $refresh = false): array
    {
        $feature = $feature === 'chat' ? 'chat' : 'image';
        $settings = $this->usage->settings();
        $endpoint = $this->defaultEndpoint($settings, $feature);
        $apiKey = $this->defaultApiKey($settings, $feature);
        $workflow = $this->fallbackWorkflow($feature);
        $model = $this->fallbackModel($feature);
        $api = $endpoint === ''
            ? ['configured' => false, 'ok' => false, 'message' => '无接口', 'ms' => null, 'host' => null]
            : $this->probeApi($feature, $endpoint, $apiKey, $refresh);

        $fallbackMode = $workflow ? 'workflow' : ($model ? 'model' : 'none');
        $activeMode = $api['ok'] ? 'api' : $fallbackMode;

        $message = match ($activeMode) {
            'api' => '默认接口正常',
            'workflow' => $endpoint === '' ? '未设置接口，使用本地工作流' : '接口不通，使用本地工作流',
            'model' => $endpoint === '' ? '未设置接口，直接使用本地模型' : '接口不通，直接使用本地模型',
            default => $endpoint === '' ? '未设置接口且没有本地工作流/模型' : '接口不通且没有本地工作流/模型',
        };

        return [
            'feature' => $feature,
            'label' => $feature === 'chat' ? 'Chat' : '生图',
            'active_mode' => $activeMode,
            'api' => $api,
            'endpoint' => $endpoint,
            'workflow' => $workflow ? [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'slug' => $workflow->slug,
                'type' => $workflow->type,
            ] : null,
            'model' => $model,
            'message' => $message,
        ];
    }

    public function fallbackWorkflow(string $feature): ?AiWorkflow
    {
        $feature = $feature === 'chat' ? 'chat' : 'image';
        $trigger = $feature === 'chat' ? 'ai_chat' : 'ai_image';

        return AiWorkflow::query()
            ->where('is_active', true)
            ->whereIn('type', [$feature, AiWorkflow::TYPE_MIXED])
            ->where('trigger_key', $trigger)
            ->orderBy('sort_order')
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * @return array{id:string,name:string,source:string,feature:string,path:string,extension:string,size:int,lora:bool}|null
     */
    public function fallbackModel(string $feature): ?array
    {
        return $this->localAi->models($feature === 'chat' ? 'chat' : 'image')[0] ?? null;
    }

    /**
     * @return array{configured:bool,ok:bool,message:string,ms:float|null,host:string|null}
     */
    private function probeApi(string $feature, string $endpoint, ?string $apiKey, bool $refresh): array
    {
        $baseUrl = $this->apiBaseUrl($endpoint);
        $cacheKey = 'shop:ai-channel-health:'.$feature.':'.sha1($baseUrl.'|'.((string) $apiKey));

        if (! $refresh) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                return $cached;
            }
        }

        $startedAt = microtime(true);

        try {
            $response = Http::timeout(8)
                ->connectTimeout(5)
                ->acceptJson()
                ->withHeaders(array_filter([
                    'Authorization' => $apiKey ? 'Bearer '.$apiKey : null,
                ]))
                ->get($baseUrl.'/models');
        } catch (ConnectionException $exception) {
            return $this->remember($cacheKey, [
                'configured' => true,
                'ok' => false,
                'message' => $exception->getMessage(),
                'ms' => null,
                'host' => parse_url($baseUrl, PHP_URL_HOST) ?: $baseUrl,
            ]);
        } catch (Throwable $exception) {
            return $this->remember($cacheKey, [
                'configured' => true,
                'ok' => false,
                'message' => $exception->getMessage(),
                'ms' => null,
                'host' => parse_url($baseUrl, PHP_URL_HOST) ?: $baseUrl,
            ]);
        }

        $message = $response->successful()
            ? 'OK'
            : trim((string) (data_get($response->json(), 'error.message') ?: data_get($response->json(), 'message') ?: $response->body()));

        return $this->remember($cacheKey, [
            'configured' => true,
            'ok' => $response->successful(),
            'message' => $message !== '' ? mb_substr($message, 0, 180) : 'HTTP '.$response->status(),
            'ms' => round((microtime(true) - $startedAt) * 1000, 2),
            'host' => parse_url($baseUrl, PHP_URL_HOST) ?: $baseUrl,
        ]);
    }

    /**
     * @param  array{configured:bool,ok:bool,message:string,ms:float|null,host:string|null}  $value
     * @return array{configured:bool,ok:bool,message:string,ms:float|null,host:string|null}
     */
    private function remember(string $key, array $value): array
    {
        Cache::put($key, $value, 60);

        return $value;
    }

    private function defaultEndpoint(SiteSetting $settings, string $feature): string
    {
        return trim((string) ($feature === 'chat'
            ? ($settings->ai_default_chat_endpoint ?: $settings->ai_default_endpoint)
            : ($settings->ai_default_image_endpoint ?: $settings->ai_default_endpoint)));
    }

    private function defaultApiKey(SiteSetting $settings, string $feature): ?string
    {
        $key = trim((string) ($feature === 'chat'
            ? ($settings->ai_default_chat_api_key ?: $settings->ai_default_api_key)
            : ($settings->ai_default_image_api_key ?: $settings->ai_default_api_key)));

        return $key !== '' ? $key : null;
    }

    private function apiBaseUrl(string $endpoint): string
    {
        $url = rtrim($endpoint, '/');

        return preg_replace('#/(models|images/generations|images/edits|images|responses|chat/completions)$#', '', $url) ?: $url;
    }
}
