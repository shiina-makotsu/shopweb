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
