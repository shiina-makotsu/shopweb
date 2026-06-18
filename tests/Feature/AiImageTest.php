<?php

use App\Models\AiChatSession;
use App\Models\AiImageTask;
use App\Models\AiUsageLog;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('renders the storefront ai image page and entry links', function (): void {
    $user = User::factory()->create([
        'role' => 'customer',
    ]);

    SiteSetting::query()->create([
        'site_name' => '自定义生图站',
    ]);

    $this->get(route('ai-image.index'))
        ->assertRedirect(route('login', absolute: false));

    $this->actingAs($user)
        ->get(route('ai-image.index'))
        ->assertOk()
        ->assertSee('AI')
        ->assertSee('自定义生图站')
        ->assertDontSee('天域工坊')
        ->assertSee('生成设置')
        ->assertSee('Chat')
        ->assertSee('当前配置')
        ->assertSee('配置名称')
        ->assertSee('默认配置')
        ->assertSee('data-endpoint-field', false)
        ->assertSee('data-key-field', false)
        ->assertSee('data-chat-url', false)
        ->assertSee('shopweb.ai-image.tasks.v1', false)
        ->assertSee('获取模型')
        ->assertSee('是否透明')
        ->assertSee('生图接口')
        ->assertSee('Images API (/images)')
        ->assertSee('Responses API')
        ->assertSee('流式传输')
        ->assertSee('partial_images')
        ->assertSee('参考图编辑')
        ->assertSee('添加遮罩')
        ->assertSee('图像任务详情')
        ->assertSee('data-clear-prompt', false)
        ->assertSee('data-chat-view', false)
        ->assertSee('data-chat-new', false)
        ->assertSee('data-chat-delete', false)
        ->assertSee('lg:sticky', false)
        ->assertSee('data-chat-files-input', false)
        ->assertSee('data-chat-model-select', false)
        ->assertSee('data-chat-web-search', false)
        ->assertSee('aria-pressed="false"', false)
        ->assertSee('gpt-image-2')
        ->assertSee('gpt-5.5')
        ->assertSee('chatTitleFromMessage', false)
        ->assertSee('推理模式')
        ->assertSee('超高')
        ->assertSee('向 AI 发送消息', false)
        ->assertSee('lg:grid-cols-3', false)
        ->assertSee('data-edit-output', false)
        ->assertSee('data-detail-error', false)
        ->assertSee('PNG')
        ->assertSee('submitWorkbench', false)
        ->assertSee('generationCount', false)
        ->assertDontSee('form.count', false)
        ->assertDontSee('form.stream', false)
        ->assertDontSee('form.size_mode', false);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('ai-image.index', absolute: false), false);
});

it('uses backend default ai config for signed in users and records usage', function (): void {
    $user = User::factory()->create([
        'role' => 'customer',
    ]);

    SiteSetting::query()->create([
        'site_name' => 'ShopWeb',
        'ai_default_image_endpoint' => 'https://backend.example.test/v1',
        'ai_default_image_api_key' => 'backend-image-key',
        'ai_default_chat_endpoint' => 'https://chat.example.test/v1',
        'ai_default_chat_api_key' => 'backend-chat-key',
        'ai_default_user_quota_k' => 20,
    ]);

    Http::fake([
        'https://backend.example.test/v1/images/generations' => Http::response([
            'usage' => [
                'total_tokens' => 1234,
                'prompt_tokens' => 200,
                'completion_tokens' => 1034,
            ],
            'data' => [
                ['url' => 'https://cdn.example.test/default.png'],
            ],
        ]),
    ]);

    $this->actingAs($user)
        ->postJson(route('ai-image.generate'), [
            'config_mode' => 'default',
            'config_name' => '后台默认',
            'model' => 'gpt-image-1',
            'prompt' => '默认配置生成',
            'count' => 1,
            'size_mode' => 'auto',
            'quality' => 'auto',
            'output_format' => 'png',
            'timeout_seconds' => 600,
        ])
        ->assertOk()
        ->assertJsonPath('images.0.url', 'https://cdn.example.test/default.png')
        ->assertJsonPath('meta.config_name', '后台默认')
        ->assertJsonPath('meta.provider_source', 'site_default');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://backend.example.test/v1/images/generations'
        && $request->hasHeader('Authorization', 'Bearer backend-image-key'));

    $this->assertDatabaseHas('ai_usage_logs', [
        'user_id' => $user->id,
        'model' => 'gpt-image-1',
        'token_count' => 1234,
        'config_name' => '后台默认',
    ]);
});

