<?php

use App\Models\Category;
use App\Models\NavigationMenuItem;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SiteSetting;
use App\Models\User;
use App\Filament\Pages\LoadingPageSettingsPage;
use App\Http\Middleware\ShowFirstVisitLoadingPage;
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
it('shows a lightweight loading page for first time storefront visitors only', function (): void {
    config(['shop.first_visit_loading.enabled' => true]);

    $response = $this->get(route('products.index', ['category' => 'demo']));

    $response
        ->assertRedirect(route('loading.show', ['to' => route('products.index', ['category' => 'demo'])]))
        ->assertCookie(ShowFirstVisitLoadingPage::COOKIE, '1')
        ->assertCookie(ShowFirstVisitLoadingPage::RECENT_COOKIE, '1');

    $this->get(route('loading.show', ['to' => route('products.index')]))
        ->assertOk()
        ->assertSee('正在为你准备页面')
        ->assertSee('loading\/prepare', false)
        ->assertSee('progress-soft-gradient', false)
        ->assertSee('data-loading-percent', false)
        ->assertSee('data-loading-skip hidden', false)
        ->assertSee('/products', false);

    $this->get(route('loading.prepare'))
        ->assertOk()
        ->assertJson(['ok' => true])
        ->assertCookie(ShowFirstVisitLoadingPage::COOKIE, '1');

    $this->withCookie(ShowFirstVisitLoadingPage::COOKIE, '1')
        ->withCookie(ShowFirstVisitLoadingPage::RECENT_COOKIE, '1')
        ->get(route('products.index'))
        ->assertOk();
});

it('renders admin loading page settings and uses saved lightweight page config', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(LoadingPageSettingsPage::getUrl())
        ->assertOk()
        ->assertSee('加载等待页')
        ->assertSee('页面预览')
        ->assertSee('.dark .shop-loading-preview', false)
        ->assertSee('🍥', false);

    SiteSetting::query()->firstOrCreate([], ['site_name' => 'ShopWeb'])->update([
        'loading_page_config' => [
            'title' => '马上就好',
            'subtitle' => '正在轻量加载站点配置。',
            'status_text' => '加载中...',
            'done_text' => '完成',
            'skip_text' => '跳过',
            'symbol' => 'ring',
            'progress_style' => 'minimal',
            'layout_columns' => 6,
            'components' => [
                ['type' => 'symbol', 'label' => '轻量加载', 'x' => 1, 'y' => 1, 'w' => 6, 'h' => 1, 'align' => 'center'],
                ['type' => 'title', 'x' => 1, 'y' => 2, 'w' => 6, 'h' => 1, 'align' => 'center'],
                ['type' => 'subtitle', 'x' => 1, 'y' => 3, 'w' => 6, 'h' => 1, 'align' => 'center'],
                ['type' => 'progress', 'x' => 1, 'y' => 4, 'w' => 6, 'h' => 1, 'align' => 'stretch'],
            ],
        ],
    ]);

    $this->get(route('loading.show', ['to' => route('home')]))
        ->assertOk()
        ->assertSee('马上就好')
        ->assertSee('class="ring"', false)
        ->assertSee('progress-minimal', false)
        ->assertSee('正在轻量加载站点配置。');
});
