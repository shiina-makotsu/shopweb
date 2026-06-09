<?php

use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\FlashSale;
use App\Models\ProductTag;
use App\Models\ProductVariant;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\OrderService;

it('allows a customer to add a sku to cart and create an order', function (): void {
    $this->seed();

    $user = User::query()->where('role', 'customer')->first()
        ?? User::factory()->create(['role' => 'customer']);

    $variant = ProductVariant::query()->firstOrFail();

    $this->post(route('cart.items.store'), [
        'variant_id' => $variant->id,
        'quantity' => 2,
    ])->assertRedirect(route('cart.show'));

    $this->actingAs($user)->post(route('checkout.store'), [
        'contact_name' => '测试用户',
        'contact_phone' => '13800000000',
        'contact_email' => 'buyer@example.com',
        'customer_note' => '测试订单',
    ])->assertRedirect();

    $this->assertDatabaseHas('orders', [
        'user_id' => $user->id,
        'total_cents' => $variant->price_cents * 2,
    ]);

    expect($variant->fresh()->stock)->toBe($variant->stock);

    $order = \App\Models\Order::query()->where('user_id', $user->id)->firstOrFail();
    app(OrderService::class)->confirmPayment($order);

    expect($variant->fresh()->stock)->toBe($variant->stock - 2);
});

it('requires login before voting', function (): void {
    $this->seed();

    $product = Product::query()->firstOrFail();
    $product->update(['status' => Product::STATUS_CONCEPT]);

    $this->post(route('votes.intent', $product), ['intent' => 'want'])
        ->assertRedirect(route('login'));
});

it('renders product tag listing pages', function (): void {
    $this->seed();

    $product = Product::query()->firstOrFail();
    $tag = ProductTag::query()->firstOrFail();

    $this->get(route('tags.show', $tag))
        ->assertOk()
        ->assertSee($tag->name)
        ->assertSee($product->title);

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertSee(route('tags.show', $tag), false);
});

it('registers customers with a simple human verification challenge', function (): void {
    $this->withSession(['register_captcha_answer' => 7])
        ->post(route('register'), [
            'public_id' => 'new_user',
            'name' => 'new-user',
            'email' => 'new-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'captcha_answer' => 3,
        ])
        ->assertInvalid(['captcha_answer']);

    $this->withSession(['register_captcha_answer' => 7])
        ->post(route('register'), [
            'public_id' => 'new_user',
            'name' => 'new-user',
            'email' => 'new-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'captcha_answer' => 7,
        ])
        ->assertRedirect(route('home'));

    $this->assertDatabaseHas('users', [
        'public_id' => 'new_user',
        'email' => 'new-user@example.com',
        'role' => 'customer',
        'account_type' => 'regular',
    ]);
});

it('only allows voting for concept products', function (): void {
    $this->seed();

    $user = User::factory()->create(['role' => 'customer']);
    $product = Product::query()->firstOrFail();

    $this->actingAs($user)
        ->post(route('votes.intent', $product), ['intent' => 'want'])
        ->assertNotFound();

    $product->update(['status' => Product::STATUS_CONCEPT]);

    $this->actingAs($user)
        ->post(route('votes.intent', $product), ['intent' => 'want'])
        ->assertRedirect();

    $this->assertDatabaseHas('product_intent_votes', [
        'product_id' => $product->id,
        'user_id' => $user->id,
        'intent' => 'want',
    ]);
});