it('lets backoffice users call managed ai beyond quota while still recording usage', function (): void {
    $admin = User::factory()->create([
        'role' => 'admin',
        'ai_quota_k' => 1,
    ]);

    AiUsageLog::query()->create([
        'user_id' => $admin->id,
        'feature' => 'image',
        'config_name' => '历史记录',
        'provider_source' => 'site_default',
        'model' => 'gpt-image-1',
        'token_count' => 5000,
        'status' => 'success',
    ]);

    SiteSetting::query()->create([
        'site_name' => 'ShopWeb',
        'ai_default_image_endpoint' => 'https://backend.example.test/v1',
        'ai_default_image_api_key' => 'backend-image-key',
        'ai_default_user_quota_k' => 1,
    ]);

    Http::fake([
        'https://backend.example.test/v1/images/generations' => Http::response([
            'usage' => [
                'total_tokens' => 2222,
                'prompt_tokens' => 222,
                'completion_tokens' => 2000,
            ],
            'data' => [
                ['url' => 'https://cdn.example.test/admin.png'],
            ],
        ]),
    ]);

    $this->actingAs($admin)
        ->postJson(route('ai-image.generate'), [
            'config_mode' => 'default',
            'model' => 'gpt-image-1',
            'prompt' => '后台用户不限额生成',
            'count' => 1,
            'size_mode' => 'auto',
            'quality' => 'auto',
            'output_format' => 'png',
            'timeout_seconds' => 600,
        ])
        ->assertOk()
        ->assertJsonPath('images.0.url', 'https://cdn.example.test/admin.png');

    $this->assertDatabaseHas('ai_usage_logs', [
        'user_id' => $admin->id,
        'model' => 'gpt-image-1',
        'token_count' => 2222,
        'provider_source' => 'site_default',
    ]);

    expect(AiUsageLog::query()->sum('token_count'))->toBe(7222);
});

it('records backoffice custom ai calls without applying storefront quota', function (): void {
    $support = User::factory()->create([
        'role' => 'support',
        'ai_quota_k' => 0,
    ]);

    Http::fake([
        'https://custom.example.test/v1/chat/completions' => Http::response([
            'usage' => [
                'total_tokens' => 88,
                'prompt_tokens' => 30,
                'completion_tokens' => 58,
            ],
            'choices' => [
                ['message' => ['content' => '后台自定义 key 回复']],
            ],
        ]),
    ]);

    $this->actingAs($support)
        ->postJson(route('ai-image.chat'), [
            'config_mode' => 'custom',
            'config_name' => 'support custom',
            'endpoint' => 'https://custom.example.test/v1',
            'api_key' => 'custom-key',
            'model' => 'gpt-4.1-mini',
            'prompt' => '你好',
            'reasoning_mode' => 'low',
            'timeout_seconds' => 600,
        ])
        ->assertOk()
        ->assertJsonPath('message', '后台自定义 key 回复')
        ->assertJsonPath('meta.provider_source', 'backoffice_custom');

    $this->assertDatabaseHas('ai_usage_logs', [
        'user_id' => $support->id,
        'feature' => 'chat',
        'model' => 'gpt-4.1-mini',
        'token_count' => 88,
        'provider_source' => 'backoffice_custom',
        'config_name' => 'support custom',
    ]);
});

