<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AiImageController extends Controller
{
    public function index(): View
    {
        return view('ai-image.index');
    }

    public function models(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url:http,https', 'max:2048'],
            'api_key' => ['nullable', 'string', 'max:4096'],
        ]);

        $baseUrl = $this->apiBaseUrl((string) $data['endpoint']);
        try {
            $response = Http::timeout(30)
                ->acceptJson()
                ->withHeaders($this->apiHeaders($data['api_key'] ?? null))
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
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url:http,https', 'max:2048'],
            'api_key' => ['nullable', 'string', 'max:4096'],
            'model' => ['required', 'string', 'max:255'],
            'prompt' => ['required', 'string', 'max:5000'],
            'negative_prompt' => ['nullable', 'string', 'max:2000'],
            'count' => ['required', 'integer', 'min:1', 'max:8'],
            'size_mode' => ['required', 'in:ratio,custom'],
            'ratio' => ['nullable', 'in:1:1,4:3,3:4,16:9,9:16,21:9'],
            'width' => ['nullable', 'integer', 'min:256', 'max:4096'],
            'height' => ['nullable', 'integer', 'min:256', 'max:4096'],
            'quality' => ['nullable', 'in:auto,standard,hd,high,medium,low'],
            'style' => ['nullable', 'in:auto,vivid,natural'],
            'background' => ['nullable', 'in:auto,transparent,opaque'],
            'output_format' => ['nullable', 'in:auto,png,jpeg,webp'],
            'response_format' => ['nullable', 'in:auto,url,b64_json'],
            'seed' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'steps' => ['nullable', 'integer', 'min:1', 'max:150'],
            'guidance_scale' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'reference_images' => ['nullable', 'array', 'max:6'],
            'reference_images.*' => ['image', 'max:10240'],
        ]);

        if (($data['size_mode'] ?? null) === 'custom' && (empty($data['width']) || empty($data['height']))) {
            throw ValidationException::withMessages([
                'width' => '自定义尺寸需要同时填写宽度和高度。',
            ]);
        }

        $baseUrl = $this->apiBaseUrl((string) $data['endpoint']);
        $payload = $this->generationPayload($data);
        $references = $request->file('reference_images', []);

        $pendingRequest = Http::timeout(180)
            ->acceptJson()
            ->withHeaders($this->apiHeaders($data['api_key'] ?? null));

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

                $response = $pendingRequest->post($baseUrl.'/images/edits', $payload);
            } else {
                $response = $pendingRequest->post($baseUrl.'/images/generations', $payload);
            }
        } catch (ConnectionException $exception) {
            return response()->json([
                'message' => '图片生成失败：'.$exception->getMessage(),
            ], 502);
        }

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

        return response()->json([
            'images' => $images,
        ]);
    }

    private function apiBaseUrl(string $endpoint): string
    {
        $url = $this->assertSafeEndpoint($endpoint);
        $url = rtrim($url, '/');

        return preg_replace('#/(models|images/generations|images/edits|responses|chat/completions)$#', '', $url) ?: $url;
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
            'size' => $this->requestedSize($data),
        ];

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

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requestedSize(array $data): string
    {
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

            foreach (['b64_json', 'base64', 'image'] as $key) {
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
