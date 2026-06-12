<?php

use App\Models\AdminActivityLog;
use App\Models\AfterSalesRequest;
use App\Models\Category;
use App\Models\CostEntry;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Procurement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SupportChatSession;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseMovement;
use App\Models\WarehouseStock;
use App\Services\ProcurementService;
use App\Services\OrderService;
use App\Services\WarehouseService;
use App\Support\AdminAccess;
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

    $summary = app(ProfitMetrics::class)->summary();

    expect($summary)->toMatchArray([
        'sales_cents' => 10000,
        'purchase_cost_cents' => 0,
        'gross_profit_cents' => 10000,
        'cost_cents' => 3000,
        'profit_cents' => 7000,
        'completed_orders' => 1,
    ])->and($summary['gross_profit_rate'])->toBe(1.0)
        ->and($summary['profit_rate'])->toBe(0.7);
});

it('calculates gross profit and warehouse profit breakdowns', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'customer']);
    $category = Category::query()->create(['name' => '利润仓库', 'slug' => 'profit-warehouse', 'is_active' => true]);
    $warehouseA = Warehouse::query()->create(['name' => 'A 仓', 'country' => '中国', 'is_active' => true]);
    $warehouseB = Warehouse::query()->create(['name' => 'B 仓', 'country' => '中国', 'is_active' => true]);

    $productA = Product::query()->create([
        'category_id' => $category->id,
        'title' => '利润商品 A',
        'slug' => 'profit-product-a',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);
    $variantA = ProductVariant::query()->create(['product_id' => $productA->id, 'sku' => 'PROFIT-A', 'price_cents' => 10000, 'stock' => 5, 'is_active' => true]);

    $productB = Product::query()->create([
        'category_id' => $category->id,
        'title' => '利润商品 B',
        'slug' => 'profit-product-b',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);
    $variantB = ProductVariant::query()->create(['product_id' => $productB->id, 'sku' => 'PROFIT-B', 'price_cents' => 8000, 'stock' => 5, 'is_active' => true]);

    $orderA = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'PROFIT-A-1',
        'status' => Order::STATUS_FULFILLED,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 10000,
        'discount_cents' => 1000,
        'shipping_fee_cents' => 1200,
        'shipment_plan' => [['warehouse_id' => $warehouseA->id, 'warehouse_name' => $warehouseA->name, 'fee_cents' => 1200, 'items' => []]],
        'total_cents' => 10200,
        'contact_name' => 'Buyer',
        'contact_phone' => '1',
    ]);
    OrderItem::query()->create([
        'order_id' => $orderA->id,
        'product_id' => $productA->id,
        'product_variant_id' => $variantA->id,
        'warehouse_id' => $warehouseA->id,
        'product_title' => $productA->title,
        'product_status' => Product::STATUS_PUBLISHED,
        'variant_sku' => $variantA->sku,
        'unit_price_cents' => 10000,
        'quantity' => 1,
        'line_total_cents' => 10000,
    ]);

    $orderB = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'PROFIT-B-1',
        'status' => Order::STATUS_FULFILLED,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 8000,
        'total_cents' => 8000,
        'contact_name' => 'Buyer',
        'contact_phone' => '1',
    ]);
    OrderItem::query()->create([
        'order_id' => $orderB->id,
        'product_id' => $productB->id,
        'product_variant_id' => $variantB->id,
        'warehouse_id' => $warehouseB->id,
        'product_title' => $productB->title,
        'product_status' => Product::STATUS_PUBLISHED,
        'variant_sku' => $variantB->sku,
        'unit_price_cents' => 8000,
        'quantity' => 1,
        'line_total_cents' => 8000,
    ]);

    $procurementA = Procurement::query()->create([
        'product_id' => $productA->id,
        'created_by_id' => $admin->id,
        'warehouse_id' => $warehouseA->id,
        'name' => 'A 仓采购',
        'quantity' => 1,
        'purchase_amount_cents' => 4000,
        'status' => Procurement::STATUS_RECEIVED,
    ]);
    CostEntry::query()->create(['procurement_id' => $procurementA->id, 'category' => CostEntry::CATEGORY_PURCHASE, 'name' => 'A 采购', 'amount_cents' => 4000]);
    CostEntry::query()->create(['procurement_id' => $procurementA->id, 'category' => CostEntry::CATEGORY_SHIPPING, 'name' => 'A 运输', 'amount_cents' => 500]);

    $procurementB = Procurement::query()->create([
        'product_id' => $productB->id,
        'created_by_id' => $admin->id,
        'warehouse_id' => $warehouseB->id,
        'name' => 'B 仓采购',
        'quantity' => 1,
        'purchase_amount_cents' => 1000,
        'status' => Procurement::STATUS_RECEIVED,
    ]);
    CostEntry::query()->create(['procurement_id' => $procurementB->id, 'category' => CostEntry::CATEGORY_PURCHASE, 'name' => 'B 采购', 'amount_cents' => 1000]);
    CostEntry::query()->create(['category' => CostEntry::CATEGORY_OTHER, 'name' => '未分配成本', 'amount_cents' => 300]);

    $metrics = app(ProfitMetrics::class);

    $summary = $metrics->summary();

    expect($summary)->toMatchArray([
        'sales_cents' => 18200,
        'purchase_cost_cents' => 5000,
        'gross_profit_cents' => 13200,
        'cost_cents' => 5800,
        'profit_cents' => 12400,
    ])->and(round($summary['gross_profit_rate'] * 100, 2))->toBe(72.53)
        ->and(round($summary['profit_rate'] * 100, 2))->toBe(68.13);

    $rows = collect($metrics->warehouseBreakdown())->keyBy('warehouse_name');

    expect($rows['A 仓'])->toMatchArray([
        'sales_cents' => 10200,
        'cost_cents' => 4500,
        'profit_cents' => 5700,
        'orders_count' => 1,
        'profit_rate' => 5700 / 10200,
    ])->and($rows['B 仓'])->toMatchArray([
        'sales_cents' => 8000,
        'cost_cents' => 1000,
        'profit_cents' => 7000,
        'orders_count' => 1,
        'profit_rate' => 7000 / 8000,
    ])->and($rows['未分配仓库'])->toMatchArray([
        'sales_cents' => 0,
        'cost_cents' => 300,
        'profit_cents' => -300,
        'profit_rate' => null,
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
    $sales = User::factory()->create(['role' => 'sales']);

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

    $this->actingAs($finance)->get('/admin/after-sales-requests')->assertOk();
    $this->actingAs($sales)->get('/admin/after-sales-requests')->assertOk();

    expect(AdminAccess::canAction('after_sales.request_refund', $support))->toBeTrue()
        ->and(AdminAccess::canAction('after_sales.refund', $support))->toBeFalse()
        ->and(AdminAccess::canAction('after_sales.refund', $finance))->toBeTrue()
        ->and(AdminAccess::canAction('after_sales.refund', $sales))->toBeTrue();
});