it('renders ai quota dashboards for storefront users and admins', function (): void {
    $user = User::factory()->create([
        'role' => 'customer',
        'name' => '普通用户',
        'ai_quota_k' => 10,
    ]);
    $member = User::factory()->create([
        'role' => 'customer',
        'name' => 'member_user',
        'account_type' => 'member',
    ]);
    $moderator = User::factory()->create([
        'role' => 'customer',
        'name' => 'moderator_user',
        'forum_role' => 'moderator',
    ]);

    AiUsageLog::query()->create([
        'user_id' => $user->id,
        'feature' => 'image',
        'config_name' => '默认配置',
        'provider_source' => 'site_default',
        'model' => 'gpt-image-1',
        'token_count' => 1536,
        'prompt_tokens' => 256,
        'completion_tokens' => 1280,
        'request_ms' => 1800,
        'status' => 'success',
    ]);
    AiUsageLog::query()->create([
        'user_id' => $user->id,
        'feature' => 'image',
        'config_name' => '默认配置',
        'provider_source' => 'site_default',
        'model' => 'flux-kontext',
        'token_count' => 1000000,
        'prompt_tokens' => 400000,
        'completion_tokens' => 600000,
        'request_ms' => 2500,
        'status' => 'success',
    ]);

    $this->actingAs($user)
        ->get(route('user.section', 'ai'))
        ->assertOk()
        ->assertSee('AI 配额')
        ->assertSee('AI 余额')
        ->assertSee('gpt-image-1')
        ->assertSee('请求时间')
        ->assertSee('默认配置');

    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $this->actingAs($admin)
        ->get('/admin/user-ai')
        ->assertOk()
        ->assertSee('用户 AI')
        ->assertSee('默认总配置')
        ->assertSee('生图 API URL')
        ->assertSee('聊天 API URL')
        ->assertSee('用户生图 API URL')
        ->assertSee('用户聊天 API URL')
        ->assertSee('用户列表')
        ->assertSee('搜索用户')
        ->assertSee('用户类型')
        ->assertSee('会员用户')
        ->assertSee('版主')
        ->assertSee($member->name)
        ->assertSee($moderator->name)
        ->assertSee('当前用户数据')
        ->assertSee('gpt-image-1')
        ->assertSee('flux-kontext')
        ->assertSee('输入 token')
        ->assertSee('输出 token')
        ->assertSee('400k')
        ->assertSee('600k')
        ->assertSee('1m')
        ->assertSee('总量', false)
        ->assertSee('使用记录');
});

it('fetches image models through the server proxy', function (): void {
    $user = User::factory()->create(['role' => 'customer']);

    Http::fake([
        'https://api.example.test/v1/models' => Http::response([
            'data' => [
                ['id' => 'gpt-4.1-mini'],
                ['id' => 'gpt-image-1'],
                ['id' => 'flux-kontext'],
            ],
        ]),
    ]);

    $this->actingAs($user)->postJson(route('ai-image.models'), [
        'feature' => 'image',
        'endpoint' => 'https://api.example.test/v1',
        'api_key' => 'test-key',
    ])
        ->assertOk()
        ->assertJsonPath('models.0.id', 'gpt-image-1')
        ->assertJsonPath('models.1.id', 'flux-kontext');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.example.test/v1/models'
        && $request->hasHeader('Authorization', 'Bearer test-key'));
});

it('uses native ca verification for ai provider requests by default', function (): void {
    $controller = app(\App\Http\Controllers\AiImageController::class);
    $method = new ReflectionMethod($controller, 'aiHttpOptions');

    expect($method->invoke($controller))->toMatchArray([
        'curl' => [
            CURLOPT_SSL_OPTIONS => CURLSSLOPT_NATIVE_CA,
        ],
    ]);
});

it('uses separate backend keys for image and chat ai requests', function (): void {
    $user = User::factory()->create([
        'role' => 'customer',
    ]);

    SiteSetting::query()->create([
        'site_name' => 'ShopWeb',
        'ai_default_image_endpoint' => 'https://image.example.test/v1',
        'ai_default_image_api_key' => 'image-key',
        'ai_default_chat_endpoint' => 'https://chat.example.test/v1',
        'ai_default_chat_api_key' => 'chat-key',
        'ai_default_user_quota_k' => 20,
    ]);

    Http::fake([
        'https://chat.example.test/v1/chat/completions' => Http::response([
            'usage' => [
                'total_tokens' => 42,
                'prompt_tokens' => 12,
                'completion_tokens' => 30,
            ],
            'choices' => [
                ['message' => ['content' => '聊天回复']],
            ],
        ]),
    ]);

    $this->actingAs($user)
        ->postJson(route('ai-image.chat'), [
            'config_mode' => 'default',
            'config_name' => '默认聊天',
            'model' => 'gpt-4.1-mini',
            'prompt' => '你好',
            'reasoning_mode' => 'medium',
            'web_search' => true,
            'timeout_seconds' => 600,
        ])
        ->assertOk()
        ->assertJsonPath('message', '聊天回复')
        ->assertJsonPath('meta.provider_source', 'site_default')
        ->assertJsonPath('meta.web_search', true);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://chat.example.test/v1/chat/completions'
        && $request->hasHeader('Authorization', 'Bearer chat-key')
        && str_contains($request->body(), '"web_search":true')
        && str_contains($request->body(), '"web_search_preview"'));

    $this->assertDatabaseHas('ai_usage_logs', [
        'user_id' => $user->id,
        'feature' => 'chat',
        'model' => 'gpt-4.1-mini',
        'token_count' => 42,
    ]);
});

