<?php

use App\Models\AnalyticsEvent;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\ReportMetrics;

it('tracks visit dimensions for guests customers and staff', function (): void {
    $customer = User::factory()->create(['role' => 'customer']);
    $staff = User::factory()->create(['role' => 'admin']);

    $this->withHeaders([
        'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile',
        'CF-IPCountry' => 'JP',
        'CF-IPCity' => 'Tokyo',
    ])->get('/')->assertOk();

    $this->actingAs($customer)
        ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'])
        ->get('/cart')
        ->assertOk();

    $this->actingAs($staff)
        ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'])
        ->get('/')
        ->assertOk();

    expect(AnalyticsEvent::query()->where('event', AnalyticsEvent::PAGE_VIEW)->where('visitor_type', 'guest')->where('device_type', 'mobile')->exists())->toBeTrue()
        ->and(AnalyticsEvent::query()->where('event', AnalyticsEvent::PAGE_VIEW)->where('visitor_type', 'customer')->where('device_type', 'desktop')->exists())->toBeTrue()
        ->and(AnalyticsEvent::query()->where('event', AnalyticsEvent::PAGE_VIEW)->where('visitor_type', 'staff')->where('surface', 'frontend')->exists())->toBeTrue();
});

it('summarizes today visitors by customer staff and guest ip', function (): void {
    $customer = User::factory()->create(['role' => 'customer', 'name' => '前台访问者']);
    $staff = User::factory()->create(['role' => 'support', 'name' => '客服访问者']);

    AnalyticsEvent::query()->create([
        'event' => AnalyticsEvent::PAGE_VIEW,
        'user_id' => $customer->id,
        'visitor_type' => 'customer',
        'surface' => 'frontend',
        'device_type' => 'desktop',
        'path' => '/',
        'ip_region' => 'CN / Guangdong',
        'created_at' => now()->setTime(9, 0),
    ]);
    AnalyticsEvent::query()->create([
        'event' => AnalyticsEvent::PAGE_VIEW,
        'user_id' => $customer->id,
        'visitor_type' => 'customer',
        'surface' => 'frontend',
        'device_type' => 'desktop',
        'path' => '/products',
        'ip_region' => 'CN / Guangdong',
        'created_at' => now()->setTime(9, 5),
    ]);
    AnalyticsEvent::query()->create([
        'event' => AnalyticsEvent::PAGE_VIEW,
        'user_id' => $staff->id,
        'visitor_type' => 'staff',
        'surface' => 'admin',
        'device_type' => 'desktop',
        'path' => 'admin/reports',
        'ip_region' => '本地/内网',
        'created_at' => now()->setTime(10, 0),
    ]);
    AnalyticsEvent::query()->create([
        'event' => AnalyticsEvent::PAGE_VIEW,
        'visitor_type' => 'guest',
        'surface' => 'frontend',
        'device_type' => 'mobile',
        'path' => '/',
        'ip_hash' => str_repeat('a', 64),
        'ip_region' => 'JP / Tokyo',
        'created_at' => now()->setTime(11, 0),
    ]);

    $rows = app(ReportMetrics::class)->todayVisitors();

    expect($rows->firstWhere('visitor', '前台访问者'))->toMatchArray([
        'type' => '前台用户',
        'visits' => 2,
        'pages' => 2,
    ])
        ->and($rows->firstWhere('visitor', '客服访问者'))->toMatchArray([
            'type' => '后台用户',
            'visits' => 1,
        ])
        ->and($rows->firstWhere('visitor', '游客'))->toMatchArray([
            'type' => '游客汇总',
            'visits' => 1,
        ]);

    $guestSummary = $rows->firstWhere('visitor', '游客');
    expect(collect($guestSummary['children'] ?? [])->firstWhere('visitor', '游客 aaaaaaaaaaaa'))->toMatchArray([
        'type' => '游客/IP',
        'region' => 'JP / Tokyo',
    ]);

    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin/reports')
        ->assertOk()
        ->assertSee('今日访问用户')
        ->assertSee('前台访问者')
        ->assertSee('游客');
});

it('does not write automatic admin page view logs unless enabled', function (): void {
    config(['shop.analytics.track_admin_page_views' => false]);

    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk();

    expect(AnalyticsEvent::query()
        ->where('event', AnalyticsEvent::PAGE_VIEW)
        ->where('surface', 'admin')
        ->exists())->toBeFalse();
});

it('excludes staff product detail visits from customer conversion metrics', function (): void {
    $category = Category::query()->create([
        'name' => 'Analytics',
        'slug' => 'analytics',
        'is_active' => true,
    ]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => 'Measured Product',
        'slug' => 'measured-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);
    ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'MEASURED-1',
        'price_cents' => 1000,
        'stock' => 5,
        'is_active' => true,
    ]);

    AnalyticsEvent::query()->create([
        'event' => AnalyticsEvent::PRODUCT_VIEW,
        'product_id' => $product->id,
        'visitor_type' => 'guest',
        'surface' => 'frontend',
        'device_type' => 'desktop',
    ]);
    AnalyticsEvent::query()->create([
        'event' => AnalyticsEvent::PRODUCT_VIEW,
        'product_id' => $product->id,
        'visitor_type' => 'customer',
        'surface' => 'frontend',
        'device_type' => 'mobile',
    ]);
    AnalyticsEvent::query()->create([
        'event' => AnalyticsEvent::PRODUCT_VIEW,
        'product_id' => $product->id,
        'visitor_type' => 'staff',
        'surface' => 'frontend',
        'device_type' => 'desktop',
    ]);

    $row = app(ReportMetrics::class)->productConversions()->first();

    expect($row)->toMatchArray([
        'product' => 'Measured Product',
        'views' => 2,
        'guest_views' => 1,
        'customer_views' => 1,
        'staff_views' => 1,
    ]);
});
