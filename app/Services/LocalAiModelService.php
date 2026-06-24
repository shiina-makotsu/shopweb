<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class LocalAiModelService
{
    public function __construct(private readonly LocalAiResourceGuard $resourceGuard)
    {
    }

    /**
     * @return array<int, string>
     */
    private function languageExtensions(): array
    {
        return ['gguf', 'ggml', 'bin', 'safetensors', 'pt', 'pth', 'onnx'];
    }

    /**
     * @return array<int, string>
     */
    private function imageExtensions(): array
    {
        return ['safetensors', 'ckpt', 'pt', 'pth', 'onnx'];
    }

    public function basePath(): string
    {
        return storage_path('app/ai-models');
    }

    public function languagePath(): string
    {
        return $this->basePath().DIRECTORY_SEPARATOR.'language';
    }

    public function imagePath(): string
    {
        return $this->basePath().DIRECTORY_SEPARATOR.'image';
    }

    public function loraPath(): string
    {
        return $this->basePath().DIRECTORY_SEPARATOR.'lora';
    }

    public function ensureDirectories(): void
    {
        foreach ([$this->languagePath(), $this->imagePath(), $this->loraPath()] as $path) {
            if (! File::isDirectory($path)) {
                File::makeDirectory($path, 0755, true);
            }
        }
    }

    /**
     * @return array<int, array{id:string,name:string,source:string,feature:string,path:string,extension:string,size:int,lora:bool}>
     */
    public function models(string $feature): array
    {
        $feature = $feature === 'chat' ? 'chat' : 'image';

        return $feature === 'chat'
            ? $this->scanDirectory($this->languagePath(), $this->languageExtensions(), 'chat', false)
            : $this->scanDirectory($this->imagePath(), $this->imageExtensions(), 'image', false);
    }

    /**
     * @return array<int, array{id:string,name:string,source:string,feature:string,path:string,extension:string,size:int,lora:bool}>
     */
    public function loras(): array
    {
        return $this->scanDirectory($this->loraPath(), $this->imageExtensions(), 'image', true);
    }

    public function hasModel(string $model, string $feature): bool
    {
        return $this->findModel($model, $feature) !== null;
    }

    /**
     * @return array{id:string,name:string,source:string,feature:string,path:string,extension:string,size:int,lora:bool}|null
     */
    public function findModel(string $model, string $feature): ?array
    {
        $model = $this->stripLocalPrefix($model);

        foreach ($this->models($feature) as $candidate) {
            if ($candidate['id'] === $model || $candidate['name'] === $model || basename($candidate['path']) === $model) {
                return $candidate;
            }
        }

        return null;
    }

    public function shouldUseLocal(array $data, string $feature): bool
    {
        if (($data['provider'] ?? null) === 'local') {
            return true;
        }

        $model = trim((string) ($data['model'] ?? ''));

        return $model !== '' && Str::startsWith($model, 'local:') && $this->hasModel($model, $feature);
    }

    public function enabledFallback(): bool
    {
        return (bool) config('services.local_ai.fallback_enabled', true);
    }

    public function runnerConfigured(): bool
    {
        return trim((string) config('services.local_ai.runner_url', '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array{name:string,content:string,mime_type:string}>  $references
     * @return array{images:array<int, array<string,string>>,payload:array<string,mixed>,request_ms:int,source:string}
     */
    public function generateImage(array $data, array $payload, array $references = []): array
    {
        $this->resourceGuard->assertCanRun('image');

        $model = $this->requireModel((string) ($data['model'] ?? ''), 'image');
        $startedAt = microtime(true);
        $response = $this->sendToRunner('/image/generate', [
            'model' => $model,
            'prompt' => $payload['prompt'] ?? '',
            'parameters' => $payload,
            'references' => $references,
            'loras' => $this->selectedLoras($data),
        ], (int) ($data['timeout_seconds'] ?? 600));

        return [
            'images' => $this->extractRunnerImages($response),
            'payload' => $response,
            'request_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'source' => 'local',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{message:string,payload:array<string,mixed>,request_ms:int,source:string}
     */
    public function chat(array $data, array $payload): array
    {
        $this->resourceGuard->assertCanRun('chat');

        $model = $this->requireModel((string) ($data['model'] ?? ''), 'chat');
        $startedAt = microtime(true);
        $response = $this->sendToRunner('/chat', [
            'model' => $model,
            'prompt' => $data['prompt'] ?? '',
            'messages' => $payload['messages'] ?? [],
            'parameters' => $payload,
        ], (int) ($data['timeout_seconds'] ?? 600));

        $message = trim((string) data_get($response, 'message', data_get($response, 'text', data_get($response, 'output_text', ''))));

        if ($message === '') {
            throw new RuntimeException('本地 AI runner 没有返回可识别的聊天内容。');
        }

        return [
            'message' => $message,
            'payload' => $response,
            'request_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'source' => 'local',
        ];
    }

    /**
     * @param  array<int, string>  $extensions
     * @return array<int, array{id:string,name:string,source:string,feature:string,path:string,extension:string,size:int,lora:bool}>
     */
    private function scanDirectory(string $path, array $extensions, string $feature, bool $lora): array
    {
        $this->ensureDirectories();

        if (! File::isDirectory($path)) {
            return [];
        }

        $extensions = array_map('strtolower', $extensions);

        return collect(File::files($path))
            ->filter(fn (\SplFileInfo $file): bool => in_array(strtolower($file->getExtension()), $extensions, true))
            ->map(function (\SplFileInfo $file) use ($feature, $lora): array {
                $name = pathinfo($file->getFilename(), PATHINFO_FILENAME);

                return [
                    'id' => $lora ? $name : 'local:'.$feature.':'.$name,
                    'name' => $name,
                    'source' => 'local',
                    'feature' => $feature,
                    'path' => $file->getPathname(),
                    'extension' => strtolower($file->getExtension()),
                    'size' => $file->getSize(),
                    'lora' => $lora,
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @return array{id:string,name:string,source:string,feature:string,path:string,extension:string,size:int,lora:bool}
     */
    private function requireModel(string $model, string $feature): array
    {
        $resolved = $this->findModel($model, $feature);

        if ($resolved === null) {
            throw new RuntimeException('没有在本地模型目录中找到该模型。');
        }

        if (! $this->runnerConfigured()) {
            throw new RuntimeException('已识别本地模型，但尚未配置 LOCAL_AI_RUNNER_URL，本地推理暂不可用。');
        }

        return $resolved;
    }

    /**
     * @return array<int, array{id:string,name:string,path:string,weight:float}>
     */
    private function selectedLoras(array $data): array
    {
        $selected = collect($data['lora_models'] ?? [])
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->values();

        if ($selected->isEmpty()) {
            return [];
        }

        return collect($this->loras())
            ->filter(fn (array $lora): bool => $selected->contains($lora['id']) || $selected->contains($lora['name']))
            ->map(fn (array $lora): array => [
                'id' => $lora['id'],
                'name' => $lora['name'],
                'path' => $lora['path'],
                'weight' => 1.0,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sendToRunner(string $path, array $payload, int $timeout): array
    {
        $baseUrl = rtrim((string) config('services.local_ai.runner_url', ''), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('本地 AI runner 未配置。');
        }

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout(min(30, max(5, $timeout)))
                ->acceptJson()
                ->post($baseUrl.$path, $payload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('本地 AI runner 无法连接：'.$exception->getMessage(), previous: $exception);
        }

        if ($response->failed()) {
            $message = data_get($response->json(), 'message')
                ?? data_get($response->json(), 'error.message')
                ?? $response->body();

            throw new RuntimeException('本地 AI runner 请求失败：'.trim((string) $message));
        }

        return (array) $response->json();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string,string>>
     */
    private function extractRunnerImages(array $payload): array
    {
        $items = data_get($payload, 'images', data_get($payload, 'data', []));

        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->map(function (mixed $item): ?array {
                if (is_string($item)) {
                    return Str::startsWith($item, 'http')
                        ? ['url' => $item, 'revised_prompt' => '']
                        : ['data_url' => Str::startsWith($item, 'data:image/') ? $item : 'data:image/png;base64,'.$item, 'revised_prompt' => ''];
                }

                if (! is_array($item)) {
                    return null;
                }

                $url = $item['url'] ?? null;
                $dataUrl = $item['data_url'] ?? $item['b64_json'] ?? $item['base64'] ?? null;

                if (is_string($url) && $url !== '') {
                    return ['url' => $url, 'revised_prompt' => (string) ($item['revised_prompt'] ?? '')];
                }

                if (is_string($dataUrl) && $dataUrl !== '') {
                    return [
                        'data_url' => Str::startsWith($dataUrl, 'data:image/') ? $dataUrl : 'data:image/png;base64,'.$dataUrl,
                        'revised_prompt' => (string) ($item['revised_prompt'] ?? ''),
                    ];
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function stripLocalPrefix(string $model): string
    {
        if (! Str::startsWith($model, 'local:')) {
            return $model;
        }

        $parts = explode(':', $model);

        return end($parts) ?: $model;
    }
}