it('returns detailed provider errors for disabled image capability', function (): void {
    $user = User::factory()->create(['role' => 'customer']);

    Http::fake([
        'https://api.example.test/v1/images/generations' => Http::response([
            'error' => [
                'message' => '该分组没有启用图片功能',
                'type' => 'image_not_enabled',
            ],
        ], 503),
    ]);

    $this->actingAs($user)
        ->postJson(route('ai-image.generate'), [
            'endpoint' => 'https://api.example.test/v1',
            'api_key' => 'test-key',
            'model' => 'gpt-image-1',
            'prompt' => '测试错误',
            'count' => 1,
            'size_mode' => 'auto',
            'quality' => 'auto',
            'output_format' => 'png',
            'timeout_seconds' => 600,
        ])
        ->assertStatus(503)
        ->assertJson(fn ($json) => $json
            ->where('message', fn (string $message): bool => str_contains($message, '服务商暂不可用')
                && str_contains($message, '该分组没有启用图片功能'))
        );
});

it('can retry image generation with a listed model when forced to use the images endpoint', function (): void {
    $user = User::factory()->create(['role' => 'customer']);

    Http::fake([
        'https://api.example.test/v1/images/generations' => Http::sequence()
            ->push([
                'error' => [
                    'message' => 'No available channel for model gpt-image-2 under group 企业生图专线',
                    'type' => 'new_api_error',
                ],
            ], 503)
            ->push([
                'data' => [
                    ['url' => 'https://cdn.example.test/fallback.png'],
                ],
            ]),
        'https://api.example.test/v1/models' => Http::response([
            'data' => [
                ['id' => 'gpt-4.1-mini'],
                ['id' => 'gpt-image-1'],
            ],
        ]),
    ]);

    $this->actingAs($user)->postJson(route('ai-image.generate'), [
        'endpoint' => 'https://api.example.test/v1',
        'api_key' => 'test-key',
        'model' => 'gpt-image-2',
        'prompt' => '测试自动切换可用图片模型',
        'count' => 1,
        'size_mode' => 'auto',
        'quality' => 'auto',
        'output_format' => 'png',
        'timeout_seconds' => 600,
        'image_api_mode' => 'images',
    ])
        ->assertOk()
        ->assertJsonPath('images.0.url', 'https://cdn.example.test/fallback.png')
        ->assertJsonPath('meta.model', 'gpt-image-1')
        ->assertJsonPath('meta.fallback_model', 'gpt-image-1');

    Http::assertSentCount(3);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.example.test/v1/models');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.example.test/v1/images/generations'
        && str_contains($request->body(), '"model":"gpt-image-2"'));
    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.example.test/v1/images/generations'
        && str_contains($request->body(), '"model":"gpt-image-1"'));
});

it('falls back to the root images endpoint when image generations has no channel', function (): void {
    $user = User::factory()->create(['role' => 'customer']);

    Http::fake([
        'https://api.example.test/v1/images/generations' => Http::response([
            'error' => [
                'message' => 'No available channel for model gpt-image-2 under group 企业生图专线',
                'type' => 'new_api_error',
            ],
        ], 503),
        'https://api.example.test/v1/images' => Http::response([
            'data' => [
                ['url' => 'https://cdn.example.test/root-images.png'],
            ],
        ]),
    ]);

    $this->actingAs($user)->postJson(route('ai-image.generate'), [
        'endpoint' => 'https://api.example.test/v1/images',
        'api_key' => 'test-key',
        'model' => 'gpt-image-2',
        'prompt' => '使用根 images 接口生图',
        'count' => 1,
        'size_mode' => 'auto',
        'quality' => 'auto',
        'output_format' => 'png',
        'image_api_mode' => 'auto',
        'timeout_seconds' => 600,
    ])
        ->assertOk()
        ->assertJsonPath('images.0.url', 'https://cdn.example.test/root-images.png')
        ->assertJsonPath('meta.image_api_mode', 'images_root');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.example.test/v1/images/generations'
        && str_contains($request->body(), '"model":"gpt-image-2"'));
    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.example.test/v1/images'
        && str_contains($request->body(), '"prompt"'));
});

