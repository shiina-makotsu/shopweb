<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\ReportMetrics;

it('reports product category and customer sales from fulfilled orders only', function (): void {
    $customer = User::factory()->create(['role' => 'customer']);
    $category = Category::query()->create([
        'name' => 'Digital Goods',
        'slug' => 'digital-goods',
        'is_active' => true,
    ]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => 'Starter Pack',
        'slug' => 'starter-pack',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_CONTACT,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'PACK-001',
        'price_cents' => 12000,
        'stock' => 10,
        'is_active' => true,
    ]);

    $paidOrder = Order::query()->create([
        'user_id' => $customer->id,
        'order_number' => 'RPT-PAID',
        'status' => Order::STATUS_FULFILLED,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 24000,
        'discount_cents' => 0,
        'total_cents' => 24000,
        'contact_name' => 'Buyer',
        'contact_phone' => '13800000000',
        'paid_at' => now(),
        'fulfilled_at' => now(),
    ]);
    OrderItem::query()->create([
        'order_id' => $paidOrder->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_title' => $product->title,
        'variant_sku' => $variant->sku,
        'unit_price_cents' => 12000,
        'quantity' => 2,
        'line_total_cents' => 24000,
    ]);

    $pendingOrder = Order::query()->create([
        'user_id' => $customer->id,
        'order_number' => 'RPT-PENDING',
        'status' => Order::STATUS_PENDING_PAYMENT,
        'payment_status' => Order::PAYMENT_PENDING,
        'subtotal_cents' => 12000,
        'discount_cents' => 0,
        'total_cents' => 12000,
        'contact_name' => 'Buyer',
        'contact_phone' => '13800000000',
    ]);
    OrderItem::query()->create([
        'order_id' => $pendingOrder->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_title' => $product->title,
        'variant_sku' => $variant->sku,
        'unit_price_cents' => 12000,
        'quantity' => 1,
        'line_total_cents' => 12000,
    ]);

    $paidNotFulfilledOrder = Order::query()->create([
        'user_id' => $customer->id,
        'order_number' => 'RPT-PAID-NOT-FULFILLED',
        'status' => Order::STATUS_PAID,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 12000,
        'discount_cents' => 0,
        'total_cents' => 12000,
        'contact_name' => 'Buyer',
        'contact_phone' => '13800000000',
        'paid_at' => now(),
    ]);
    OrderItem::query()->create([
        'order_id' => $paidNotFulfilledOrder->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_title' => $product->title,
        'variant_sku' => $variant->sku,
        'unit_price_cents' => 12000,
        'quantity' => 1,
        'line_total_cents' => 12000,
    ]);

    $metrics = app(ReportMetrics::class);

    expect($metrics->productSales()->first())->toMatchArray([
        'product' => 'Starter Pack',
        'sku' => 'PACK-001',
        'orders' => 1,
        'quantity' => 2,
        'total' => '¥240.00',
    ]);

    expect($metrics->categorySales()->first())->toMatchArray([
        'category' => 'Digital Goods',
        'items' => 1,
        'quantity' => 2,
        'total' => '¥240.00',
    ]);
});

it('keeps presale variants out of low stock and concept-only products in intent rankings', function (): void {
    $voter = User::factory()->create(['role' => 'customer']);
    $otherVoter = User::factory()->create(['role' => 'customer']);
    $concept = Product::query()->create([
        'title' => 'Concept Idea',
        'slug' => 'concept-idea',
        'status' => Product::STATUS_CONCEPT,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);
    $published = Product::query()->create([
        'title' => 'Published Item',
        'slug' => 'published-item',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);
    $presale = Product::query()->create([
        'title' => 'Presale Item',
        'slug' => 'presale-item',
        'status' => Product::STATUS_PRESALE,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);

    ProductVariant::query()->create([
        'product_id' => $presale->id,
        'sku' => 'PRE-LOW',
        'price_cents' => 1000,
        'stock' => 0,
        'low_stock_threshold' => 5,
        'is_active' => true,
    ]);
    ProductVariant::query()->create([
        'product_id' => $published->id,
        'sku' => 'PUB-LOW',
        'price_cents' => 1000,
        'stock' => 0,
        'low_stock_threshold' => 5,
        'is_active' => true,
    ]);

    $concept->intentVotes()->create(['user_id' => $voter->id, 'intent' => 'want']);
    $published->intentVotes()->create(['user_id' => $otherVoter->id, 'intent' => 'want']);

    $metrics = app(ReportMetrics::class);

    expect($metrics->lowStockVariants()->pluck('sku')->all())->toContain('PUB-LOW')
        ->not->toContain('PRE-LOW')
        ->and($metrics->intentVotes()->pluck('product')->all())->toBe(['Concept Idea']);
});

it('ranks customers by fulfilled order total only', function (): void {
    $customer = User::factory()->create(['role' => 'customer']);
    $other = User::factory()->create(['role' => 'customer']);

    Order::query()->create([
        'user_id' => $customer->id,
        'order_number' => 'RPT-CUSTOMER-FULFILLED',
        'status' => Order::STATUS_FULFILLED,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 24000,
        'discount_cents' => 0,
        'total_cents' => 24000,
        'contact_name' => 'Buyer',
        'contact_phone' => '13800000000',
        'paid_at' => now(),
        'fulfilled_at' => now(),
    ]);

    Order::query()->create([
        'user_id' => $customer->id,
        'order_number' => 'RPT-CUSTOMER-PAID-ONLY',
        'status' => Order::STATUS_PAID,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 99000,
        'discount_cents' => 0,
        'total_cents' => 99000,
        'contact_name' => 'Buyer',
        'contact_phone' => '13800000000',
        'paid_at' => now(),
    ]);

    Order::query()->create([
        'user_id' => $other->id,
        'order_number' => 'RPT-CUSTOMER-CANCELLED',
        'status' => Order::STATUS_CANCELLED,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 100000,
        'discount_cents' => 0,
        'total_cents' => 100000,
        'contact_name' => 'Buyer',
        'contact_phone' => '13800000000',
        'paid_at' => now(),
    ]);

    $topCustomer = app(ReportMetrics::class)->topCustomers()->first();

    expect($topCustomer['name'])->toBe($customer->name)
        ->and($topCustomer['orders'])->toBe(1)
        ->and($topCustomer['total'])->toBe(\App\Support\Money::format(24000));
});

it('exports report csv files for users with report access', function (): void {
    $this->seed();

    $finance = User::factory()->create(['role' => 'finance']);
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($finance)
        ->get(route('admin.report-exports.product-sales'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $this->actingAs($finance)
        ->get(route('admin.report-exports.profit-overview', [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
        ]))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $this->actingAs($customer)
        ->get(route('admin.report-exports.product-sales'))
        ->assertForbidden();

    $this->actingAs($customer)
        ->get(route('admin.report-exports.profit-overview'))
        ->assertForbidden();
});
