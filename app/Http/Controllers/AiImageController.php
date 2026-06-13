<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use App\Services\AiUsageService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiImageController extends Controller
{
    public function __construct(private readonly AiUsageService $usage)
    {
    }

    public function index(): View
    {
        return view('ai-image.index', [
            'settings' => SiteSetting::query()->first(),
        ]);
    }

    public function models(Request $request): JsonResponse
    {
        $data = $request->validate([
            'config_mode' => ['nullable', 'in:default,custom'],
            'config_name' => ['nullable', 'string', 'max:100'],
            'endpoint' => ['nullable', 'url:http,https', 'max:2048'],
            'api_key' => ['nullable', 'string', 'max:4096'],
        ]);

        $config = $this->usage->resolveConfig($request->user(), $data);
        $baseUrl = $this->apiBaseUrl((string) $config['endpoint']);
        try {
            $response = Http::timeout(30)
                ->acceptJson()
                ->withHeaders($this->apiHeaders($config['api_key'] ?? null))
                ->get($baseUrl.'/models');
        } catch (ConnectionException $exception) {
            return response()->json([
                'message' => '模型列表获取失败：'.$exception->getMessage(),
            ], 502);
        }

        if ($response->failed()) {
            return $this->providerErrorResponse((array) $response->json(), $response->status(), '模型列表获取失败。');
        }

        $models = $this->extractModels((array) $response->json());

        return response()->json([
            'models' => $models,
            'base_url' => $baseUrl,
            'config_name' => $config['config_name'],
            'provider_source' => $config['source'],
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $this->validatedGenerationData($request);
        $config = $this->usage->resolveConfig($request->user(), $data);

        if (($config['tracked'] ?? false) && $request->user()) {
            $this->usage->assertWithinQuota($request->user());
        }

        $baseUrl = $this->apiBaseUrl((string) $config['endpoint']);
        $payload = $this->generationPayload($data);
        $references = $request->file('reference_images', []);
        $mask = $request->file('mask_image');

        $pendingRequest = Http::timeout((int) ($data['timeout_seconds'] ?? 600))
            ->acceptJson()
            ->withHeaders($this->apiHeaders($config['api_key'] ?? null));

        $startedAt = microtime(true);
        try {
            if ($references !== []) {
                foreach (array_values($references) as $index => $file) {
                    if (! $file instanceof UploadedFile) {
                        continue;
                    }

                    $pendingRequest = $pendingRequest->attach(
                        count($references) === 1 ? 'image' : 'image[]',
                        file_get_contents($file->getRealPath()),
                        $file->getClientOriginalName() ?: 'reference-'.$index.'.png',
                    );
                }

                if ($mask instanceof UploadedFile) {
                    $pendingRequest = $pendingRequest->attach(
                        'mask',
                        file_get_contents($mask->getRealPath()),
                        $mask->getClientOriginalName() ?: 'mask.png',
                    );
                }

                $response = $pendingRequest->post($baseUrl.'/images/edits', $payload);
            } else {
                $response = $pendingRequest->post($baseUrl.'/images/generations', $payload);
            }
        } catch (ConnectionException $exception) {
            return response()->json([
                'message' => '图片生成失败：'.$exception->getMessage(),
            ], 502);
        }

        $requestMs = (int) round((microtime(true) - $startedAt) * 1000);
        $providerPayload = (array) $response->json();

        if ($response->failed()) {
            return $this->providerErrorResponse($providerPayload, $response->status(), '图片生成失败。');
        }

        $images = $this->extractImages($providerPayload);

        if ($images === []) {
            return response()->json([
                'message' => '服务商没有返回可识别的图片数据。',
            ], 502);
        }

        $this->usage->record($request->user(), $data, $config, $providerPayload, $requestMs, metadata: [
            'feature' => 'image',
            'image_count' => count($images),
            'stream' => false,
        ]);

        return response()->json([
            'images' => $images,
            'meta' => $this->generationMeta($data, $baseUrl, $config),
        ]);
    }

    public function stream(Request $request): StreamedResponse
    {
        $data = $this->validatedGenerationData($request);
        $data['count'] = 1;
        $data['stream'] = true;
        $config = $this->usage->resolveConfig($request->user(), $data);

        if (($config['tracked'] ?? false) && $request->user()) {
            $this->usage->assertWithinQuota($request->user());
        }

        $baseUrl = $this->apiBaseUrl((string) $config['endpoint']);
        $payload = $this->generationPayload($data);
        $references = $this->referenceAttachments($request->file('reference_images', []));
        $mask = $this->uploadedAttachment($request->file('mask_image'), 'mask.png');
        $timeout = (int) ($data['timeout_seconds'] ?? 600);
        $headers = $this->apiHeaders($config['api_key'] ?? null);
        $user = $request->user();
        $usage = $this->usage;

        return response()->stream(function () use ($baseUrl, $payload, $references, $mask, $timeout, $headers, $data, $config, $user, $usage): void {
            $emit = fn (string $event, array $payload): bool => $this->emitSse($event, $payload);

            $emit('started', [
                'created_at' => now()->toIso8601String(),
            ]);

            $startedAt = microtime(true);

            try {
                $images = $this->streamImages($baseUrl, $payload, $references, $mask, $timeout, $headers, $emit);
                $requestMs = (int) round((microtime(true) - $startedAt) * 1000);

                if ($images === []) {
                    $emit('error', [
                        'message' => '服务商没有返回可识别的图片数据。',
                    ]);

                    return;
                }

                $emit('done', [
                    'images' => $images,
                    'meta' => $this->generationMeta($data, $baseUrl, $config),
                ]);

                $usage->record($user, $data, $config, [], $requestMs, metadata: [
                    'feature' => 'image',
                    'image_count' => count($images),
                    'stream' => true,
                ]);
            } catch (RuntimeException $exception) {
                $emit('error', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }, 200, [
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function apiBaseUrl(string $endpoint): string
    {
        $url = $this->assertSafeEndpoint($endpoint);
        $url = rtrim($url, '/');

        return preg_replace('#/(models|images/generations|images/edits|responses|chat/completions)$#', '', $url) ?: $url;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedGenerationData(Request $request): array
    {
        $data = $request->validate([
            'config_mode' => ['nullable', 'in:default,custom'],
            'config_name' => ['nullable', 'string', 'max:100'],
            'endpoint' => ['nullable', 'url:http,https', 'max:2048'],
            'api_key' => ['nullable', 'string', 'max:4096'],
            'model' => ['required', 'string', 'max:255'],
            'prompt' => ['required', 'string', 'max:5000'],
            'negative_prompt' => ['nullable', 'string', 'max:2000'],
            'count' => ['required', 'integer', 'min:1', 'max:8'],
            'size_mode' => ['required', 'in:auto,ratio,custom'],
            'ratio' => ['nullable', 'in:1:1,4:3,3:4,16:9,9:16,21:9'],
            'width' => ['nullable', 'integer', 'min:256', 'max:4096'],
            'height' => ['nullable', 'integer', 'min:256', 'max:4096'],
            'quality' => ['nullable', 'in:auto,high,medium,low'],
            'style' => ['nullable', 'in:auto,vivid,natural'],
            'background' => ['nullable', 'in:auto,transparent,opaque'],
            'transparent' => ['nullable', 'boolean'],
            'output_format' => ['nullable', 'in:auto,png,jpeg,webp'],
            'response_format' => ['nullable', 'in:auto,url,b64_json'],
            'seed' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'steps' => ['nullable', 'integer', 'min:1', 'max:150'],
            'guidance_scale' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'stream' => ['nullable', 'boolean'],
            'partial_images' => ['nullable', 'integer', 'min:0', 'max:3'],
            'timeout_seconds' => ['nullable', 'integer', 'min:30', 'max:1200'],
            'reference_images' => ['nullable', 'array', 'max:6'],
            'reference_images.*' => ['image', 'max:10240'],
            'mask_image' => ['nullable', 'image', 'max:10240'],
        ]);

        if (($data['size_mode'] ?? null) === 'custom' && (empty($data['width']) || empty($data['height']))) {
            throw ValidationException::withMessages([
                'width' => '自定义尺寸需要同时填写宽度和高度。',
            ]);
        }

        return $data;
    }

    private function assertSafeEndpoint(string $endpoint): string
    {
        $endpoint = trim($endpoint);
        $parts = parse_url($endpoint);
        $scheme = Str::lower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        $hostLower = Str::lower($host);
        $hostForIpCheck = trim($host, '[]');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw ValidationException::withMessages([
                'endpoint' => '请输入 http:// 或 https:// 开头的 API 地址。',
            ]);
        }

        if (($parts['user'] ?? null) || ($parts['pass'] ?? null)) {
            throw ValidationException::withMessages([
                'endpoint' => 'API 地址不能包含用户名或密码。',
            ]);
        }

        if (
            in_array($hostLower, ['localhost', 'localhost.localdomain'], true)
            || Str::endsWith($hostLower, ['.local', '.internal', '.localhost'])
            || (filter_var($hostForIpCheck, FILTER_VALIDATE_IP) && ! $this->isPublicIp($hostForIpCheck))
        ) {
            throw ValidationException::withMessages([
                'endpoint' => '出于安全考虑，前台生图接口不能请求 localhost、内网或保留地址。',
            ]);
        }

        return $endpoint;
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * @return array<string, string>
     */
    private function apiHeaders(?string $apiKey): array
    {
        $apiKey = trim((string) $apiKey);

        if ($apiKey === '') {
            return [];
        }

        return [
            'Authorization' => 'Bearer '.$apiKey,
            'X-API-Key' => $apiKey,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function generationPayload(array $data): array
    {
        $payload = [
            'model' => trim((string) $data['model']),
            'prompt' => trim((string) $data['prompt']),
            'n' => (int) $data['count'],
        ];

        $size = $this->requestedSize($data);
        if ($size !== null) {
            $payload['size'] = $size;
        }

        if ((bool) ($data['transparent'] ?? false)) {
            $data['background'] = 'transparent';
        }

        foreach (['negative_prompt', 'quality', 'style', 'background', 'output_format', 'response_format'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));

            if ($value !== '' && $value !== 'auto') {
                $payload[$field] = $value;
            }
        }

        foreach (['seed', 'steps', 'guidance_scale'] as $field) {
            if (($data[$field] ?? null) !== null && $data[$field] !== '') {
                $payload[$field] = is_numeric($data[$field]) ? $data[$field] + 0 : $data[$field];
            }
        }

        if ((bool) ($data['stream'] ?? false)) {
            $payload['stream'] = true;

            if (($data['partial_images'] ?? null) !== null && $data['partial_images'] !== '') {
                $payload['partial_images'] = (int) $data['partial_images'];
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requestedSize(array $data): ?string
    {
        if (($data['size_mode'] ?? null) === 'auto') {
            return null;
        }

        if (($data['size_mode'] ?? null) === 'custom') {
            return ((int) $data['width']).'x'.((int) $data['height']);
        }

        return match ((string) ($data['ratio'] ?? '1:1')) {
            '4:3' => '1536x1152',
            '3:4' => '1152x1536',
            '16:9' => '1536x864',
            '9:16' => '864x1536',
            '21:9' => '1792x768',
            default => '1024x1024',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{id: string, name: string}>
     */
    private function extractModels(array $payload): array
    {
        $items = $payload['data'] ?? $payload['models'] ?? [];

        if (! is_array($items)) {
            return [];
        }

        $models = collect($items)
            ->map(function (mixed $item): ?array {
                $id = is_array($item) ? ($item['id'] ?? $item['name'] ?? null) : $item;

                if (! is_string($id) || trim($id) === '') {
                    return null;
                }

                return [
                    'id' => $id,
                    'name' => is_array($item) && is_string($item['name'] ?? null) ? $item['name'] : $id,
                ];
            })
            ->filter()
            ->values();

        $imageModels = $models
            ->filter(fn (array $model): bool => $this->looksLikeImageModel($model['id'].' '.$model['name']))
            ->values();

        return ($imageModels->isNotEmpty() ? $imageModels : $models)->all();
    }

    private function looksLikeImageModel(string $label): bool
    {
        $label = Str::lower($label);

        foreach (['image', 'dall-e', 'gpt-image', 'flux', 'stable', 'sdxl', 'midjourney', 'recraft', 'ideogram', 'kolors', 'jimeng', 'seedream', 'wanx'] as $needle) {
            if (str_contains($label, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, string>>
     */
    private function extractImages(array $payload): array
    {
        $items = [];

        foreach (['data', 'images', 'artifacts', 'output'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $items = [...$items, ...array_values($payload[$key])];
            }
        }

        if ($items === []) {
            $items = [$payload];
        }

        $images = [];

        foreach ($items as $item) {
            if (is_string($item)) {
                $this->appendImageString($images, $item);

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            foreach (['url', 'image_url'] as $key) {
                $source = $item[$key] ?? null;

                if (is_string($source) && $this->isSafeImageSource($source)) {
                    $sourceKey = str_starts_with($source, 'data:image/') ? 'data_url' : 'url';

                    $images[] = [
                        $sourceKey => $source,
                        'revised_prompt' => (string) ($item['revised_prompt'] ?? ''),
                    ];
                }
            }

            foreach (['b64_json', 'base64', 'image', 'partial_image_b64', 'partial_image', 'image_b64'] as $key) {
                if (is_string($item[$key] ?? null)) {
                    $images[] = [
                        'data_url' => $this->dataUrl($item[$key], (string) ($item['mime_type'] ?? 'image/png')),
                        'revised_prompt' => (string) ($item['revised_prompt'] ?? ''),
                    ];
                }
            }
        }

        return collect($images)
            ->unique(fn (array $image): string => $image['url'] ?? $image['data_url'] ?? '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, string>>  $images
     */
    private function appendImageString(array &$images, string $value): void
    {
        if (str_starts_with($value, 'data:image/')) {
            $images[] = ['data_url' => $value];

            return;
        }

        if ($this->isSafeImageSource($value)) {
            $images[] = ['url' => $value];
        }
    }

    private function isSafeImageSource(string $value): bool
    {
        if (str_starts_with($value, 'data:image/')) {
            return true;
        }

        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            return false;
        }

        return in_array(Str::lower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    private function dataUrl(string $base64, string $mimeType): string
    {
        if (str_starts_with($base64, 'data:image/')) {
            return $base64;
        }

        return 'data:'.$mimeType.';base64,'.preg_replace('/\s+/', '', $base64);
    }

    /**
     * @param  array<int, UploadedFile>|UploadedFile|null  $files
     * @return array<int, array{name: string, content: string}>
     */
    private function referenceAttachments(array|UploadedFile|null $files): array
    {
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }

        $attachments = [];

        foreach (array_values($files ?? []) as $index => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $attachments[] = [
                'name' => $file->getClientOriginalName() ?: 'reference-'.$index.'.png',
                'content' => file_get_contents($file->getRealPath()),
            ];
        }

        return $attachments;
    }

    /**
     * @return array{name: string, content: string}|null
     */
    private function uploadedAttachment(mixed $file, string $fallbackName): ?array
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        return [
            'name' => $file->getClientOriginalName() ?: $fallbackName,
            'content' => file_get_contents($file->getRealPath()),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function generationMeta(array $data, string $baseUrl, array $config = []): array
    {
        return [
            'source' => parse_url($baseUrl, PHP_URL_HOST) ?: $baseUrl,
            'config_name' => (string) ($config['config_name'] ?? ''),
            'provider_source' => (string) ($config['source'] ?? ''),
            'model' => trim((string) $data['model']),
            'size_mode' => (string) ($data['size_mode'] ?? 'auto'),
            'requested_size' => $this->requestedSize($data),
            'quality' => (string) ($data['quality'] ?? 'auto'),
            'format' => (string) ($data['output_format'] ?? 'png'),
            'count' => (int) ($data['count'] ?? 1),
            'transparent' => (bool) ($data['transparent'] ?? false),
            'stream' => (bool) ($data['stream'] ?? false),
            'partial_images' => (int) ($data['partial_images'] ?? 0),
        ];
    }

    /**
     * @param  array<int, array{name: string, content: string}>  $references
     * @param  array{name: string, content: string}|null  $mask
     * @param  array<string, string>  $headers
     * @param  callable(string, array<string, mixed>): bool  $emit
     * @return array<int, array<string, string>>
     */
    private function streamImages(string $baseUrl, array $payload, array $references, ?array $mask, int $timeout, array $headers, callable $emit): array
    {
        if (! function_exists('curl_init')) {
            throw new RuntimeException('当前 PHP 未启用 curl 扩展，无法使用流式传输。');
        }

        $curl = curl_init($baseUrl.($references === [] ? '/images/generations' : '/images/edits'));

        if ($curl === false) {
            throw new RuntimeException('图片流式请求初始化失败。');
        }

        $body = $payload;
        $requestHeaders = ['Accept: text/event-stream'];

        foreach ($headers as $key => $value) {
            $requestHeaders[] = $key.': '.$value;
        }

        $temporaryFiles = [];

        if ($references === []) {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}';
            $requestHeaders[] = 'Content-Type: application/json';
        } else {
            foreach ($references as $index => $reference) {
                $temporaryFile = tempnam(sys_get_temp_dir(), 'shopweb-ai-reference-');
                if ($temporaryFile === false) {
                    continue;
                }

                file_put_contents($temporaryFile, $reference['content']);
                $temporaryFiles[] = $temporaryFile;
                $body[count($references) === 1 ? 'image' : 'image['.$index.']'] = new \CURLFile($temporaryFile, null, $reference['name']);
            }

            if ($mask !== null) {
                $temporaryFile = tempnam(sys_get_temp_dir(), 'shopweb-ai-mask-');
                if ($temporaryFile !== false) {
                    file_put_contents($temporaryFile, $mask['content']);
                    $temporaryFiles[] = $temporaryFile;
                    $body['mask'] = new \CURLFile($temporaryFile, null, $mask['name']);
                }
            }
        }

        $buffer = '';
        $images = [];

        try {
            curl_setopt_array($curl, [
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => $requestHeaders,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_WRITEFUNCTION => function ($curl, string $chunk) use (&$buffer, &$images, $emit): int {
                    $buffer .= $chunk;

                    while (preg_match("/\R\R/", $buffer, $match, PREG_OFFSET_CAPTURE)) {
                        $offset = $match[0][1];
                        $length = strlen($match[0][0]);
                        $event = substr($buffer, 0, $offset);
                        $buffer = substr($buffer, $offset + $length);

                        $this->handleStreamEvent($event, $images, $emit);
                    }

                    return strlen($chunk);
                },
            ]);

            curl_exec($curl);

            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $error = curl_error($curl);

            if ($buffer !== '') {
                $this->handleStreamEvent($buffer, $images, $emit);
            }

            if ($error !== '') {
                throw new RuntimeException('图片流式生成失败：'.$error);
            }

            if ($status >= 400) {
                throw new RuntimeException('图片流式生成失败，服务商返回 HTTP '.$status.'。');
            }
        } finally {
            curl_close($curl);

            foreach ($temporaryFiles as $temporaryFile) {
                @unlink($temporaryFile);
            }
        }

        return collect($images)
            ->unique(fn (array $image): string => $image['url'] ?? $image['data_url'] ?? '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, string>>  $images
     * @param  callable(string, array<string, mixed>): bool  $emit
     */
    private function handleStreamEvent(string $event, array &$images, callable $emit): void
    {
        $dataLines = [];

        foreach (preg_split('/\R/', trim($event)) ?: [] as $line) {
            if (str_starts_with($line, 'data:')) {
                $dataLines[] = trim(substr($line, 5));
            }
        }

        $rawData = trim(implode("\n", $dataLines));

        if ($rawData === '' || $rawData === '[DONE]') {
            return;
        }

        $payload = json_decode($rawData, true);

        if (! is_array($payload)) {
            return;
        }

        $chunkImages = $this->extractImages($payload);

        if ($chunkImages === []) {
            return;
        }

        $images = [...$images, ...$chunkImages];

        $emit('partial', [
            'images' => $chunkImages,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function emitSse(string $event, array $payload): bool
    {
        echo 'event: '.$event."\n";
        echo 'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();

        return true;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function providerErrorResponse(?array $payload, int $status, string $fallback): JsonResponse
    {
        $message = data_get($payload, 'error.message')
            ?? data_get($payload, 'message')
            ?? $fallback;

        return response()->json([
            'message' => is_string($message) ? $message : $fallback,
        ], $status >= 400 && $status < 600 ? $status : 502);
    }
}
