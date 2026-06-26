<?php

use App\Models\Category;
use App\Models\NavigationMenuItem;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\StorefrontCache;
use Illuminate\Support\Facades\Cache;

it('prewarms storefront cache used by public pages', function (): void {
    Cache::flush();

    $category = Category::query()->create([
        'name' => '缓存分类',
        'slug' => 'cached-category',
        'is_active' => true,
    ]);

    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => '缓存商品',
        'slug' => 'cached-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
        'is_featured' => true,
    ]);

    ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'CACHE-001',
        'price_cents' => 9900,
        'stock' => 5,
        'is_active' => true,
    ]);

    Page::query()->create([
        'title' => '缓存页面',
        'slug' => 'cached-page',
        'is_published' => true,
    ]);

    NavigationMenuItem::query()->create([
        'placement' => NavigationMenuItem::PLACEMENT_TOP_NAV,
        'label' => '缓存菜单',
        'route_name' => 'products.index',
        'is_active' => true,
    ]);

    $this->artisan('shop:cache-prewarm')
        ->expectsOutputToContain('Prewarmed')
        ->assertSuccessful();

    $cache = app(StorefrontCache::class);

    expect($cache->categories()->pluck('slug'))->toContain('cached-category')
        ->and($cache->pages()->pluck('slug'))->toContain('cached-page')
        ->and($cache->menuItems(NavigationMenuItem::PLACEMENT_TOP_NAV)->pluck('label'))->toContain('缓存菜单')
        ->and($cache->homeProducts('featured')->pluck('slug'))->toContain('cached-product');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('缓存商品');
});
