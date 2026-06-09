<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\ReportMetrics;

it('reports product and category sales from confirmed payment orders only', function (): void {
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
        'status' => Order::STATUS_PAID,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 24000,
        'discount_cents' => 0,
        'total_cents' => 24000,
        'contact_name' => 'Buyer',
        'contact_phone' => '13800000000',
        'paid_at' => now(),
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

    $metrics = app(ReportMetrics::class);

    expect($metrics->productSales()->first())->toMatchArray([
        'product' => 'Starter Pack',
        'sku' => 'PACK-001',
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

it('exports report csv files for users with report access', function (): void {
    $this->seed();

    $finance = User::factory()->create(['role' => 'finance']);
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($finance)
        ->get(route('admin.report-exports.product-sales'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $this->actingAs($customer)
        ->get(route('admin.report-exports.product-sales'))
        ->assertForbidden();
});