it('allows presale checkout and concept crowdfunding without stock deduction', function (): void {
    $this->seed();

    $user = User::factory()->create(['role' => 'customer']);
    $product = Product::query()->firstOrFail();
    $variant = $product->variants()->firstOrFail();

    $product->update(['status' => Product::STATUS_CONCEPT]);

    $this->post(route('cart.items.store'), [
        'variant_id' => $variant->id,
        'quantity' => 1,
    ])->assertRedirect(route('cart.show'));

    $this->actingAs($user)->post(route('checkout.store'), [
        'contact_name' => '概念筹款用户',
        'contact_phone' => '13800000000',
        'contact_email' => 'concept@example.com',
    ])->assertRedirect();

    $conceptOrder = \App\Models\Order::query()->where('user_id', $user->id)->firstOrFail();
    app(OrderService::class)->confirmPayment($conceptOrder);

    expect($variant->fresh()->stock)->toBe($variant->stock);

    $product->update(['status' => Product::STATUS_PRESALE]);

    $this->post(route('cart.items.store'), [
        'variant_id' => $variant->id,
        'quantity' => 1,
    ])->assertRedirect(route('cart.show'));

    $this->actingAs($user)->post(route('checkout.store'), [
        'contact_name' => '预售用户',
        'contact_phone' => '13900000000',
        'contact_email' => 'presale@example.com',
    ])->assertRedirect();

    $order = \App\Models\Order::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
    app(OrderService::class)->confirmPayment($order);

    expect($variant->fresh()->stock)->toBe($variant->stock)
        ->and($order->fresh()->status)->toBe(\App\Models\Order::STATUS_PENDING_SHIPMENT);
});

it('marks in stock products sold out after confirmed payment consumes stock', function (): void {
    $this->seed();

    $user = User::factory()->create(['role' => 'customer']);
    $product = Product::query()->firstOrFail();
    $variant = $product->variants()->firstOrFail();
    $variant->update(['stock' => 1]);

    $this->post(route('cart.items.store'), [
        'variant_id' => $variant->id,
        'quantity' => 1,
    ])->assertRedirect(route('cart.show'));

    $this->actingAs($user)->post(route('checkout.store'), [
        'contact_name' => '现货用户',
        'contact_phone' => '13700000000',
        'contact_email' => 'stock@example.com',
    ])->assertRedirect();

    app(OrderService::class)->confirmPayment(\App\Models\Order::query()->where('user_id', $user->id)->firstOrFail());

    expect($variant->fresh()->stock)->toBe(0)
        ->and($product->fresh()->status)->toBe(Product::STATUS_SOLD_OUT);
});

it('hides storefront order numbers by default', function (): void {
    $this->seed();

    $user = User::factory()->create(['role' => 'customer']);
    $otherUser = User::factory()->create(['role' => 'customer']);
    $order = \App\Models\Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'PRIVATE-ORDER-1',
        'status' => \App\Models\Order::STATUS_PENDING_PAYMENT,
        'payment_status' => \App\Models\Order::PAYMENT_PENDING,
        'subtotal_cents' => 1000,
        'total_cents' => 1000,
        'contact_name' => '隐私用户',
        'contact_phone' => '13600000000',
    ]);

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('订单 #'.$order->id)
        ->assertSee('付款备注单号：PRIVATE-ORDER-1');
});

it('renders custom pages from markdown safely', function (): void {
    $page = Page::query()->create([
        'title' => '关于我们',
        'slug' => 'about-us',
        'body' => "## 购买说明\n\n- 人工确认付款\n\n<script>alert('xss')</script>",
        'excerpt' => '轻量商城说明页',
        'seo_title' => '关于 ShopWeb',
        'seo_description' => 'ShopWeb 自定义说明页面',
        'is_published' => true,
    ]);

    $this->get(route('pages.show', $page))
        ->assertOk()
        ->assertSee('关于我们')
        ->assertSee('购买说明')
        ->assertSee('人工确认付款')
        ->assertDontSee('<script>', false)
        ->assertDontSee('alert', false);
});

it('renders custom page cover images from the media library', function (): void {
    $asset = MediaAsset::query()->create([
        'name' => 'About cover',
        'path' => 'media/about-cover.jpg',
        'disk' => 'public_uploads',
        'mime_type' => 'image/jpeg',
        'alt' => 'About cover alt',
    ]);

    $page = Page::query()->create([
        'title' => 'About ShopWeb',
        'slug' => 'about-shopweb',
        'cover_media_asset_id' => $asset->id,
        'body' => 'Page body',
        'is_published' => true,
    ]);

    $this->get(route('pages.show', $page))
        ->assertOk()
        ->assertSee('/uploads/media/about-cover.jpg', false)
        ->assertSee('About cover alt', false);
});