it('splits after sales refund requests from refund approval permissions', function (): void {
    $support = User::factory()->create(['role' => 'support']);
    $sales = User::factory()->create(['role' => 'sales']);
    $user = User::factory()->create(['role' => 'customer']);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'REFUND-APPROVAL-1',
        'status' => Order::STATUS_FULFILLED,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 1000,
        'total_cents' => 1000,
        'contact_name' => 'Buyer',
        'contact_phone' => '1',
    ]);
    $request = AfterSalesRequest::query()->create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'type' => 'refund',
        'status' => AfterSalesRequest::STATUS_OPEN,
        'subject' => 'Refund review',
        'message' => 'Please review refund.',
    ]);

    $this->actingAs($support)
        ->get('/admin/after-sales-requests')
        ->assertOk()
        ->assertSee('发起退款申请')
        ->assertDontSee('审批退款');

    $request->update([
        'status' => AfterSalesRequest::STATUS_CONTACTING,
        'resolution_type' => AfterSalesRequest::RESOLUTION_REFUND,
        'refund_amount_cents' => 1000,
        'refund_status' => AfterSalesRequest::REFUND_REQUESTED,
        'refund_requested_by_id' => $support->id,
        'refund_requested_at' => now(),
    ]);

    $this->actingAs($sales)
        ->get('/admin/after-sales-requests')
        ->assertOk()
        ->assertSee('审批退款')
        ->assertSee('驳回退款');
});

it('renders an order business timeline in the backoffice order form', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'customer']);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'TIME-1',
        'status' => Order::STATUS_FULFILLED,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 1000,
        'total_cents' => 1000,
        'contact_name' => 'Buyer',
        'contact_phone' => '1',
        'paid_at' => now(),
        'fulfilled_at' => now(),
    ]);

    AdminActivityLog::query()->create([
        'user_id' => $admin->id,
        'action' => 'order_payment_confirmed',
        'subject_type' => $order->getMorphClass(),
        'subject_id' => $order->id,
        'description' => $order->order_number,
    ]);

    $this->actingAs($admin)
        ->get('/admin/orders/'.$order->id.'/edit')
        ->assertOk()
        ->assertSee('业务时间线')
        ->assertSee('下单')
        ->assertSee('确认付款')
        ->assertSee('后台确认收款');
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

    $incomingVariant = $procurement->fresh('incomingProduct.variants')->incomingProduct->variants()->firstOrFail();

    expect($procurement->fresh('incomingProduct')->incomingProduct->status)->toBe(Product::STATUS_PUBLISHED)
        ->and($incomingVariant->stock)->toBe(5)
        ->and($incomingVariant->is_active)->toBeTrue()
        ->and($item->fresh()->status)->toBe(Order::STATUS_PENDING_SHIPMENT)
        ->and($order->fresh()->status)->toBe(Order::STATUS_PENDING_SHIPMENT)
        ->and(InventoryMovement::query()->where('product_variant_id', $incomingVariant->id)->where('reason', 'warehouse_received')->exists())->toBeTrue();

    app(OrderService::class)->ship($order->fresh(), ['tracking_number' => 'CN-1'], $admin);

    expect(WarehouseStock::query()->sum('quantity'))->toBe(3)
        ->and($order->fresh()->status)->toBe(Order::STATUS_AWAITING_RECEIPT)
        ->and($order->fresh()->tracking_number)->toBe('CN-1')
        ->and($incomingVariant->fresh()->stock)->toBe(3)
        ->and(WarehouseMovement::query()->where('type', WarehouseMovement::TYPE_SHIPPED)->where('delta', -2)->exists())->toBeTrue()
        ->and(InventoryMovement::query()->where('product_variant_id', $incomingVariant->id)->where('reason', 'warehouse_shipped')->exists())->toBeTrue();

    app(OrderService::class)->returnToWarehouse($order->fresh(), $admin, '拒收退回');

    expect(WarehouseStock::query()->sum('quantity'))->toBe(5)
        ->and($incomingVariant->fresh()->stock)->toBe(5)
        ->and(WarehouseMovement::query()->where('type', WarehouseMovement::TYPE_RETURNED)->where('delta', 2)->exists())->toBeTrue()
        ->and(InventoryMovement::query()->where('product_variant_id', $incomingVariant->id)->where('reason', 'warehouse_returned')->exists())->toBeTrue();
});