it('falls back to responses image generation when the images endpoint has no channel', function (): void {
    $user = User::factory()->create(['role' => 'customer']);

    Http::fake([
        'https://api.example.test/v1/images/generations' => Http::response([
            'error' => [
                'message' => 'No available channel for model gpt-image-1 under group 企业生图专线',
                'type' => 'new_api_error',
            ],
        ], 503),
        'https://api.example.test/v1/images' => Http::response([
            'error' => [
                'message' => 'No available channel for model gpt-image-1 under group 企业生图专线',
                'type' => 'new_api_error',
            ],
        ], 503),
        'https://api.example.test/v1/responses' => Http::response([
            'output' => [
                [
                    'type' => 'image_generation_call',
                    'result' => base64_encode('responses-image'),
                ],
            ],
        ]),
    ]);

    $this->actingAs($user)->postJson(route('ai-image.generate'), [
        'endpoint' => 'https://api.example.test/v1',
        'api_key' => 'test-key',
        'model' => 'gpt-image-1',
        'prompt' => '使用 responses 生图',
        'count' => 1,
        'size_mode' => 'auto',
        'quality' => 'auto',
        'output_format' => 'png',
        'image_api_mode' => 'auto',
        'timeout_seconds' => 600,
    ])
        ->assertOk()
        ->assertJsonPath('images.0.data_url', 'data:image/png;base64,'.base64_encode('responses-image'))
        ->assertJsonPath('meta.image_api_mode', 'responses');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.example.test/v1/images/generations');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.example.test/v1/images');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.example.test/v1/responses'
        && str_contains($request->body(), '"type":"image_generation"')
        && str_contains($request->body(), '"model":"gpt-5.5"')
        && str_contains($request->body(), '"input":"'));
});

it('sends references through responses image generation as multimodal input', function (): void {
    $user = User::factory()->create(['role' => 'customer']);

    Http::fake([
        'https://api.example.test/v1/responses' => Http::response([
            'output' => [
                [
                    'type' => 'image_generation_call',
                    'result' => base64_encode('responses-reference-image'),
                ],
            ],
        ]),
    ]);

    $this->actingAs($user)->postJson(route('ai-image.generate'), [
        'endpoint' => 'https://api.example.test/v1',
        'api_key' => 'test-key',
        'model' => 'gpt-image-2',
        'prompt' => '参考图生成',
        'count' => 1,
        'size_mode' => 'auto',
        'quality' => 'auto',
        'output_format' => 'png',
        'image_api_mode' => 'responses',
        'timeout_seconds' => 600,
        'reference_images' => [
            UploadedFile::fake()->image('reference.png', 64, 64),
        ],
    ])
        ->assertOk()
        ->assertJsonPath('meta.image_api_mode', 'responses');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.example.test/v1/responses'
        && str_contains($request->body(), '"model":"gpt-5.5"')
        && str_contains($request->body(), '"input_text"')
        && str_contains($request->body(), '"input_image"'));
});

it('tries alternate image modes when a successful provider response contains no image data', function (): void {
    $user = User::factory()->create(['role' => 'customer']);

    Http::fake([
        'https://api.example.test/v1/images/generations' => Http::response([
            'id' => 'task-without-image',
            'status' => 'completed',
            'message' => 'ok',
        ]),
        'https://api.example.test/v1/images' => Http::response([
            'id' => 'root-task-without-image',
            'status' => 'completed',
        ]),
        'https://api.example.test/v1/responses' => Http::response([
            'output' => [
                [
                    'type' => 'image_generation_call',
                    'result' => base64_encode('fallback-response-image'),
                ],
            ],
        ]),
    ]);

    $this->actingAs($user)->postJson(route('ai-image.generate'), [
        'endpoint' => 'https://api.example.test/v1',
        'api_key' => 'test-key',
        'chat_endpoint' => 'https://api.example.test/v1',
        'chat_api_key' => 'test-key',
        'model' => 'gpt-image-1',
        'prompt' => 'fallback when provider returns no image',
        'count' => 1,
        'size_mode' => 'auto',
        'quality' => 'auto',
        'output_format' => 'png',
        'image_api_mode' => 'auto',
        'timeout_seconds' => 600,
    ])
        ->assertOk()
        ->assertJsonPath('images.0.data_url', 'data:image/png;base64,'.base64_encode('fallback-response-image'))
        ->assertJsonPath('meta.image_api_mode', 'responses');
});

