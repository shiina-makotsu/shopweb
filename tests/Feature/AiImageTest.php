<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

it('renders the storefront ai image page and entry links', function (): void {
    $this->get(route('ai-image.index'))
        ->assertOk()
        ->assertSee('AI 生图')
        ->assertSee('API URL')
        ->assertSee('获取模型')
        ->assertSee('参考图')
        ->assertSee('生成图片');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('ai-image.index', absolute: false), false);
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
        'reference_images' => [
            UploadedFile::fake()->image('reference.png', 64, 64),
        ],
    ])
        ->assertOk()
        ->assertJsonCount(2, 'images')
        ->assertJsonPath('images.0.data_url', 'data:image/png;base64,'.base64_encode('fake-image'))
        ->assertJsonPath('images.1.url', 'https://cdn.example.test/generated.png');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.example.test/v1/images/edits'
        && str_contains($request->body(), '一张商品海报')
        && str_contains($request->body(), '832x1216'));
});

it('blocks localhost and private ai endpoints from the storefront proxy', function (): void {
    $this->postJson(route('ai-image.models'), [
        'endpoint' => 'http://127.0.0.1:11434/v1',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('endpoint');
});
