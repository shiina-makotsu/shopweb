<?php

use App\Models\AfterSalesRequest;
use App\Models\Category;
use App\Models\CostEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Procurement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SupportChatSession;
use App\Models\User;
use App\Models\WarehouseMovement;
use App\Models\WarehouseStock;
use App\Services\ProcurementService;
use App\Services\OrderService;
use App\Services\WarehouseService;
use App\Support\ProfitMetrics;

it('syncs procurement into an incoming product, auto costs, and allocated presale users', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'customer']);
    $category = Category::query()->create(['name' => '预售', 'slug' => 'presale', 'is_active' => true]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => '预售商品',
        'slug' => 'pre-order-product',
        'status' => Product::STATUS_PRESALE,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'PRE-001',
        'price_cents' => 1200,
        'stock' => 0,
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'PRE-PO-1',
        'status' => Order::STATUS_PENDING_SHIPMENT,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 2400,
        'total_cents' => 2400,
        'contact_name' => 'Buyer',
        'contact_phone' => '1',
        'requires_shipping' => true,
    ]);
    $item = OrderItem::query()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_title' => $product->title,
        'product_status' => Product::STATUS_PRESALE,
        'variant_sku' => $variant->sku,
        'unit_price_cents' => 1200,
        'quantity' => 2,
        'line_total_cents' => 2400,
        'status' => Order::STATUS_PENDING_SHIPMENT,
    ]);
    $procurement = Procurement::query()->create([
        'product_id' => $product->id,
        'created_by_id' => $admin->id,
        'name' => '第一批采购',
        'quantity' => 10,
        'purchase_amount_cents' => 10000,
        'shipping_amount_cents' => 2000,
        'shipping_country' => 'JP',
        'international_tracking_number' => 'INTL-PO-1',
        'tracking_url' => 'https://logistics.test/INTL-PO-1',
        'status' => Procurement::STATUS_INCOMING,
    ]);

    app(ProcurementService::class)->syncAllocations($procurement, [[
        'order_item_id' => $item->id,
        'allocated_quantity' => 1,
    ]]);

    $incoming = $procurement->fresh('incomingProduct')->incomingProduct;

    expect($incoming)->not->toBeNull()
        ->and($incoming->status)->toBe(Product::STATUS_INCOMING)
        ->and($incoming->incoming_quantity)->toBe(10)
        ->and($incoming->variants()->count())->toBe(1)
        ->and($procurement->fresh()->customs_tax_cents)->toBe(1200)
        ->and(CostEntry::query()->where('procurement_id', $procurement->id)->count())->toBe(3)
        ->and($item->fresh()->incoming_product_id)->toBe($incoming->id)
        ->and($item->fresh()->status)->toBe(Order::STATUS_INCOMING)
        ->and($order->fresh()->status)->toBe(Order::STATUS_INCOMING);

    $this->assertDatabaseHas('procurement_user_allocations', [
        'procurement_id' => $procurement->id,
        'order_item_id' => $item->id,
        'presale_quantity' => 2,
        'allocated_quantity' => 1,
    ]);
});

it('calculates profit from fulfilled orders minus costs', function (): void {
    $user = User::factory()->create(['role' => 'customer']);

    Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'DONE-1',
        'status' => Order::STATUS_FULFILLED,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 10000,
        'total_cents' => 10000,
        'contact_name' => 'Buyer',
        'contact_phone' => '1',
    ]);
    Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'PAID-1',
        'status' => Order::STATUS_PAID,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 5000,
        'total_cents' => 5000,
        'contact_name' => 'Buyer',
        'contact_phone' => '1',
    ]);
    CostEntry::query()->create([
        'category' => CostEntry::CATEGORY_OTHER,
        'name' => '平台成本',
        'amount_cents' => 3000,
    ]);

    expect(app(ProfitMetrics::class)->summary())->toMatchArray([
        'sales_cents' => 10000,
        'cost_cents' => 3000,
        'profit_cents' => 7000,
        'completed_orders' => 1,
    ]);
});