it('downloads generated image content when provider returns a file id', function (): void {
    $user = User::factory()->create(['role' => 'customer']);

    Http::fake([
        'https://api.example.test/v1/images/generations' => Http::response([
            'output' => [
                [
                    'type' => 'image_generation_call',
                    'file_id' => 'file-image-123',
                ],
            ],
        ]),
        'https://api.example.test/v1/files/file-image-123/content' => Http::response('file-image-bytes', 200, [
            'Content-Type' => 'image/png',
        ]),
    ]);

    $this->actingAs($user)->postJson(route('ai-image.generate'), [
        'endpoint' => 'https://api.example.test/v1',
        'api_key' => 'test-key',
        'model' => 'gpt-image-1',
        'prompt' => 'provider returns file id',
        'count' => 1,
        'size_mode' => 'auto',
        'quality' => 'auto',
        'output_format' => 'png',
        'timeout_seconds' => 600,
    ])
        ->assertOk()
        ->assertJsonPath('images.0.data_url', 'data:image/png;base64,'.base64_encode('file-image-bytes'));

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.example.test/v1/files/file-image-123/content');
});

it('generates images with prompt dimensions and references', function (): void {
    $user = User::factory()->create(['role' => 'customer']);

    Http::fake([
        'https://api.example.test/v1/images/edits' => Http::response([
            'data' => [
                ['b64_json' => base64_encode('fake-image')],
                ['url' => 'https://cdn.example.test/generated.png'],
            ],
        ]),
    ]);

    $this->actingAs($user)->postJson(route('ai-image.generate'), [
        'endpoint' => 'https://api.example.test/v1',
        'api_key' => 'test-key',
        'model' => 'gpt-image-1',
        'prompt' => '一张商品海报',
        'negative_prompt' => '低清晰度',
        'count' => 2,
        'size_mode' => 'custom',
        'width' => 832,
        'height' => 1216,
        'quality' => 'high',
        'style' => 'vivid',
        'background' => 'opaque',
        'output_format' => 'png',
        'timeout_seconds' => 600,
        'reference_images' => [
            UploadedFile::fake()->image('reference.png', 64, 64),
        ],
        'mask_image' => UploadedFile::fake()->image('mask.png', 64, 64),
    ])
        ->assertOk()
        ->assertJsonCount(2, 'images')
        ->assertJsonPath('images.0.data_url', 'data:image/png;base64,'.base64_encode('fake-image'))
        ->assertJsonPath('images.1.url', 'https://cdn.example.test/generated.png')
        ->assertJsonPath('meta.requested_size', '832x1216')
        ->assertJsonPath('meta.format', 'png');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.example.test/v1/images/edits'
        && str_contains($request->body(), '一张商品海报')
        && str_contains($request->body(), 'name="mask"')
        && str_contains($request->body(), '832x1216'));
});

it('generates auto sized transparent png images without sending a size', function (): void {
    $user = User::factory()->create(['role' => 'customer']);

    Http::fake([
        'https://api.example.test/v1/images/generations' => Http::response([
            'data' => [
                ['url' => 'https://cdn.example.test/auto.png'],
            ],
        ]),
    ]);

    $this->actingAs($user)->postJson(route('ai-image.generate'), [
        'endpoint' => 'https://api.example.test/v1',
        'api_key' => 'test-key',
        'model' => 'gpt-image-1',
        'prompt' => '一张透明背景商品图',
        'count' => 1,
        'size_mode' => 'auto',
        'quality' => 'auto',
        'transparent' => true,
        'output_format' => 'png',
        'timeout_seconds' => 600,
    ])
        ->assertOk()
        ->assertJsonPath('images.0.url', 'https://cdn.example.test/auto.png')
        ->assertJsonPath('meta.requested_size', null)
        ->assertJsonPath('meta.transparent', true);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.example.test/v1/images/generations'
        && str_contains($request->body(), '"output_format":"png"')
        && str_contains($request->body(), '"background":"transparent"')
        && ! str_contains($request->body(), '"size"'));
});

