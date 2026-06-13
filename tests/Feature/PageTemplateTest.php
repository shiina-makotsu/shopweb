<?php

use App\Models\Category;
use App\Models\FriendLink;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\PageTemplate;

it('renders friend link and resource custom page templates', function (): void {
    FriendLink::query()->create([
        'site_name' => 'Partner Site',
        'url' => 'https://partner.example.test',
        'description' => 'A useful partner.',
        'is_active' => true,
    ]);

    $friendPage = Page::query()->create([
        'title' => '伙伴导航',
        'slug' => 'partners',
        'template' => PageTemplate::FRIEND_LINKS,
        'body' => '这里是友链说明。',
        'is_published' => true,
    ]);

    MediaAsset::query()->create([
        'name' => 'Guide PDF',
        'path' => 'resources/guide.pdf',
        'disk' => 'public_uploads',
        'mime_type' => 'application/pdf',
        'size' => 2048,
        'usage' => MediaAsset::USAGE_RESOURCE,
        'library' => MediaAsset::LIBRARY_SITE,
        'notes' => '下载说明文档。',
    ]);

    $resourcePage = Page::query()->create([
        'title' => '资源发布',
        'slug' => 'resources',
        'template' => PageTemplate::RESOURCES,
        'body' => '这里是资源说明。',
        'is_published' => true,
    ]);

    $this->get(route('pages.show', $friendPage))
        ->assertOk()
        ->assertSee('Partner Site')
        ->assertSee('这里是友链说明。');

    $this->get(route('pages.show', $resourcePage))
        ->assertOk()
        ->assertSee('Guide PDF')
        ->assertSee('打开资源');
});

it('renders search results inside a custom search page template', function (): void {
    $category = Category::query()->create(['name' => '搜索分类', 'slug' => 'search-category']);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => 'Alice Search Product',
        'slug' => 'alice-search-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);
    ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'SEARCH-1',
        'price_cents' => 1000,
        'stock' => 3,
        'is_active' => true,
    ]);
    User::factory()->create([
        'role' => 'customer',
        'name' => 'Alice User',
        'public_id' => 'alice_user',
    ]);

    $page = Page::query()->create([
        'title' => '综合搜索',
        'slug' => 'discover',
        'template' => PageTemplate::SEARCH,
        'body' => '搜索提示。',
        'is_published' => true,
    ]);

    $this->get(route('pages.show', [$page, 'q' => 'Alice']))
        ->assertOk()
        ->assertSee('Alice Search Product')
        ->assertSee('Alice User')
        ->assertSee('搜索提示。');
});

it('renders the about custom page template with editable placeholders', function (): void {
    expect(PageTemplate::options())->toHaveKey(PageTemplate::ABOUT)
        ->and(PageTemplate::defaultSlug(PageTemplate::ABOUT))->toBe('about')
        ->and(PageTemplate::defaultTitle(PageTemplate::ABOUT))->toBe('关于我们')
        ->and(PageTemplate::defaultExcerpt(PageTemplate::ABOUT))->toBeNull()
        ->and(PageTemplate::defaultBody(PageTemplate::ABOUT))->toContain('在这里介绍你的网站定位')
        ->and(PageTemplate::defaultBody(PageTemplate::ABOUT))->not->toContain('枫叶、白桦、丛林');

    $page = Page::query()->create([
        'title' => PageTemplate::defaultTitle(PageTemplate::ABOUT),
        'slug' => PageTemplate::defaultSlug(PageTemplate::ABOUT),
        'template' => PageTemplate::ABOUT,
        'body' => PageTemplate::defaultBody(PageTemplate::ABOUT),
        'excerpt' => PageTemplate::defaultExcerpt(PageTemplate::ABOUT),
        'is_published' => true,
    ]);

    $this->get(route('pages.show', $page))
        ->assertOk()
        ->assertSee('关于我们')
        ->assertSee('在这里放一句与你的网站理念相关的诗词')
        ->assertSee('在这里介绍你的网站定位')
        ->assertSee('在这里介绍网站名称、品牌名称或域名的来源')
        ->assertDontSee('枫叶、白桦、丛林')
        ->assertSee('CC BY 4.0')
        ->assertSee('creativecommons.org/licenses/by/4.0/deed.zh-hans', false);
});

it('renders drag and drop page blocks after markdown content', function (): void {
    $page = Page::query()->create([
        'title' => '区块页面',
        'slug' => 'block-page',
        'template' => PageTemplate::DEFAULT,
        'body' => '正文开头',
        'blocks' => [
            [
                'type' => 'heading',
                'data' => ['text' => '自由贸易说明', 'level' => 'h2'],
            ],
            [
                'type' => 'quote',
                'data' => ['content' => '尊重边界，平等沟通。', 'author' => '枫桦林'],
            ],
            [
                'type' => 'button',
                'data' => ['label' => '回到首页', 'url' => '/', 'style' => 'secondary'],
            ],
            [
                'type' => 'columns',
                'data' => ['left' => '左栏内容', 'right' => '右栏内容'],
            ],
        ],
        'is_published' => true,
    ]);

    $this->get(route('pages.show', $page))
        ->assertOk()
        ->assertSee('正文开头')
        ->assertSee('自由贸易说明')
        ->assertSee('尊重边界，平等沟通。')
        ->assertSee('枫桦林')
        ->assertSee('回到首页')
        ->assertSee('左栏内容')
        ->assertSee('右栏内容');
});

it('uses a published 404 template page when no page with slug 404 exists', function (): void {
    Page::query()->create([
        'title' => '模板 404 页面',
        'slug' => 'not-found-template',
        'template' => PageTemplate::NOT_FOUND,
        'body' => '这里是模板 404 文案。',
        'is_published' => true,
    ]);

    $this->get('/missing-template-page')
        ->assertNotFound()
        ->assertSee('模板 404 页面')
        ->assertSee('这里是模板 404 文案。')
        ->assertSee('href="/"', false);
});
