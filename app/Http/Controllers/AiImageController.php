<?php

namespace App\Http\Controllers;

use App\Models\AiUserConfig;
use App\Models\AiChatSession;
use App\Models\AiImageTask;
use App\Models\MediaAsset;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\AiUsageService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
            'feature' => ['nullable', 'in:image,chat'],
            'config_mode' => ['nullable', 'in:default,custom'],
            'config_id' => ['nullable', 'integer'],
            'config_name' => ['nullable', 'string', 'max:100'],
            'endpoint' => ['nullable', 'url:http,https', 'max:2048'],
            'api_key' => ['nullable', 'string', 'max:4096'],
            'chat_endpoint' => ['nullable', 'url:http,https', 'max:2048'],
            'chat_api_key' => ['nullable', 'string', 'max:4096'],
        ]);
        $feature = ($data['feature'] ?? 'image') === 'chat' ? 'chat' : 'image';
        $data = $this->applySavedUserConfig($request, $data);

        $config = $this->usage->resolveConfig($request->user(), $data, $feature);
        $baseUrl = $this->apiBaseUrl((string) $config['endpoint']);
        try {
            $response = $this->aiHttp(30)
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

        $models = $this->extractModels((array) $response->json(), $feature);

        return response()->json([
            'models' => $models,
            'feature' => $feature,
            'base_url' => $baseUrl,
            'config_name' => $config['config_name'],
            'provider_source' => $config['source'],
        ]);
    }

    public function configs(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'configs' => AiUserConfig::query()
                ->whereBelongsTo($user)
                ->latest()
                ->get()
                ->map(fn (AiUserConfig $config): array => $this->publicAiConfig($config))
                ->values(),
        ]);
    }

    public function saveConfig(Request $request): JsonResponse
    {
        $data = $request->validate([
            'config_id' => ['nullable', 'integer'],
            'name' => ['nullable', 'string', 'max:100'],
            'image_endpoint' => ['nullable', 'url:http,https', 'max:2048'],
            'image_api_key' => ['nullable', 'string', 'max:4096'],
            'chat_endpoint' => ['nullable', 'url:http,https', 'max:2048'],
            'chat_api_key' => ['nullable', 'string', 'max:4096'],
            'image_model' => ['nullable', 'string', 'max:255'],
            'chat_model' => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if (blank($data['image_endpoint'] ?? null) && blank($data['chat_endpoint'] ?? null)) {
            throw ValidationException::withMessages([
                'image_endpoint' => 'Please fill at least one image or chat API URL.',
            ]);
        }

        $user = $request->user();
        $config = AiUserConfig::query()
            ->whereBelongsTo($user)
            ->when($data['config_id'] ?? null, fn ($query, int $id) => $query->whereKey($id))
            ->first();

        if (! $config) {
            $config = new AiUserConfig(['user_id' => $user->id]);
        }

        $imageApiKey = filled($data['image_api_key'] ?? null)
            ? (string) $data['image_api_key']
            : $config->image_api_key;
        $chatApiKey = filled($data['chat_api_key'] ?? null)
            ? (string) $data['chat_api_key']
            : $config->chat_api_key;

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $name = $this->nextUserConfigName($user);
        }

        $config->fill([
            'name' => $name,
            'image_endpoint' => blank($data['image_endpoint'] ?? null) ? null : trim((string) $data['image_endpoint']),
            'image_api_key' => $imageApiKey,
            'chat_endpoint' => blank($data['chat_endpoint'] ?? null) ? null : trim((string) $data['chat_endpoint']),
            'chat_api_key' => $chatApiKey,
            'image_model' => blank($data['image_model'] ?? null) ? null : trim((string) $data['image_model']),
            'chat_model' => blank($data['chat_model'] ?? null) ? null : trim((string) $data['chat_model']),
            'is_default' => (bool) ($data['is_default'] ?? false),
        ]);
        $config->save();

        if ($config->is_default) {
            AiUserConfig::query()
                ->whereBelongsTo($user)
                ->whereKeyNot($config->id)
                ->update(['is_default' => false]);
        }

        return response()->json([
            'config' => $this->publicAiConfig($config->fresh()),
        ]);
    }

    private function nextUserConfigName(User $user): string
    {
        $count = AiUserConfig::query()
            ->whereBelongsTo($user)
            ->count();

        do {
            $count++;
            $name = '自定义配置 '.$count;
        } while (AiUserConfig::query()
            ->whereBelongsTo($user)
            ->where('name', $name)
            ->exists());

        return $name;
    }

    public function deleteConfig(Request $request, AiUserConfig $config): JsonResponse
    {
        abort_unless($config->user_id === $request->user()?->id, 403);

        $config->delete();

        return response()->json(['deleted' => true]);
    }

    public function state(Request $request): JsonResponse
    {
        $this->usage->purgeExpiredAiTrash();
        $user = $request->user();

        return response()->json([
            'tasks' => AiImageTask::query()
                ->whereBelongsTo($user)
                ->whereNull('deleted_at')
                ->latest()
                ->get()
                ->map(fn (AiImageTask $task): array => $task->toWorkbenchArray())
                ->values(),
            'trashed_tasks' => AiImageTask::query()
                ->whereBelongsTo($user)
                ->whereNotNull('deleted_at')
                ->latest('deleted_at')
                ->get()
                ->map(fn (AiImageTask $task): array => $task->toWorkbenchArray())
                ->values(),
            'chats' => AiChatSession::query()
                ->whereBelongsTo($user)
                ->whereNull('deleted_at')
                ->with('messages')
                ->latest('updated_at')
                ->get()
                ->map(fn (AiChatSession $session): array => $session->toWorkbenchArray())
                ->values(),
            'trashed_chats' => AiChatSession::query()
                ->whereBelongsTo($user)
                ->whereNotNull('deleted_at')
                ->with('messages')
                ->latest('deleted_at')
                ->get()
                ->map(fn (AiChatSession $session): array => $session->toWorkbenchArray())
                ->values(),
            'trash_retention_days' => $this->usage->aiTrashRetentionDays(),
        ]);
    }

    public function saveTask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'string', 'max:64'],
            'status' => ['required', 'in:running,done,failed'],
            'stream' => ['nullable', 'boolean'],
            'prompt' => ['nullable', 'string', 'max:5000'],
            'submittedPrompt' => ['nullable', 'string', 'max:8000'],
            'references' => ['nullable', 'array'],
            'config' => ['nullable', 'array'],
            'images' => ['nullable', 'array'],
            'partials' => ['nullable', 'array'],
            'error' => ['nullable', 'string', 'max:8000'],
            'createdAt' => ['nullable', 'date'],
            'updatedAt' => ['nullable', 'date'],
            'elapsedMs' => ['nullable', 'integer', 'min:0'],
            'actualWidth' => ['nullable', 'integer', 'min:0'],
            'actualHeight' => ['nullable', 'integer', 'min:0'],
            'meta' => ['nullable', 'array'],
        ]);

        $task = AiImageTask::query()
            ->whereBelongsTo($request->user())
            ->where('public_id', (string) ($data['id'] ?? ''))
            ->first();

        if (! $task) {
            $task = new AiImageTask([
                'user_id' => $request->user()->id,
                'public_id' => filled($data['id'] ?? null) ? (string) $data['id'] : null,
            ]);
        }

        $task->fill([
            'status' => $data['status'],
            'stream' => (bool) ($data['stream'] ?? false),
            'prompt' => $data['prompt'] ?? '',
            'submitted_prompt' => $data['submittedPrompt'] ?? ($data['prompt'] ?? ''),
            'references' => $data['references'] ?? [],
            'config' => $data['config'] ?? [],
            'images' => $data['images'] ?? [],
            'partials' => $data['partials'] ?? [],
            'error' => $data['error'] ?? '',
            'elapsed_ms' => (int) ($data['elapsedMs'] ?? 0),
            'actual_width' => $data['actualWidth'] ?? null,
            'actual_height' => $data['actualHeight'] ?? null,
            'meta' => $data['meta'] ?? [],
        ]);
        if (! $task->exists && filled($data['createdAt'] ?? null)) {
            $task->created_at = $data['createdAt'];
        }

        if (filled($data['updatedAt'] ?? null)) {
            $task->updated_at = $data['updatedAt'];
        }
        $task->save();

        return response()->json([
            'task' => $task->fresh()->toWorkbenchArray(),
        ]);
    }

    public function deleteTask(Request $request, string $task): JsonResponse
    {
        $record = AiImageTask::query()
            ->whereBelongsTo($request->user())
            ->where('public_id', $task)
            ->firstOrFail();

        $record->forceFill(['deleted_at' => now()])->save();

        return response()->json(['deleted' => true]);
    }

    public function restoreTask(Request $request, string $task): JsonResponse
    {
        $record = AiImageTask::query()
            ->whereBelongsTo($request->user())
            ->where('public_id', $task)
            ->firstOrFail();

        $record->forceFill(['deleted_at' => null])->save();

        return response()->json([
            'task' => $record->fresh()->toWorkbenchArray(),
        ]);
    }

    public function saveChatSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'string', 'max:64'],
            'title' => ['nullable', 'string', 'max:255'],
            'createdAt' => ['nullable', 'date'],
            'updatedAt' => ['nullable', 'date'],
            'messages' => ['nullable', 'array', 'max:200'],
            'messages.*.role' => ['required', 'in:user,assistant,system'],
            'messages.*.content' => ['nullable', 'string', 'max:20000'],
            'messages.*.files' => ['nullable', 'array'],
            'messages.*.model' => ['nullable', 'string', 'max:255'],
            'messages.*.reasoning' => ['nullable', 'string', 'max:50'],
            'messages.*.reasoningLabel' => ['nullable', 'string', 'max:50'],
            'messages.*.error' => ['nullable', 'boolean'],
        ]);

        $session = AiChatSession::query()
            ->whereBelongsTo($request->user())
            ->where('public_id', (string) ($data['id'] ?? ''))
            ->first();

        if (! $session) {
            $session = new AiChatSession([
                'user_id' => $request->user()->id,
                'public_id' => filled($data['id'] ?? null) ? (string) $data['id'] : null,
            ]);
        }

        $session->fill([
            'title' => trim((string) ($data['title'] ?? '')) ?: '新会话',
        ]);
        if (! $session->exists && filled($data['createdAt'] ?? null)) {
            $session->created_at = $data['createdAt'];
        }

        if (filled($data['updatedAt'] ?? null)) {
            $session->updated_at = $data['updatedAt'];
        }
        $session->save();

        $session->messages()->delete();

        foreach (array_slice($data['messages'] ?? [], -200) as $message) {
            $session->messages()->create([
                'user_id' => $request->user()->id,
                'role' => $message['role'],
                'content' => $message['content'] ?? '',
                'files' => $message['files'] ?? [],
                'model' => $message['model'] ?? '',
                'reasoning_mode' => $message['reasoning'] ?? '',
                'reasoning_label' => $message['reasoningLabel'] ?? '',
                'is_error' => (bool) ($message['error'] ?? false),
            ]);
        }

        $session->touch();

        return response()->json([
            'chat' => $session->fresh()->load('messages')->toWorkbenchArray(),
        ]);
    }

    public function deleteChatSession(Request $request, string $session): JsonResponse
    {
        $record = AiChatSession::query()
            ->whereBelongsTo($request->user())
            ->where('public_id', $session)
            ->firstOrFail();

        $record->forceFill(['deleted_at' => now()])->save();

        return response()->json(['deleted' => true]);
    }

    public function restoreChatSession(Request $request, string $session): JsonResponse
    {
        $record = AiChatSession::query()
            ->whereBelongsTo($request->user())
            ->where('public_id', $session)
            ->with('messages')
            ->firstOrFail();

        $record->forceFill(['deleted_at' => null])->save();

        return response()->json([
            'chat' => $record->fresh()->load('messages')->toWorkbenchArray(),
        ]);
    }

    public function uploadReference(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reference_image' => ['required', 'image', 'max:10240'],
        ]);

        /** @var UploadedFile $file */
        $file = $data['reference_image'];
        $hash = hash_file('sha256', $file->getRealPath());
        $asset = MediaAsset::query()
            ->where('content_hash', $hash)
            ->where('usage', MediaAsset::USAGE_AI_REFERENCE)
            ->first();

        if (! $asset) {
            $extension = $file->guessExtension() ?: 'png';
            $path = 'ai/references/'.$hash.'.'.$extension;
            Storage::disk('public_uploads')->put($path, file_get_contents($file->getRealPath()) ?: '');
            $asset = MediaAsset::query()->create([
                'name' => $file->getClientOriginalName() ?: 'AI reference image',
                'path' => $path,
                'content_hash' => $hash,
                'disk' => 'public_uploads',
                'mime_type' => $file->getMimeType() ?: 'image/png',
                'size' => $file->getSize(),
                'usage' => MediaAsset::USAGE_AI_REFERENCE,
                'library' => MediaAsset::LIBRARY_SITE,
                'uploaded_by_id' => $request->user()?->id,
            ]);
        }

        return response()->json([
            'asset' => [
                'id' => $asset->id,
                'name' => $asset->name,
                'path' => $asset->path,
                'url' => $asset->url(),
                'hash' => $asset->content_hash,
            ],
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $this->validatedGenerationData($request);
        $data = $this->applySavedUserConfig($request, $data);
        $usedMode = $this->initialImageApiMode($data);

        try {
            $config = $this->usage->resolveConfig($request->user(), $data, 'image');
        } catch (ValidationException $exception) {
            if ($usedMode !== 'responses') {
                throw $exception;
            }

            $config = $this->usage->resolveConfig($request->user(), $data, 'chat');
        }

        if (($config['tracked'] ?? false) && $request->user()) {
            $this->usage->assertWithinQuota($request->user(), $data);
        }

        $baseUrl = $this->apiBaseUrl((string) $config['endpoint']);
        $payload = $this->generationPayload($data);
        $references = $this->generationReferenceFiles($request, $data);
        $mask = $request->file('mask_image');
        $responsesConfig = null;
        $usedConfig = $config;
        $usedBaseUrl = $baseUrl;
        $timeout = (int) ($data['timeout_seconds'] ?? 600);

        $resolveResponsesConfig = function (bool $required = false) use ($request, $data, &$responsesConfig): ?array {
            if ($responsesConfig !== null) {
                return $responsesConfig;
            }

            try {
                $responsesConfig = $this->usage->resolveConfig($request->user(), $data, 'chat');
            } catch (ValidationException $exception) {
                if ($required) {
                    throw $exception;
                }

                return null;
            }

            return $responsesConfig;
        };

        $makePendingRequest = fn (array $requestConfig) => $this->aiHttp($timeout)
            ->acceptJson()
            ->withHeaders($this->apiHeaders($requestConfig['api_key'] ?? null));

        $sendAttempt = function (string $mode, bool $requiredResponses = false) use ($config, &$payload, $references, $mask, $makePendingRequest, $resolveResponsesConfig, &$usedConfig, &$usedBaseUrl): ?\Illuminate\Http\Client\Response {
            $attemptConfig = $mode === 'responses' ? $resolveResponsesConfig($requiredResponses) : $config;

            if ($attemptConfig === null) {
                return null;
            }

            $usedConfig = $attemptConfig;
            $usedBaseUrl = $this->apiBaseUrl((string) $attemptConfig['endpoint']);

            return $this->sendImageGenerationByMode($makePendingRequest($attemptConfig), $usedBaseUrl, $payload, $references, $mask, $mode);
        };

        $startedAt = microtime(true);
        try {
            $response = $sendAttempt($usedMode, $usedMode === 'responses');
        } catch (ConnectionException $exception) {
            return response()->json([
                'message' => '图片生成失败：'.$exception->getMessage(),
            ], 502);
        }

        if ($response === null) {
            return response()->json([
                'message' => 'Responses API image generation requires a chat/Responses API URL and key.',
            ], 422);
        }

        $requestMs = (int) round((microtime(true) - $startedAt) * 1000);
        $providerPayload = (array) $response->json();

        if ($response->failed() && $this->shouldTryAlternateImageMode($data, $providerPayload, $usedMode)) {
            $failedMode = $usedMode;

            foreach ($this->fallbackImageApiModes($data, $failedMode) as $fallbackMode) {
                if ($fallbackMode === $failedMode) {
                    continue;
                }

                $usedMode = $fallbackMode;
                $startedAt = microtime(true);

                try {
                    $fallbackResponse = $sendAttempt($usedMode);
                } catch (ConnectionException $exception) {
                    return response()->json([
                        'message' => '图片生成失败：'.$exception->getMessage(),
                    ], 502);
                }

                if ($fallbackResponse === null) {
                    continue;
                }

                $response = $fallbackResponse;
                $requestMs = (int) round((microtime(true) - $startedAt) * 1000);
                $providerPayload = (array) $response->json();

                if (! $response->failed()) {
                    break;
                }
            }
        }

        if ($response->failed()) {
            $fallbackModels = $this->fallbackImageModels($baseUrl, $config['api_key'] ?? null, $providerPayload, trim((string) $data['model']));

            foreach ($fallbackModels as $fallbackModel) {
                foreach ($this->fallbackImageApiModes($data, $usedMode) as $fallbackMode) {
                    $data['model'] = $fallbackModel;
                    $payload['model'] = $fallbackModel;
                    $usedMode = $fallbackMode;
                    $startedAt = microtime(true);

                    try {
                        $fallbackResponse = $sendAttempt($usedMode);
                    } catch (ConnectionException $exception) {
                        return response()->json([
                            'message' => '图片生成失败：'.$exception->getMessage(),
                        ], 502);
                    }

                    if ($fallbackResponse === null) {
                        continue;
                    }

                    $response = $fallbackResponse;
                    $requestMs = (int) round((microtime(true) - $startedAt) * 1000);
                    $providerPayload = (array) $response->json();

                    if (! $response->failed()) {
                        $usedConfig['fallback_model'] = $fallbackModel;
                        $providerPayload['_shopweb_fallback_model'] = $fallbackModel;
                        break 2;
                    }
                }
            }
        }

        if ($response->failed()) {
            return $this->providerErrorResponse($providerPayload, $response->status(), '图片生成失败。');
        }

        $usedConfig['image_api_mode'] = $usedMode;

        $images = $this->extractImagesFromResponse($providerPayload, $response, $usedConfig);

        if ($images === [] && ($data['image_api_mode'] ?? 'auto') === 'auto') {
            $failedMode = $usedMode;

            foreach ($this->fallbackImageApiModes($data, $failedMode) as $fallbackMode) {
                if ($fallbackMode === $failedMode || $fallbackMode === $usedMode) {
                    continue;
                }

                $startedAt = microtime(true);

                try {
                    $fallbackResponse = $sendAttempt($fallbackMode);
                } catch (ConnectionException $exception) {
                    return response()->json([
                        'message' => '图片生成失败：'.$exception->getMessage(),
                    ], 502);
                }

                if ($fallbackResponse === null || $fallbackResponse->failed()) {
                    continue;
                }

                $fallbackPayload = (array) $fallbackResponse->json();
                $fallbackImages = $this->extractImagesFromResponse($fallbackPayload, $fallbackResponse, $usedConfig);

                if ($fallbackImages === []) {
                    continue;
                }

                $response = $fallbackResponse;
                $requestMs = (int) round((microtime(true) - $startedAt) * 1000);
                $providerPayload = $fallbackPayload;
                $usedMode = $fallbackMode;
                $usedConfig['image_api_mode'] = $usedMode;
                $images = $fallbackImages;
                break;
            }
        }

        if ($images === []) {
            return response()->json([
                'message' => '服务商没有返回可识别的图片数据。',
                'provider_payload_preview' => $this->providerPayloadPreview($providerPayload),
            ], 502);
        }

        $this->usage->record($request->user(), $data, $usedConfig, $providerPayload, $requestMs, metadata: [
            'feature' => 'image',
            'image_count' => count($images),
            'stream' => false,
        ]);

        return response()->json([
            'images' => $images,
            'meta' => $this->generationMeta($data, $usedBaseUrl, $usedConfig),
        ]);
    }

    public function stream(Request $request): StreamedResponse
    {
        $data = $this->validatedGenerationData($request);
        $data = $this->applySavedUserConfig($request, $data);
        $data['count'] = 1;
        $data['stream'] = true;
        $usedMode = $this->initialStreamImageApiMode($data);
        $config = $this->usage->resolveConfig($request->user(), $data, $usedMode === 'responses' ? 'chat' : 'image');

        if (($config['tracked'] ?? false) && $request->user()) {
            $this->usage->assertWithinQuota($request->user(), $data);
        }

        $baseUrl = $this->apiBaseUrl((string) $config['endpoint']);
        $payload = $this->generationPayload($data);
        $referenceFiles = $this->generationReferenceFiles($request, $data);
        $references = $this->referenceAttachments($referenceFiles);
        $mask = $this->uploadedAttachment($request->file('mask_image'), 'mask.png');
        $timeout = (int) ($data['timeout_seconds'] ?? 600);
        $headers = $this->apiHeaders($config['api_key'] ?? null);
        $user = $request->user();
        $usage = $this->usage;
        if ($usedMode === 'responses') {
            $payload = $this->responsesImagePayload($payload, $referenceFiles);
            $references = [];
            $mask = null;
        }

        return response()->stream(function () use ($baseUrl, $payload, $references, $mask, $timeout, $headers, $data, $config, $user, $usage, $usedMode): void {
            $emit = fn (string $event, array $payload): bool => $this->emitSse($event, $payload);

            $emit('started', [
                'created_at' => now()->toIso8601String(),
            ]);

            $startedAt = microtime(true);

            try {
                $images = $this->streamImages($baseUrl, $payload, $references, $mask, $timeout, $headers, $emit, $usedMode);
                $requestMs = (int) round((microtime(true) - $startedAt) * 1000);

                if ($images === []) {
                    $emit('error', [
                        'message' => '服务商没有返回可识别的图片数据。',
                    ]);

                    return;
                }

                $emit('done', [
                    'images' => $images,
                    'meta' => $this->generationMeta($data, $baseUrl, [
                        ...$config,
                        'image_api_mode' => $usedMode,
                    ]),
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

    public function chat(Request $request): JsonResponse
    {
        $data = $this->validatedChatData($request);
        $data = $this->applySavedUserConfig($request, $data);
        $config = $this->usage->resolveConfig($request->user(), $data, 'chat');

        if (($config['tracked'] ?? false) && $request->user()) {
            $this->usage->assertWithinQuota($request->user(), [
                ...$data,
                'count' => 1,
            ]);
        }

        $baseUrl = $this->apiBaseUrl((string) $config['endpoint']);
        $payload = $this->chatPayload($data, $request->file('chat_files', []));

        $startedAt = microtime(true);
        try {
            $response = $this->aiHttp((int) ($data['timeout_seconds'] ?? 600))
                ->acceptJson()
                ->withHeaders($this->apiHeaders($config['api_key'] ?? null))
                ->post($baseUrl.'/chat/completions', $payload);
        } catch (ConnectionException $exception) {
            return response()->json([
                'message' => 'AI 聊天请求失败：'.$exception->getMessage(),
            ], 502);
        }

        $requestMs = (int) round((microtime(true) - $startedAt) * 1000);
        $providerPayload = (array) $response->json();

        if ($response->failed()) {
            return $this->providerErrorResponse($providerPayload, $response->status(), 'AI 聊天请求失败。');
        }

        $message = $this->extractChatText($providerPayload);

        if ($message === '') {
            return response()->json([
                'message' => '服务商没有返回可识别的聊天内容。',
            ], 502);
        }

        $this->usage->record($request->user(), [
            ...$data,
            'count' => 1,
        ], $config, $providerPayload, $requestMs, metadata: [
            'feature' => 'chat',
            'reasoning_mode' => $data['reasoning_mode'],
            'web_search' => (bool) ($data['web_search'] ?? false),
            'attachment_count' => count($request->file('chat_files', [])),
        ]);

        return response()->json([
            'message' => $message,
            'meta' => [
                'source' => parse_url($baseUrl, PHP_URL_HOST) ?: $baseUrl,
                'config_name' => (string) ($config['config_name'] ?? ''),
                'provider_source' => (string) ($config['source'] ?? ''),
                'model' => trim((string) $data['model']),
                'reasoning_mode' => $data['reasoning_mode'],
                'web_search' => (bool) ($data['web_search'] ?? false),
                'request_ms' => $requestMs,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applySavedUserConfig(Request $request, array $data): array
    {
        $configId = (int) ($data['config_id'] ?? 0);

        if ($configId <= 0 || ! $request->user()) {
            return $data;
        }

        $config = AiUserConfig::query()
            ->whereBelongsTo($request->user())
            ->whereKey($configId)
            ->first();

        if (! $config) {
            throw ValidationException::withMessages([
                'config_id' => 'AI config not found.',
            ]);
        }

        $isChat = ($data['feature'] ?? null) === 'chat'
            || array_key_exists('reasoning_mode', $data)
            || array_key_exists('web_search', $data)
            || array_key_exists('chat_files', $data);

        return [
            ...$data,
            'config_mode' => 'custom',
            'config_name' => $config->name,
            'endpoint' => $config->image_endpoint ?: $config->chat_endpoint,
            'api_key' => $config->image_api_key ?: $config->chat_api_key,
            'chat_endpoint' => $config->chat_endpoint ?: $config->image_endpoint,
            'chat_api_key' => $config->chat_api_key ?: $config->image_api_key,
            'model' => $isChat
                ? ($config->chat_model ?: ($data['model'] ?? null))
                : ($config->image_model ?: ($data['model'] ?? null)),
        ];
    }

    private function publicAiConfig(?AiUserConfig $config): array
    {
        if (! $config) {
            return [];
        }

        return [
            'id' => $config->id,
            'name' => $config->name,
            'image_endpoint' => $config->image_endpoint,
            'chat_endpoint' => $config->chat_endpoint,
            'image_model' => $config->image_model,
            'chat_model' => $config->chat_model,
            'is_default' => (bool) $config->is_default,
            'has_image_key' => filled($config->image_api_key),
            'has_chat_key' => filled($config->chat_api_key),
        ];
    }

    private function apiBaseUrl(string $endpoint): string
    {
        $url = $this->assertSafeEndpoint($endpoint);
        $url = rtrim($url, '/');

        return preg_replace('#/(models|images/generations|images/edits|images|responses|chat/completions)$#', '', $url) ?: $url;
    }

    /**
     * @return array<int, UploadedFile|array{name:string,content:string,mime_type:string}>
     */
    private function generationReferenceFiles(Request $request, array $data): array
    {
        $references = array_values($request->file('reference_images', []) ?: []);
        $assetIds = collect($data['reference_asset_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->take(6 - count($references));

        if ($assetIds->isEmpty()) {
            return $references;
        }

        MediaAsset::query()
            ->whereIn('id', $assetIds)
            ->where('usage', MediaAsset::USAGE_AI_REFERENCE)
            ->get()
            ->each(function (MediaAsset $asset) use (&$references): void {
                if ($asset->disk === 'external' || ! Storage::disk($asset->disk ?: 'public_uploads')->exists($asset->path)) {
                    return;
                }

                $references[] = [
                    'name' => $asset->name ?: basename($asset->path),
                    'content' => Storage::disk($asset->disk ?: 'public_uploads')->get($asset->path),
                    'mime_type' => $asset->mime_type ?: 'image/png',
                ];
            });

        return array_slice($references, 0, 6);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedGenerationData(Request $request): array
    {
        $data = $request->validate([
            'config_mode' => ['nullable', 'in:default,custom'],
            'config_id' => ['nullable', 'integer'],
            'config_name' => ['nullable', 'string', 'max:100'],
            'endpoint' => ['nullable', 'url:http,https', 'max:2048'],
            'api_key' => ['nullable', 'string', 'max:4096'],
            'chat_endpoint' => ['nullable', 'url:http,https', 'max:2048'],
            'chat_api_key' => ['nullable', 'string', 'max:4096'],
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
            'image_api_mode' => ['nullable', 'in:auto,images,images_root,responses'],
            'seed' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'steps' => ['nullable', 'integer', 'min:1', 'max:150'],
            'guidance_scale' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'stream' => ['nullable', 'boolean'],
            'partial_images' => ['nullable', 'integer', 'min:0', 'max:3'],
            'timeout_seconds' => ['nullable', 'integer', 'min:30', 'max:1200'],
            'reference_images' => ['nullable', 'array', 'max:6'],
            'reference_images.*' => ['image', 'max:10240'],
            'reference_asset_ids' => ['nullable', 'array', 'max:6'],
            'reference_asset_ids.*' => ['integer'],
            'mask_image' => ['nullable', 'image', 'max:10240'],
        ]);

        if (($data['size_mode'] ?? null) === 'custom' && (empty($data['width']) || empty($data['height']))) {
            throw ValidationException::withMessages([
                'width' => '自定义尺寸需要同时填写宽度和高度。',
            ]);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedChatData(Request $request): array
    {
        return $request->validate([
            'config_mode' => ['nullable', 'in:default,custom'],
            'config_id' => ['nullable', 'integer'],
            'config_name' => ['nullable', 'string', 'max:100'],
            'endpoint' => ['nullable', 'url:http,https', 'max:2048'],
            'api_key' => ['nullable', 'string', 'max:4096'],
            'chat_endpoint' => ['nullable', 'url:http,https', 'max:2048'],
            'chat_api_key' => ['nullable', 'string', 'max:4096'],
            'model' => ['required', 'string', 'max:255'],
            'prompt' => ['required', 'string', 'max:5000'],
            'reasoning_mode' => ['nullable', 'in:low,medium,high,ultra'],
            'web_search' => ['nullable', 'boolean'],
            'timeout_seconds' => ['nullable', 'integer', 'min:30', 'max:1200'],
            'chat_files' => ['nullable', 'array', 'max:6'],
            'chat_files.*' => ['file', 'max:20480'],
        ]);
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

    private function aiHttp(int $timeout): \Illuminate\Http\Client\PendingRequest
    {
        $request = Http::timeout($timeout)->connectTimeout(min(30, max(5, $timeout)));
        $options = $this->aiHttpOptions();

        return $options === [] ? $request : $request->withOptions($options);
    }

    /**
     * @return array<string, mixed>
     */
    private function aiHttpOptions(): array
    {
        if (! (bool) config('services.ai_http.verify_ssl', true)) {
            return ['verify' => false];
        }

        $caBundle = trim((string) config('services.ai_http.ca_bundle', ''));

        if ($caBundle !== '') {
            $path = base_path($caBundle);

            if (! is_file($path)) {
                $path = $caBundle;
            }

            if (is_file($path)) {
                return ['verify' => $path];
            }
        }

        if ((bool) config('services.ai_http.use_native_ca', true) && defined('CURLOPT_SSL_OPTIONS') && defined('CURLSSLOPT_NATIVE_CA')) {
            return [
                'curl' => [
                    CURLOPT_SSL_OPTIONS => CURLSSLOPT_NATIVE_CA,
                ],
            ];
        }

        return [];
    }

    /**
     * @return array<int, mixed>
     */
    private function aiCurlOptions(): array
    {
        if (! (bool) config('services.ai_http.verify_ssl', true)) {
            return [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ];
        }

        $caBundle = trim((string) config('services.ai_http.ca_bundle', ''));

        if ($caBundle !== '') {
            $path = base_path($caBundle);

            if (! is_file($path)) {
                $path = $caBundle;
            }

            if (is_file($path)) {
                return [
                    CURLOPT_CAINFO => $path,
                ];
            }
        }

        if ((bool) config('services.ai_http.use_native_ca', true) && defined('CURLOPT_SSL_OPTIONS') && defined('CURLSSLOPT_NATIVE_CA')) {
            return [
                CURLOPT_SSL_OPTIONS => CURLSSLOPT_NATIVE_CA,
            ];
        }

        return [];
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
    private function extractModels(array $payload, string $feature = 'image'): array
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

        if ($feature === 'chat') {
            $chatModels = $models
                ->reject(fn (array $model): bool => $this->looksLikeImageModel($model['id'].' '.$model['name']))
                ->values();

            return ($chatModels->isNotEmpty() ? $chatModels : $models)->all();
        }

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

    private function initialImageApiMode(array $data): string
    {
        return match ($data['image_api_mode'] ?? 'auto') {
            'responses' => 'responses',
            'images_root' => 'images_root',
            default => 'images',
        };
    }

    private function initialStreamImageApiMode(array $data): string
    {
        return match ($data['image_api_mode'] ?? 'auto') {
            'images' => 'images',
            'images_root' => 'images_root',
            default => 'responses',
        };
    }

    private function shouldTryAlternateImageMode(array $data, array $payload, string $usedMode): bool
    {
        if (($data['image_api_mode'] ?? 'auto') !== 'auto' || ! in_array($usedMode, ['images', 'images_root'], true)) {
            return false;
        }

        return $this->shouldRetryWithListedModel($payload);
    }

    /**
     * @return array<int, string>
     */
    private function fallbackImageApiModes(array $data, string $usedMode): array
    {
        if (($data['image_api_mode'] ?? 'auto') === 'images') {
            return ['images'];
        }

        if (($data['image_api_mode'] ?? 'auto') === 'images_root') {
            return ['images_root'];
        }

        if (($data['image_api_mode'] ?? 'auto') === 'responses') {
            return ['responses'];
        }

        return collect([$usedMode, 'images_root', 'responses', 'images'])
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, UploadedFile>|UploadedFile|null  $references
     */
    private function sendImageGenerationByMode(
        \Illuminate\Http\Client\PendingRequest $request,
        string $baseUrl,
        array $payload,
        array|UploadedFile|null $references,
        mixed $mask,
        string $mode,
    ): \Illuminate\Http\Client\Response {
        if ($mode === 'responses') {
            return $request->post($baseUrl.'/responses', $this->responsesImagePayload($payload, $references));
        }

        if ($mode === 'images_root') {
            return $this->sendImageGenerationRequest($request, $baseUrl, $payload, $references, $mask, useRootImagesEndpoint: true);
        }

        return $this->sendImageGenerationRequest($request, $baseUrl, $payload, $references, $mask);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, UploadedFile>|UploadedFile|null  $references
     */
    private function sendImageGenerationRequest(
        \Illuminate\Http\Client\PendingRequest $request,
        string $baseUrl,
        array $payload,
        array|UploadedFile|null $references,
        mixed $mask,
        bool $useRootImagesEndpoint = false,
    ): \Illuminate\Http\Client\Response {
        $references = $references instanceof UploadedFile ? [$references] : array_values($references ?? []);

        if ($references !== []) {
            foreach ($references as $index => $file) {
                $attachment = $this->attachmentData($file, $index);

                if ($attachment === null) {
                    continue;
                }

                $request = $request->attach(
                    count($references) === 1 ? 'image' : 'image[]',
                    $attachment['content'],
                    $attachment['name'],
                );
            }

            if ($mask instanceof UploadedFile) {
                $request = $request->attach(
                    'mask',
                    file_get_contents($mask->getRealPath()),
                    $mask->getClientOriginalName() ?: 'mask.png',
                );
            }

            return $request->post($baseUrl.($useRootImagesEndpoint ? '/images' : '/images/edits'), $payload);
        }

        return $request->post($baseUrl.($useRootImagesEndpoint ? '/images' : '/images/generations'), $payload);
    }

    /**
     * @return array{name:string,content:string,mime_type:string}|null
     */
    private function attachmentData(mixed $file, int $index = 0): ?array
    {
        if ($file instanceof UploadedFile) {
            return [
                'name' => $file->getClientOriginalName() ?: 'reference-'.$index.'.png',
                'content' => file_get_contents($file->getRealPath()) ?: '',
                'mime_type' => $file->getMimeType() ?: 'image/png',
            ];
        }

        if (is_array($file) && is_string($file['content'] ?? null)) {
            return [
                'name' => (string) ($file['name'] ?? 'reference-'.$index.'.png'),
                'content' => (string) $file['content'],
                'mime_type' => (string) ($file['mime_type'] ?? 'image/png'),
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, UploadedFile>|UploadedFile|null  $references
     * @return array<string, mixed>
     */
    private function responsesImagePayload(array $payload, array|UploadedFile|null $references): array
    {
        $references = $references instanceof UploadedFile ? [$references] : array_values($references ?? []);
        $prompt = (string) ($payload['prompt'] ?? '');
        $content = [];

        foreach ($references as $file) {
            $attachment = $this->attachmentData($file);

            if ($attachment === null || ! str_starts_with($attachment['mime_type'], 'image/')) {
                continue;
            }

            $content[] = [
                'type' => 'input_image',
                'image_url' => $this->dataUrl(
                    base64_encode($attachment['content']),
                    $attachment['mime_type'],
                ),
            ];
        }

        if ($content !== []) {
            array_unshift($content, [
                'type' => 'input_text',
                'text' => $prompt,
            ]);
        }

        $responsePayload = [
            'model' => $this->responsesImageModel((string) $payload['model']),
            'input' => $content === [] ? $prompt : [[
                'role' => 'user',
                'content' => $content,
            ]],
            'tools' => [[
                'type' => 'image_generation',
            ]],
            'tool_choice' => [
                'type' => 'image_generation',
            ],
        ];

        foreach (['size', 'quality', 'background', 'output_format'] as $field) {
            if (isset($payload[$field])) {
                $responsePayload['tools'][0][$field] = $payload[$field];
            }
        }

        if (isset($payload['stream'])) {
            $responsePayload['stream'] = (bool) $payload['stream'];
        }

        if (isset($payload['partial_images'])) {
            $responsePayload['tools'][0]['partial_images'] = $payload['partial_images'];
        }

        return $responsePayload;
    }

    private function responsesImageModel(string $imageModel): string
    {
        $configured = trim((string) config('services.ai_http.responses_image_model', ''));

        if ($configured !== '') {
            return $configured;
        }

        return $this->looksLikeImageModel($imageModel) ? 'gpt-5.5' : $imageModel;
    }

    /**
     * @return array<int, string>
     */
    private function fallbackImageModels(string $baseUrl, ?string $apiKey, array $payload, string $failedModel): array
    {
        if (! $this->shouldRetryWithListedModel($payload)) {
            return [];
        }

        $models = collect();

        try {
            $response = $this->aiHttp(30)
                ->acceptJson()
                ->withHeaders($this->apiHeaders($apiKey))
                ->get($baseUrl.'/models');

            if (! $response->failed()) {
                $models = collect($this->extractModels((array) $response->json(), 'image'))
                    ->pluck('id');
            }
        } catch (ConnectionException) {
            $models = collect();
        }

        return $models
            ->merge($this->compatibleImageFallbackModels())
            ->map(fn (mixed $model): string => trim((string) $model))
            ->filter(fn (string $model): bool => $model !== '' && $model !== $failedModel)
            ->unique()
            ->take(4)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function compatibleImageFallbackModels(): array
    {
        return [
            'gpt-image-1',
            'dall-e-3',
            'dall-e-2',
        ];
    }

    private function shouldRetryWithListedModel(array $payload): bool
    {
        $message = Str::lower($this->rawProviderErrorDetail($payload));

        if ($message === '') {
            return false;
        }

        foreach ([
            'no available channel',
            'channel',
            'model_not_found',
            'model not found',
            'model_not_supported',
            'model not supported',
            'model unavailable',
            'model is not available',
            '模型不存在',
            '模型无权限',
            '无可用渠道',
            '没有可用渠道',
            '未启用',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function rawProviderErrorDetail(?array $payload): string
    {
        if ($payload === null) {
            return '';
        }

        $candidates = [
            data_get($payload, 'error.message'),
            data_get($payload, 'error.detail'),
            data_get($payload, 'error.type'),
            data_get($payload, 'message'),
            data_get($payload, 'detail'),
            data_get($payload, 'msg'),
        ];

        return collect($candidates)
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => trim($value))
            ->implode('；');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, string>>
     */
    private function extractImages(array $payload): array
    {
        $images = [];
        $items = [$payload];
        $scanned = 0;

        while ($items !== [] && $scanned < 500) {
            $item = array_shift($items);
            $scanned++;

            if (is_string($item)) {
                $this->appendImageString($images, $item);

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            if (($item['type'] ?? null) === 'image_generation_call' && is_string($item['result'] ?? null)) {
                $images[] = [
                    'data_url' => $this->dataUrl($item['result'], (string) ($item['mime_type'] ?? 'image/png')),
                    'revised_prompt' => (string) ($item['revised_prompt'] ?? ''),
                ];
            }

            foreach (['data', 'images', 'artifacts', 'output', 'choices', 'content', 'message', 'delta', 'result', 'results', 'image', 'output_image', 'image_file'] as $key) {
                if (! isset($item[$key]) || ! is_array($item[$key])) {
                    continue;
                }

                $items = [
                    ...$items,
                    ...(array_is_list($item[$key]) ? array_values($item[$key]) : [$item[$key]]),
                ];
            }

            foreach (['url', 'image_url', 'imageUrl', 'file_url', 'download_url', 'content_url', 'src'] as $key) {
                $source = $item[$key] ?? null;

                if (is_array($source)) {
                    $items[] = $source;

                    continue;
                }

                if (is_string($source)) {
                    $this->appendImageString($images, $source, (string) ($item['revised_prompt'] ?? ''));
                }
            }

            foreach (['content', 'text', 'output_text', 'message'] as $key) {
                if (is_string($item[$key] ?? null)) {
                    $this->appendImageString($images, $item[$key], (string) ($item['revised_prompt'] ?? ''));
                }
            }

            foreach (['b64_json', 'base64', 'b64', 'image', 'output_image', 'partial_image_b64', 'partial_image', 'image_b64', 'image_base64', 'result'] as $key) {
                $value = $item[$key] ?? null;

                if (is_string($value)) {
                    if (in_array($key, ['image', 'result'], true)) {
                        $countBefore = count($images);
                        $this->appendImageString($images, $value, (string) ($item['revised_prompt'] ?? ''));

                        if (count($images) > $countBefore) {
                            continue;
                        }
                    }

                    $images[] = [
                        'data_url' => $this->dataUrl($value, (string) ($item['mime_type'] ?? 'image/png')),
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
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, string>>
     */
    private function extractImagesFromResponse(array $payload, \Illuminate\Http\Client\Response $response, array $config): array
    {
        $images = $this->extractImages($payload);

        if ($images !== []) {
            return $images;
        }

        $contentType = Str::lower($response->header('Content-Type') ?: '');

        if (str_starts_with($contentType, 'image/')) {
            return [[
                'data_url' => $this->dataUrl(base64_encode($response->body()), $contentType),
                'revised_prompt' => '',
            ]];
        }

        $fileIds = $this->extractImageFileIds($payload);

        if ($fileIds === []) {
            return [];
        }

        $baseUrl = $this->apiBaseUrl((string) ($config['endpoint'] ?? ''));
        $apiKey = $config['api_key'] ?? null;
        $resolved = [];

        foreach ($fileIds as $fileId) {
            try {
                $fileResponse = $this->aiHttp(120)
                    ->accept('*/*')
                    ->withHeaders($this->apiHeaders($apiKey))
                    ->get($baseUrl.'/files/'.rawurlencode($fileId).'/content');
            } catch (ConnectionException) {
                continue;
            }

            if ($fileResponse->failed()) {
                continue;
            }

            $fileContentType = Str::lower($fileResponse->header('Content-Type') ?: 'image/png');

            if (str_contains($fileContentType, 'json')) {
                $resolved = [
                    ...$resolved,
                    ...$this->extractImages((array) $fileResponse->json()),
                ];

                continue;
            }

            $resolved[] = [
                'data_url' => $this->dataUrl(base64_encode($fileResponse->body()), str_starts_with($fileContentType, 'image/') ? $fileContentType : 'image/png'),
                'revised_prompt' => '',
            ];
        }

        return collect($resolved)
            ->unique(fn (array $image): string => $image['url'] ?? $image['data_url'] ?? '')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function extractImageFileIds(array $payload): array
    {
        $ids = [];
        $items = [$payload];
        $scanned = 0;

        while ($items !== [] && $scanned < 500) {
            $item = array_shift($items);
            $scanned++;

            if (! is_array($item)) {
                continue;
            }

            foreach (['data', 'images', 'artifacts', 'output', 'choices', 'content', 'message', 'delta', 'result', 'results', 'image', 'output_image', 'image_file'] as $key) {
                if (isset($item[$key]) && is_array($item[$key])) {
                    $items = [
                        ...$items,
                        ...(array_is_list($item[$key]) ? array_values($item[$key]) : [$item[$key]]),
                    ];
                }
            }

            foreach (['file_id', 'fileId', 'image_file_id', 'imageFileId', 'id'] as $key) {
                $value = $item[$key] ?? null;

                if (! is_string($value) || trim($value) === '') {
                    continue;
                }

                $type = Str::lower((string) ($item['type'] ?? $item['object'] ?? ''));

                if ($key !== 'id' || str_contains($type, 'image') || str_contains($type, 'file')) {
                    $ids[] = trim($value);
                }
            }
        }

        return collect($ids)->unique()->values()->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function providerPayloadPreview(array $payload): array
    {
        $preview = [];

        foreach (['id', 'object', 'model', 'message', 'detail', 'msg', 'error', 'data', 'output', 'choices', 'result', 'results'] as $key) {
            if (array_key_exists($key, $payload)) {
                $preview[$key] = $payload[$key];
            }
        }

        return json_decode(json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '[]', true) ?: [];
    }

    /**
     * @param  array<int, array<string, string>>  $images
     */
    private function appendImageString(array &$images, string $value, string $revisedPrompt = ''): void
    {
        $value = trim($value);

        if ($value === '') {
            return;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $images = [...$images, ...$this->extractImages($decoded)];

            return;
        }

        foreach ($this->imageSourcesFromText($value) as $source) {
            $sourceKey = str_starts_with($source, 'data:image/') ? 'data_url' : 'url';
            $images[] = [
                $sourceKey => $source,
                'revised_prompt' => $revisedPrompt,
            ];
        }

        if (str_starts_with($value, 'data:image/')) {
            $images[] = [
                'data_url' => $value,
                'revised_prompt' => $revisedPrompt,
            ];

            return;
        }

        if ($this->isSafeImageSource($value)) {
            $images[] = [
                'url' => $value,
                'revised_prompt' => $revisedPrompt,
            ];
        }
    }

    /**
     * @return array<int, string>
     */
    private function imageSourcesFromText(string $value): array
    {
        $sources = [];

        preg_match_all('/data:image\/[a-zA-Z0-9.+-]+;base64,[A-Za-z0-9+\/=\s]+/', $value, $dataUrlMatches);
        $sources = [...$sources, ...($dataUrlMatches[0] ?? [])];

        preg_match_all('/!\[[^\]]*]\(([^)\s]+)(?:\s+"[^"]*")?\)/', $value, $markdownMatches);
        $sources = [...$sources, ...($markdownMatches[1] ?? [])];

        preg_match_all('/https?:\/\/[^\s<>"\']+/i', $value, $urlMatches);
        $sources = [...$sources, ...($urlMatches[0] ?? [])];

        return collect($sources)
            ->map(fn (string $source): string => trim($source, " \t\n\r\0\x0B'\"),.;]}>"))
            ->filter(fn (string $source): bool => $this->isSafeImageSource($source))
            ->unique()
            ->values()
            ->all();
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
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>|UploadedFile|null  $files
     * @return array<string, mixed>
     */
    private function chatPayload(array $data, array|UploadedFile|null $files): array
    {
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }

        $content = [[
            'type' => 'text',
            'text' => trim((string) $data['prompt']),
        ]];

        foreach (array_values($files ?? []) as $index => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            if (str_starts_with((string) $file->getMimeType(), 'image/')) {
                $content[] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => $this->dataUrl(
                            base64_encode(file_get_contents($file->getRealPath()) ?: ''),
                            $file->getMimeType() ?: 'image/png',
                        ),
                    ],
                ];

                continue;
            }

            $content[] = [
                'type' => 'text',
                'text' => '附件 '.($index + 1).'：'.$file->getClientOriginalName().'（'.$file->getMimeType().'，'.$file->getSize().' bytes）',
            ];
        }

        $payload = [
            'model' => trim((string) $data['model']),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ],
            'metadata' => [
                'reasoning_mode' => (string) ($data['reasoning_mode'] ?? 'low'),
                'web_search' => (bool) ($data['web_search'] ?? false),
            ],
        ];

        if ((bool) ($data['web_search'] ?? false)) {
            $payload['tools'] = [
                ['type' => 'web_search_preview'],
            ];
        }

        return $payload;
    }

    private function extractChatText(array $payload): string
    {
        $message = data_get($payload, 'choices.0.message.content')
            ?? data_get($payload, 'output.0.content.0.text')
            ?? data_get($payload, 'output_text')
            ?? data_get($payload, 'message')
            ?? data_get($payload, 'text');

        if (is_array($message)) {
            $message = collect($message)
                ->map(fn (mixed $item): string => is_array($item) ? (string) ($item['text'] ?? '') : (string) $item)
                ->filter()
                ->implode("\n");
        }

        return trim(is_string($message) ? $message : '');
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
            'fallback_model' => (string) ($config['fallback_model'] ?? ''),
            'image_api_mode' => (string) ($config['image_api_mode'] ?? $data['image_api_mode'] ?? 'auto'),
        ];
    }

    /**
     * @param  array<int, array{name: string, content: string}>  $references
     * @param  array{name: string, content: string}|null  $mask
     * @param  array<string, string>  $headers
     * @param  callable(string, array<string, mixed>): bool  $emit
     * @return array<int, array<string, string>>
     */
    private function streamImages(string $baseUrl, array $payload, array $references, ?array $mask, int $timeout, array $headers, callable $emit, string $mode = 'images'): array
    {
        if (! function_exists('curl_init')) {
            throw new RuntimeException('当前 PHP 未启用 curl 扩展，无法使用流式传输。');
        }

        $path = match ($mode) {
            'responses' => '/responses',
            'images_root' => '/images',
            default => $references === [] ? '/images/generations' : '/images/edits',
        };

        $curl = curl_init($baseUrl.$path);

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
        $rawResponse = '';
        $images = [];
        $startedAt = microtime(true);
        $timedOut = false;

        try {
            curl_setopt_array($curl, [
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => $requestHeaders,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => min(30, max(5, $timeout)),
                CURLOPT_WRITEFUNCTION => function ($curl, string $chunk) use (&$buffer, &$rawResponse, &$images, $emit, $startedAt, $timeout, &$timedOut): int {
                    if ((microtime(true) - $startedAt) >= $timeout) {
                        $timedOut = true;

                        return 0;
                    }

                    $rawResponse .= $chunk;
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
            ] + $this->aiCurlOptions());

            curl_exec($curl);

            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $error = curl_error($curl);

            if ($timedOut) {
                throw new RuntimeException('请求超过 '.$timeout.' 秒未完成，已自动取消。');
            }

            if ($buffer !== '') {
                $this->handleStreamEvent($buffer, $images, $emit);
            }

            if ($error !== '') {
                throw new RuntimeException('图片流式生成失败：'.$error);
            }

            if ($status >= 400) {
                $payload = json_decode($rawResponse, true);
                throw new RuntimeException($this->providerErrorMessage(
                    is_array($payload) ? $payload : null,
                    $status,
                    '图片流式生成失败。',
                ));
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
        $message = $this->providerErrorMessage($payload, $status, $fallback);

        return response()->json([
            'message' => $message,
        ], $status >= 400 && $status < 600 ? $status : 502);
    }

    private function providerErrorMessage(?array $payload, int $status, string $fallback): string
    {
        $candidates = [
            data_get($payload, 'error.message'),
            data_get($payload, 'error.detail'),
            data_get($payload, 'error.type'),
            data_get($payload, 'message'),
            data_get($payload, 'detail'),
            data_get($payload, 'msg'),
        ];

        $fieldErrors = collect(data_get($payload, 'errors', []))
            ->map(function (mixed $value, mixed $key): string {
                if (is_array($value)) {
                    $value = implode('；', array_map('strval', $value));
                }

                return is_string($key) ? $key.'：'.$value : (string) $value;
            })
            ->filter()
            ->implode('；');

        if ($fieldErrors !== '') {
            $candidates[] = $fieldErrors;
        }

        $detail = collect($candidates)
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => trim($value))
            ->unique()
            ->implode('；');

        $friendlyStatus = match ($status) {
            400 => '请求参数有误，请检查模型、尺寸、格式、参考图或透明背景等参数。',
            401 => '认证失败，请检查 API Key 是否正确或是否有权限。',
            402 => '服务商账户余额不足或需要开通计费。',
            403 => '服务商拒绝请求，可能是该分组没有启用图片/聊天功能、模型无权限或 Key 权限不足。',
            404 => '接口或模型不存在，请检查 API URL 和模型名。',
            408 => '服务商请求超时，可以提高超时时间或降低生成数量。',
            409 => '服务商返回任务冲突，请稍后重试。',
            413 => '上传内容过大，请压缩参考图或减少附件。',
            415 => '服务商不支持当前文件或返回格式。',
            422 => '服务商参数校验失败，请检查尺寸、数量、质量、格式或参考图参数。',
            429 => '请求过于频繁或额度耗尽，请稍后重试。',
            500 => '服务商内部错误，请稍后重试。',
            502, 503, 504 => '服务商暂不可用、网关超时或该分组未启用对应功能。',
            default => '',
        };

        if ($detail !== '') {
            return $friendlyStatus !== ''
                ? $friendlyStatus.' 服务商返回：'.$detail
                : $detail;
        }

        return $friendlyStatus !== '' ? $friendlyStatus : $fallback.' HTTP '.$status;
    }
}