it('extracts provider image urls from nested chat style payloads', function (): void {
    $user = User::factory()->create(['role' => 'customer']);

    Http::fake([
        'https://api.example.test/v1/images/generations' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => '生成完成：![image](https://cdn.example.test/aijuhe-output.png)',
                    ],
                ],
            ],
        ]),
    ]);

    $this->actingAs($user)->postJson(route('ai-image.generate'), [
        'endpoint' => 'https://api.example.test/v1',
        'api_key' => 'test-key',
        'model' => 'gpt-image-1',
        'prompt' => '兼容嵌套返回图片',
        'count' => 1,
        'size_mode' => 'auto',
        'quality' => 'auto',
        'output_format' => 'png',
        'timeout_seconds' => 600,
    ])
        ->assertOk()
        ->assertJsonPath('images.0.url', 'https://cdn.example.test/aijuhe-output.png');
});

it('extracts provider image urls from result fields', function (): void {
    $user = User::factory()->create(['role' => 'customer']);

    Http::fake([
        'https://api.example.test/v1/images/generations' => Http::response([
            'data' => [
                ['result' => 'https://cdn.example.test/result-field.png'],
            ],
        ]),
    ]);

    $this->actingAs($user)->postJson(route('ai-image.generate'), [
        'endpoint' => 'https://api.example.test/v1',
        'api_key' => 'test-key',
        'model' => 'gpt-image-1',
        'prompt' => '兼容 result 链接',
        'count' => 1,
        'size_mode' => 'auto',
        'quality' => 'auto',
        'output_format' => 'png',
        'timeout_seconds' => 600,
    ])
        ->assertOk()
        ->assertJsonPath('images.0.url', 'https://cdn.example.test/result-field.png');
});

it('registers the streaming image route used by the workbench', function (): void {
    expect(route('ai-image.stream', absolute: false))->toBe('/ai-image/stream');
});

