<?php

use App\Models\AiUsageLog;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

it('renders the storefront ai image page and entry links', function (): void {
    SiteSetting::query()->create([
        'site_name' => '自定义生图站',
    ]);

    $this->get(route('ai-image.index'))
        ->assertOk()
        ->assertSee('AI')
        ->assertSee('自定义生图站')
        ->assertDontSee('天域工坊')
        ->assertSee('生成设置')
        ->assertSee('Chat')
        ->assertSee('当前配置')
        ->assertSee('配置名称')
        ->assertSee('默认配置')
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
        ->assertSee('chatTitleFromMessage', false)
        ->assertSee('推理模式')
        ->assertSee('超高')
        ->assertSee('向 AI 发送消息', false)
        ->assertSee('lg:grid-cols-3', false)
        ->assertSee('data-edit-output', false)
        ->assertSee('data-detail-error', false)
        ->assertSee('PNG');

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
        'ai_default_endpoint' => 'https://backend.example.test/v1',
        'ai_default_api_key' => 'backend-key',
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
        && $request->hasHeader('Authorization', 'Bearer backend-key'));

    $this->assertDatabaseHas('ai_usage_logs', [
        'user_id' => $user->id,
        'model' => 'gpt-image-1',
        'token_count' => 1234,
        'config_name' => '后台默认',
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
        ->assertSee('默认总 Key')
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
    Http::fake([
        'https://api.example.test/v1/models' => Http::response([
            'data' => [
                ['id' => 'gpt-4.1-mini'],
                ['id' => 'gpt-image-1'],
                ['id' => 'flux-kontext'],
            ],
        ]),
    ]);

    $this->postJson(route('ai-image.models'), [
        'endpoint' => 'https://api.example.test/v1',
        'api_key' => 'test-key',
    ])
        ->assertOk()
        ->assertJsonPath('models.0.id', 'gpt-image-1')
        ->assertJsonPath('models.1.id', 'flux-kontext');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.example.test/v1/models'
        && $request->hasHeader('Authorization', 'Bearer test-key'));
});

it('generates images with prompt dimensions and references', function (): void {
    Http::fake([
        'https://api.example.test/v1/images/edits' => Http::response([
            'data' => [
                ['b64_json' => base64_encode('fake-image')],
                ['url' => 'https://cdn.example.test/generated.png'],
            ],
        ]),
    ]);

    $this->postJson(route('ai-image.generate'), [
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
    Http::fake([
        'https://api.example.test/v1/images/generations' => Http::response([
            'data' => [
                ['url' => 'https://cdn.example.test/auto.png'],
            ],
        ]),
    ]);

    $this->postJson(route('ai-image.generate'), [
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
    $this->postJson(route('ai-image.models'), [
        'endpoint' => 'http://127.0.0.1:11434/v1',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('endpoint');
});