it('lets users submit after sales requests and contact support with their order only', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $other = User::factory()->create(['role' => 'customer']);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'AFTER-1',
        'status' => Order::STATUS_FULFILLED,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 1000,
        'total_cents' => 1000,
        'contact_name' => 'Buyer',
        'contact_phone' => '1',
    ]);
    $otherOrder = Order::query()->create([
        'user_id' => $other->id,
        'order_number' => 'AFTER-2',
        'status' => Order::STATUS_FULFILLED,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 1000,
        'total_cents' => 1000,
        'contact_name' => 'Other',
        'contact_phone' => '2',
    ]);

    $this->actingAs($user)
        ->post(route('orders.after-sales.store', $order), [
            'type' => 'refund',
            'subject' => '我要售后',
            'message' => '商品需要处理。',
        ])
        ->assertRedirect(route('orders.after-sales', $order));

    $this->assertDatabaseHas('after_sales_requests', [
        'user_id' => $user->id,
        'order_id' => $order->id,
        'subject' => '我要售后',
        'status' => AfterSalesRequest::STATUS_OPEN,
    ]);

    $this->actingAs($user)
        ->post(route('orders.contact-support', $order))
        ->assertRedirect(route('support.index', ['order_id' => $order->id]));

    $this->actingAs($user)
        ->get(route('support.index', ['order_id' => $order->id]))
        ->assertOk()
        ->assertSee('客服会话')
        ->assertSee('订单号：'.$order->order_number);

    $this->assertDatabaseHas('support_chat_sessions', [
        'user_id' => $user->id,
        'order_id' => $order->id,
        'status' => SupportChatSession::STATUS_OPEN,
    ]);

    $this->actingAs($user)
        ->get(route('orders.after-sales', $otherOrder))
        ->assertForbidden();
});

it('renders procurement finance and after sales backoffice pages by role', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $purchasing = User::factory()->create(['role' => 'purchasing']);
    $finance = User::factory()->create(['role' => 'finance']);
    $support = User::factory()->create(['role' => 'support']);

    $this->actingAs($admin)
        ->get('/admin/procurements')
        ->assertOk()
        ->assertSee('采购商品');

    $this->actingAs($admin)
        ->get('/admin/cost-entries')
        ->assertOk()
        ->assertSee('成本条目');

    $this->actingAs($admin)
        ->get('/admin/profit-overview')
        ->assertOk()
        ->assertSee('利润概览');

    $this->actingAs($admin)
        ->get('/admin/after-sales-requests')
        ->assertOk()
        ->assertSee('售后需求');

    $this->actingAs($purchasing)->get('/admin/procurements')->assertOk();
    $this->actingAs($purchasing)->get('/admin/cost-entries')->assertForbidden();

    $this->actingAs($finance)->get('/admin/cost-entries')->assertOk();
    $this->actingAs($finance)->get('/admin/procurements')->assertForbidden();

    $this->actingAs($support)->get('/admin/after-sales-requests')->assertOk();
    $this->actingAs($support)->get('/admin/procurements')->assertForbidden();
});

it('receives procurement into warehouse stock and ships allocated orders out of warehouse', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'customer']);
    $category = Category::query()->create(['name' => '仓库预售', 'slug' => 'warehouse-presale', 'is_active' => true]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => '仓库预售商品',
        'slug' => 'warehouse-presale-product',
        'status' => Product::STATUS_PRESALE,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'W-PRE-1',
        'price_cents' => 1000,
        'stock' => 0,
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'WARE-1',
        'status' => Order::STATUS_PENDING_SHIPMENT,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 1000,
        'total_cents' => 1000,
        'contact_name' => 'Buyer',
        'contact_phone' => '1',
        'requires_shipping' => true,
    ]);
    $item = OrderItem::query()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_title' => $product->title,
        'product_status' => Product::STATUS_PRESALE,
        'variant_sku' => $variant->sku,
        'unit_price_cents' => 1000,
        'quantity' => 2,
        'line_total_cents' => 2000,
        'status' => Order::STATUS_PENDING_SHIPMENT,
    ]);
    $procurement = Procurement::query()->create([
        'product_id' => $product->id,
        'created_by_id' => $admin->id,
        'name' => '仓库批次',
        'quantity' => 5,
        'purchase_amount_cents' => 5000,
        'shipping_country' => 'JP',
        'status' => Procurement::STATUS_INCOMING,
    ]);

    app(ProcurementService::class)->syncAllocations($procurement, [[
        'order_item_id' => $item->id,
        'allocated_quantity' => 2,
    ]]);
    app(WarehouseService::class)->receiveProcurement($procurement, $admin, '到货正常');

    expect($procurement->fresh()->status)->toBe(Procurement::STATUS_RECEIVED)
        ->and(WarehouseStock::query()->sum('quantity'))->toBe(5)
        ->and(WarehouseMovement::query()->where('type', WarehouseMovement::TYPE_RECEIVED)->exists())->toBeTrue();

    app(OrderService::class)->ship($order->fresh(), ['tracking_number' => 'CN-1'], $admin);

    expect(WarehouseStock::query()->sum('quantity'))->toBe(3)
        ->and(WarehouseMovement::query()->where('type', WarehouseMovement::TYPE_SHIPPED)->where('delta', -2)->exists())->toBeTrue();

    app(OrderService::class)->returnToWarehouse($order->fresh(), $admin, '拒收退回');

    expect(WarehouseStock::query()->sum('quantity'))->toBe(5)
        ->and(WarehouseMovement::query()->where('type', WarehouseMovement::TYPE_RETURNED)->where('delta', 2)->exists())->toBeTrue();
});