it('blocks localhost and private ai endpoints from the storefront proxy', function (): void {
    $user = User::factory()->create(['role' => 'customer']);

    $this->actingAs($user)->postJson(route('ai-image.models'), [
        'endpoint' => 'http://127.0.0.1:11434/v1',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('endpoint');
});

it('persists account ai image tasks and chat sessions with recycle bin restore', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $otherUser = User::factory()->create(['role' => 'customer']);

    $this->actingAs($user)
        ->postJson(route('ai-image.tasks.store'), [
            'id' => 'task-account-1',
            'status' => 'done',
            'stream' => true,
            'prompt' => 'account task',
            'submittedPrompt' => 'account submitted task',
            'references' => [
                ['name' => 'ref.png', 'url' => '/uploads/ai/references/ref.png'],
            ],
            'config' => ['model' => 'gpt-image-2'],
            'images' => [
                ['url' => 'https://cdn.example.test/image.png'],
            ],
            'partials' => [],
            'elapsedMs' => 2500,
        ])
        ->assertOk()
        ->assertJsonPath('task.id', 'task-account-1');

    $this->actingAs($user)
        ->postJson(route('ai-image.chats.store'), [
            'id' => 'chat-account-1',
            'title' => '首个话题',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'hello',
                    'model' => 'gpt-5.5',
                    'reasoning' => 'low',
                    'reasoningLabel' => '低',
                ],
                [
                    'role' => 'assistant',
                    'content' => 'hi',
                    'model' => 'gpt-5.5',
                    'reasoning' => 'low',
                    'reasoningLabel' => '低',
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('chat.id', 'chat-account-1')
        ->assertJsonCount(2, 'chat.messages');

    $this->actingAs($otherUser)
        ->getJson(route('ai-image.state'))
        ->assertOk()
        ->assertJsonCount(0, 'tasks')
        ->assertJsonCount(0, 'chats');

    $this->actingAs($user)
        ->deleteJson(route('ai-image.tasks.destroy', 'task-account-1'))
        ->assertOk();

    $this->actingAs($user)
        ->deleteJson(route('ai-image.chats.destroy', 'chat-account-1'))
        ->assertOk();

    $this->actingAs($user)
        ->getJson(route('ai-image.state'))
        ->assertOk()
        ->assertJsonCount(0, 'tasks')
        ->assertJsonCount(1, 'trashed_tasks')
        ->assertJsonPath('trashed_tasks.0.id', 'task-account-1')
        ->assertJsonCount(0, 'chats')
        ->assertJsonCount(1, 'trashed_chats')
        ->assertJsonPath('trashed_chats.0.id', 'chat-account-1');

    $this->actingAs($user)
        ->postJson(route('ai-image.tasks.restore', 'task-account-1'))
        ->assertOk()
        ->assertJsonPath('task.trashed', false);

    $this->actingAs($user)
        ->postJson(route('ai-image.chats.restore', 'chat-account-1'))
        ->assertOk()
        ->assertJsonPath('chat.trashed', false);

    $this->actingAs($user)
        ->getJson(route('ai-image.state'))
        ->assertOk()
        ->assertJsonPath('tasks.0.id', 'task-account-1')
        ->assertJsonPath('chats.0.id', 'chat-account-1')
        ->assertJsonCount(0, 'trashed_tasks')
        ->assertJsonCount(0, 'trashed_chats');
});

it('prunes expired ai recycle bin records by configured retention days', function (): void {
    $user = User::factory()->create(['role' => 'customer']);

    SiteSetting::query()->create([
        'site_name' => 'ShopWeb',
        'ai_trash_retention_days' => 2,
    ]);

    AiImageTask::query()->create([
        'user_id' => $user->id,
        'public_id' => 'task-expired',
        'status' => 'failed',
        'prompt' => 'expired',
        'deleted_at' => now()->subDays(3),
    ]);
    AiImageTask::query()->create([
        'user_id' => $user->id,
        'public_id' => 'task-fresh-trash',
        'status' => 'failed',
        'prompt' => 'fresh',
        'deleted_at' => now()->subDay(),
    ]);
    AiChatSession::query()->create([
        'user_id' => $user->id,
        'public_id' => 'chat-expired',
        'title' => 'expired',
        'deleted_at' => now()->subDays(3),
    ]);
    AiChatSession::query()->create([
        'user_id' => $user->id,
        'public_id' => 'chat-fresh-trash',
        'title' => 'fresh',
        'deleted_at' => now()->subDay(),
    ]);

    Artisan::call('shop:ai-trash-prune');

    $this->assertDatabaseMissing('ai_image_tasks', ['public_id' => 'task-expired']);
    $this->assertDatabaseHas('ai_image_tasks', ['public_id' => 'task-fresh-trash']);
    $this->assertDatabaseMissing('ai_chat_sessions', ['public_id' => 'chat-expired']);
    $this->assertDatabaseHas('ai_chat_sessions', ['public_id' => 'chat-fresh-trash']);
});

it('blocks managed ai calls before provider requests when shared token quota is insufficient', function (): void {
    $user = User::factory()->create([
        'role' => 'customer',
        'ai_quota_k' => 1,
    ]);

    SiteSetting::query()->create([
        'site_name' => 'ShopWeb',
        'ai_default_image_endpoint' => 'https://image.example.test/v1',
        'ai_default_image_api_key' => 'image-key',
        'ai_default_chat_endpoint' => 'https://chat.example.test/v1',
        'ai_default_chat_api_key' => 'chat-key',
    ]);

    AiUsageLog::query()->create([
        'user_id' => $user->id,
        'feature' => 'image',
        'config_name' => '默认配置',
        'provider_source' => 'site_default',
        'model' => 'gpt-image-2',
        'token_count' => 1000,
        'status' => 'success',
    ]);

    Http::fake();

    $this->actingAs($user)
        ->postJson(route('ai-image.generate'), [
            'config_mode' => 'default',
            'model' => 'gpt-image-2',
            'prompt' => 'quota image',
            'count' => 1,
            'size_mode' => 'auto',
            'quality' => 'auto',
            'output_format' => 'png',
            'timeout_seconds' => 600,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quota')
        ->assertJson(fn ($json) => $json->where('message', fn (string $message): bool => str_contains($message, 'AI 余额不足'))->etc());

    $this->actingAs($user)
        ->postJson(route('ai-image.chat'), [
            'config_mode' => 'default',
            'model' => 'gpt-5.5',
            'prompt' => 'quota chat',
            'reasoning_mode' => 'low',
            'timeout_seconds' => 600,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quota');

    Http::assertNothingSent();
});