it('tracks media library usage for page covers logo and markdown references', function (): void {
    $cover = MediaAsset::query()->create([
        'name' => 'Cover asset',
        'path' => 'media/cover.jpg',
        'disk' => 'public_uploads',
        'mime_type' => 'image/jpeg',
    ]);
    $logo = MediaAsset::query()->create([
        'name' => 'Logo asset',
        'path' => 'media/logo.png',
        'disk' => 'public_uploads',
        'mime_type' => 'image/png',
    ]);
    $markdown = MediaAsset::query()->create([
        'name' => 'Markdown asset',
        'path' => 'media/manual.pdf',
        'disk' => 'public_uploads',
        'mime_type' => 'application/pdf',
    ]);
    $unused = MediaAsset::query()->create([
        'name' => 'Unused asset',
        'path' => 'media/unused.png',
        'disk' => 'public_uploads',
        'mime_type' => 'image/png',
    ]);

    Page::query()->create([
        'title' => 'Cover page',
        'slug' => 'cover-page',
        'cover_media_asset_id' => $cover->id,
        'body' => "Download: {$markdown->url()}",
        'is_published' => true,
    ]);
    SiteSetting::query()->create([
        'site_name' => 'ShopWeb',
        'logo_path' => $logo->path,
    ]);

    expect($cover->fresh()->isReferenced())->toBeTrue()
        ->and($logo->fresh()->isReferenced())->toBeTrue()
        ->and($markdown->fresh()->isReferenced())->toBeTrue()
        ->and($unused->fresh()->isReferenced())->toBeFalse()
        ->and($cover->fresh()->usageSummary())->toContain('页面封面')
        ->and($logo->fresh()->usageSummary())->toContain('站点 Logo')
        ->and($markdown->fresh()->usageSummary())->toContain('Markdown 引用')
        ->and($unused->fresh()->usageSummary())->toBe('未使用');
});

