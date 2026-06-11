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
