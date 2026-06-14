<?php

use App\Models\AiUsageLog;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

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

it('retries image generation with a listed model when the default model has no channel', function (): void {
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