it('renders site setting markdown safely', function (): void {
    SiteSetting::query()->create([
        'site_name' => 'ShopWeb',
        'home_title' => '首页说明',
        'home_content' => "## 首页内容\n\n<script>alert('home')</script>",
        'payment_instructions' => "## 付款说明\n\n<script>alert('pay')</script>",
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('首页内容')
        ->assertDontSee('<script>', false)
        ->assertDontSee('home', false);
});

it('applies storefront appearance settings as css variables', function (): void {
    SiteSetting::query()->delete();

    SiteSetting::query()->create([
        'site_name' => 'ShopWeb',
        'favicon_path' => 'site/favicon.png',
        'home_background_path' => 'site/home-bg.jpg',
        'auth_background_path' => 'site/auth-bg.jpg',
        'primary_color' => '#0f766e',
        'accent_color' => '#b91c1c',
        'background_color' => '#f8fafc',
        'button_radius' => 'md',
        'product_card_density' => 'compact',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('--shop-primary: #0f766e', false)
        ->assertSee('--shop-accent: #b91c1c', false)
        ->assertSee('--shop-background: #f8fafc', false)
        ->assertSee('--shop-button-radius: 6px', false)
        ->assertSee('--shop-product-card-padding: 0.5rem', false)
        ->assertSee('/uploads/site/favicon.png', false)
        ->assertSee('--shop-page-background-image: url(http://localhost/uploads/site/home-bg.jpg)', false);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('--shop-page-background-image: url(http://localhost/uploads/site/auth-bg.jpg)', false);
});

it('can hide the home welcome section and query shipments by order number only', function (): void {
    $this->seed();

    SiteSetting::query()->first()->update([
        'home_welcome_enabled' => false,
        'home_title' => '不要显示的欢迎区',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('不要显示的欢迎区');

    $user = User::factory()->create(['role' => 'customer']);
    $otherUser = User::factory()->create(['role' => 'customer']);
    $order = \App\Models\Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'SHIP-ONLY-1',
        'status' => \App\Models\Order::STATUS_SHIPPED,
        'payment_status' => \App\Models\Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 1000,
        'total_cents' => 1000,
        'contact_name' => 'A',
        'contact_phone' => '13800000000',
        'contact_email' => 'a@example.com',
        'requires_shipping' => true,
        'tracking_number' => 'TRACK-ONLY-1',
    ]);

    $this->get(route('shipments.show', ['order_number' => $order->order_number]))
        ->assertRedirect(route('login'));

    $this->actingAs($otherUser)
        ->get(route('shipments.show', ['order_number' => $order->order_number]))
        ->assertOk()
        ->assertSee('未在你的购买记录中找到匹配订单')
        ->assertDontSee('TRACK-ONLY-1');

    $this->actingAs($user)
        ->get(route('shipments.show', ['order_number' => $order->order_number]))
        ->assertOk()
        ->assertSee('TRACK-ONLY-1')
        ->assertDontSee('下单手机号');
});

it('orders home product sections and places store pages in the welcome header', function (): void {
    $this->seed();

    $category = \App\Models\Category::query()->firstOrFail();
    \App\Models\Page::query()->updateOrCreate(
        ['slug' => 'about-us'],
        ['title' => '关于我们', 'body' => 'About', 'is_published' => true],
    );

    $discountProduct = Product::query()->create([
        'category_id' => $category->id,
        'title' => '折扣测试商品',
        'slug' => 'discount-home-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);
    ProductVariant::query()->create([
        'product_id' => $discountProduct->id,
        'sku' => 'HOME-DISCOUNT-1',
        'price_cents' => 2000,
        'discount_price_cents' => 1500,
        'stock' => 10,
        'is_active' => true,
    ]);

    Product::query()->create([
        'category_id' => $category->id,
        'title' => '概念测试商品',
        'slug' => 'concept-home-product',
        'status' => Product::STATUS_CONCEPT,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);

    $response = $this->get(route('home'))
        ->assertOk()
        ->assertSee('商店信息')
        ->assertSee('关于我们')
        ->assertSee('推荐商品')
        ->assertSee('最新商品')
        ->assertSee('折扣商品')
        ->assertSee('概念商品')
        ->assertSee('折扣测试商品')
        ->assertSee('概念测试商品')
        ->assertSee('购买')
        ->assertSee('加入购物车')
        ->assertSee('投票')
        ->assertSee('筹款');

    $html = $response->getContent();

    expect(strpos($html, '推荐商品'))->toBeLessThan(strpos($html, '最新商品'))
        ->and(strpos($html, '最新商品'))->toBeLessThan(strpos($html, '折扣商品'))
        ->and(strpos($html, '折扣商品'))->toBeLessThan(strpos($html, '概念商品'));
});

it('always renders home discount and concept sections with empty states', function (): void {
    $this->seed();

    Product::query()
        ->where('status', Product::STATUS_CONCEPT)
        ->update(['status' => Product::STATUS_DRAFT]);

    ProductVariant::query()->update([
        'discount_price_cents' => null,
        'discount_starts_at' => null,
        'discount_ends_at' => null,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('折扣商品')
        ->assertSee('暂无折扣商品')
        ->assertSee('概念商品')
        ->assertSee('暂无概念商品')
        ->assertSee(route('products.index', ['discount' => 1]), false)
        ->assertSee(route('products.index', ['status' => Product::STATUS_CONCEPT]), false);
});

it('filters storefront product lists by featured discount and concept sections', function (): void {
    $this->seed();

    $category = \App\Models\Category::query()->firstOrFail();

    $featuredProduct = Product::query()->create([
        'category_id' => $category->id,
        'title' => '首页推荐筛选商品',
        'slug' => 'featured-filter-product',
        'status' => Product::STATUS_PUBLISHED,
        'is_featured' => true,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);
    ProductVariant::query()->create([
        'product_id' => $featuredProduct->id,
        'sku' => 'FEATURED-FILTER-1',
        'price_cents' => 1000,
        'stock' => 1,
        'is_active' => true,
    ]);

    $discountProduct = Product::query()->create([
        'category_id' => $category->id,
        'title' => '首页折扣筛选商品',
        'slug' => 'discount-filter-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);
    ProductVariant::query()->create([
        'product_id' => $discountProduct->id,
        'sku' => 'DISCOUNT-FILTER-1',
        'price_cents' => 2000,
        'discount_price_cents' => 1200,
        'stock' => 1,
        'is_active' => true,
    ]);

    $conceptProduct = Product::query()->create([
        'category_id' => $category->id,
        'title' => '首页概念筛选商品',
        'slug' => 'concept-filter-product',
        'status' => Product::STATUS_CONCEPT,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);

    $this->get(route('products.index', ['featured' => 1]))
        ->assertOk()
        ->assertSee('推荐商品')
        ->assertSee($featuredProduct->title)
        ->assertDontSee($discountProduct->title)
        ->assertDontSee($conceptProduct->title);

    $this->get(route('products.index', ['discount' => 1]))
        ->assertOk()
        ->assertSee('折扣商品')
        ->assertSee($discountProduct->title)
        ->assertDontSee($conceptProduct->title);

    $this->get(route('products.index', ['status' => Product::STATUS_CONCEPT]))
        ->assertOk()
        ->assertSee('概念商品')
        ->assertSee($conceptProduct->title)
        ->assertDontSee($discountProduct->title);
});

it('lets users buy now from product cards and crowdfund concept products through normal orders', function (): void {
    $this->seed();

    $user = User::factory()->create(['role' => 'customer']);
    $category = \App\Models\Category::query()->firstOrFail();

    $stockProduct = Product::query()->create([
        'category_id' => $category->id,
        'title' => '立即购买商品',
        'slug' => 'buy-now-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);
    $stockVariant = ProductVariant::query()->create([
        'product_id' => $stockProduct->id,
        'sku' => 'BUY-NOW-1',
        'price_cents' => 3000,
        'stock' => 3,
        'is_active' => true,
    ]);

    $this->post(route('cart.buy-now'), [
        'variant_id' => $stockVariant->id,
        'quantity' => 1,
    ])->assertRedirect(route('checkout.create'));

    $this->followingRedirects()
        ->get(route('checkout.create'))
        ->assertSee('登录');

    $this->actingAs($user)
        ->post(route('cart.buy-now'), [
            'variant_id' => $stockVariant->id,
            'quantity' => 1,
        ])
        ->assertRedirect(route('checkout.create'));

    $this->actingAs($user)->post(route('checkout.store'), [
        'contact_name' => '立即购买用户',
        'contact_phone' => '13800000000',
        'contact_email' => 'buy-now@example.com',
    ])->assertRedirect();

    $this->assertDatabaseHas('orders', [
        'user_id' => $user->id,
        'subtotal_cents' => 3000,
        'total_cents' => 3000,
    ]);

    $concept = Product::query()->create([
        'category_id' => $category->id,
        'title' => '概念筹款商品',
        'slug' => 'concept-crowdfund-product',
        'status' => Product::STATUS_CONCEPT,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
        'crowdfunding_enabled' => true,
        'crowdfunding_goal_cents' => 500000,
    ]);
    $conceptVariant = ProductVariant::query()->create([
        'product_id' => $concept->id,
        'sku' => 'CONCEPT-FUND-1',
        'price_cents' => 5000,
        'stock' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('cart.buy-now'), [
            'variant_id' => $conceptVariant->id,
            'quantity' => 2,
        ])
        ->assertRedirect(route('checkout.create'));

    $this->actingAs($user)->post(route('checkout.store'), [
        'contact_name' => '筹款用户',
        'contact_phone' => '13900000000',
        'contact_email' => 'concept@example.com',
        'customer_note' => '概念商品筹款',
    ])->assertRedirect();

    $this->assertDatabaseHas('order_items', [
        'product_id' => $concept->id,
        'product_status' => Product::STATUS_CONCEPT,
        'variant_sku' => 'CONCEPT-FUND-1',
        'quantity' => 2,
        'line_total_cents' => 10000,
    ]);

    app(OrderService::class)->confirmPayment(\App\Models\Order::query()
        ->whereHas('items', fn ($query) => $query->where('product_id', $concept->id))
        ->firstOrFail());

    expect($conceptVariant->fresh()->stock)->toBe(0);
});

it('renders product videos and optional product introduction', function (): void {
    $category = \App\Models\Category::query()->create(['name' => '媒体', 'slug' => 'media', 'is_active' => true]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => '视频商品',
        'slug' => 'video-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
        'description' => null,
    ]);
    ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'VIDEO-1',
        'price_cents' => 1000,
        'stock' => 5,
        'is_active' => true,
    ]);
    ProductMedia::query()->create([
        'product_id' => $product->id,
        'type' => ProductMedia::TYPE_PREVIEW,
        'media_kind' => ProductMedia::KIND_VIDEO,
        'path' => 'products/demo.mp4',
        'mime_type' => 'video/mp4',
        'sort_order' => 1,
    ]);

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertSee('<video', false)
        ->assertSee('products/demo.mp4', false)
        ->assertSee('暂无详情说明');
});

it('reserves flash sale quota before selecting a variant and never returns cancelled quota to the flash sale', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $category = \App\Models\Category::query()->create(['name' => '秒杀', 'slug' => 'flash', 'is_active' => true]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => '秒杀商品',
        'slug' => 'flash-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);
    $variantA = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'FLASH-A',
        'specs' => ['颜色' => 'A'],
        'price_cents' => 5000,
        'stock' => 1,
        'is_active' => true,
    ]);
    $variantB = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'FLASH-B',
        'specs' => ['颜色' => 'B'],
        'price_cents' => 5000,
        'stock' => 2,
        'is_active' => true,
    ]);
    $flashSale = FlashSale::query()->create([
        'product_id' => $product->id,
        'product_variant_ids' => [$variantA->id, $variantB->id],
        'name' => '今晚秒杀',
        'sale_price_cents' => 990,
        'quantity_limit' => 2,
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addHour(),
        'is_active' => true,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('秒杀商品')
        ->assertSee('登录后抢')
        ->assertSee('本场剩余：2 件');

    $this->actingAs($user)
        ->post(route('flash-sales.reserve', $flashSale), ['quantity' => 1])
        ->assertRedirect();

    $order = \App\Models\Order::query()->where('user_id', $user->id)->firstOrFail();

    expect($flashSale->fresh()->sold_quantity)->toBe(1)
        ->and($order->items()->first()->product_variant_id)->toBeNull()
        ->and($order->items()->first()->variant_sku)->toBe('待选择规格');

    $this->actingAs($user)
        ->get(route('flash-sales.checkout', $order))
        ->assertOk()
        ->assertSee('你已抢到秒杀名额')
        ->assertSee('value="'.$variantA->id.'"', false)
        ->assertSee('value="'.$variantB->id.'"', false);

    $this->actingAs($user)
        ->post(route('flash-sales.store', $order), [
            'product_variant_id' => $variantB->id,
            'contact_name' => '秒杀用户',
            'contact_phone' => '13800000000',
            'contact_email' => 'flash@example.com',
        ])
        ->assertRedirect(route('orders.show', $order));

    $this->assertDatabaseHas('order_items', [
        'order_id' => $order->id,
        'product_variant_id' => $variantB->id,
        'variant_sku' => 'FLASH-B',
        'unit_price_cents' => 990,
        'line_total_cents' => 990,
        'flash_sale_id' => $flashSale->id,
    ]);

    app(OrderService::class)->cancel($order);

    expect($flashSale->fresh()->sold_quantity)->toBe(1);
});

it('shows the next flash sale time when a flash sale has not started yet', function (): void {
    $category = \App\Models\Category::query()->create(['name' => '下次秒杀', 'slug' => 'next-flash', 'is_active' => true]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => '下次秒杀商品',
        'slug' => 'next-flash-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'NEXT-FLASH',
        'price_cents' => 5000,
        'stock' => 2,
        'is_active' => true,
    ]);
    $startsAt = now()->addDay()->setTime(20, 30);
    FlashSale::query()->create([
        'product_id' => $product->id,
        'product_variant_ids' => [$variant->id],
        'name' => '明晚秒杀',
        'sale_price_cents' => 1990,
        'quantity_limit' => 2,
        'starts_at' => $startsAt,
        'is_active' => true,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('下次秒杀：'.$startsAt->format('m-d H:i'))
        ->assertSee('未开始');
});
