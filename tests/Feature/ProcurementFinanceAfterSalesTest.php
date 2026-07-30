<?php

use App\Filament\Resources\AfterSalesRequestResource\Pages\ListAfterSalesRequests;
use App\Models\AdminActivityLog;
use App\Models\AfterSalesRequest;
use App\Models\Category;
use App\Models\CostEntry;
use App\Models\Coupon;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Procurement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SupportChatSession;
use App\Models\User;
use App\Models\SiteSetting;
use App\Models\UserProfileChangeLog;
use App\Models\Warehouse;
use App\Models\WarehouseMovement;
use App\Models\WarehouseStock;
use App\Services\BackofficeApprovalService;
use App\Services\CurrencyRateService;
use App\Services\ProcurementService;
use App\Services\CouponService;
use App\Services\OrderService;
use App\Services\WarehouseService;
use App\Support\AdminAccess;
use App\Support\CurrencyUnit;
use App\Support\ProfitMetrics;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

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
        ->and(CostEntry::query()->where('procurement_id', $procurement->id)->where('application_type', CostEntry::APPLICATION_PROCUREMENT)->count())->toBe(3)
        ->and(CostEntry::query()->where('procurement_id', $procurement->id)->where('is_effective', true)->count())->toBe(3)
        ->and(CostEntry::query()->where('procurement_id', $procurement->id)->where('effective_quantity', 10)->count())->toBe(3)
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

it('allows blank procurement country and customs rate while using country presets when present', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::query()->create(['name' => '海关', 'slug' => 'customs', 'is_active' => true]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => '海关商品',
        'slug' => 'customs-product',
        'status' => Product::STATUS_PRESALE,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);
    ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'CUSTOMS-1',
        'price_cents' => 1200,
        'stock' => 0,
        'is_active' => true,
    ]);

    $domestic = Procurement::query()->create([
        'product_id' => $product->id,
        'created_by_id' => $admin->id,
        'name' => '内地采购',
        'quantity' => 1,
        'purchase_amount_cents' => 10000,
        'shipping_amount_cents' => 1000,
        'shipping_country' => null,
        'customs_tax_rate' => 0,
        'status' => Procurement::STATUS_INCOMING,
    ]);

    app(ProcurementService::class)->syncProcurement($domestic);

    expect($domestic->fresh()->customs_tax_rate)->toBe('0.0000')
        ->and($domestic->fresh()->customs_tax_cents)->toBe(0);

    $foreign = Procurement::query()->create([
        'product_id' => $product->id,
        'created_by_id' => $admin->id,
        'name' => '日本采购',
        'quantity' => 1,
        'purchase_amount_cents' => 10000,
        'shipping_amount_cents' => 1000,
        'shipping_country' => 'JP',
        'customs_tax_rate' => 0,
        'status' => Procurement::STATUS_INCOMING,
    ]);

    app(ProcurementService::class)->syncProcurement($foreign);

    expect($foreign->fresh()->customs_tax_rate)->toBe('0.1000')
        ->and($foreign->fresh()->customs_tax_cents)->toBe(1100);
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

it('converts custom cost currencies and units into settlement cents', function (): void {
    expect(CurrencyUnit::unitOptions('CNY'))->toHaveKeys(['yuan', 'jiao', 'fen'])
        ->and(CurrencyUnit::unitOptions('FRF'))->toHaveKeys(['franc', 'centime'])
        ->and(CurrencyUnit::unitOptions('BEF'))->toHaveKeys(['franc', 'centime'])
        ->and(CurrencyUnit::unitOptions('ATS'))->toHaveKeys(['schilling', 'groschen'])
        ->and(CurrencyUnit::toSettlementCents(50, 'USD', 'cent', 7.2))->toBe(360)
        ->and(CurrencyUnit::toSettlementCents(12, 'CNY', 'jiao', 1))->toBe(120);
});

it('infers base currency from language unless the base currency is locked', function (): void {
    $settings = SiteSetting::query()->firstOrCreate([], [
        'site_name' => 'ShopWeb',
        'default_locale_mode' => 'system',
    ]);

    $settings->update([
        'default_locale_mode' => 'zh_HK',
        'store_currency' => 'CNY',
        'currency_base_unit' => 'yuan',
        'currency_base_locked' => false,
    ]);

    expect(CurrencyUnit::currencyForLocale('zh_CN'))->toBe('CNY')
        ->and(CurrencyUnit::currencyForLocale('zh_HK'))->toBe('HKD')
        ->and(CurrencyUnit::currencyForLocale('en_US'))->toBe('USD')
        ->and(CurrencyUnit::baseCurrency())->toBe('HKD')
        ->and(CurrencyUnit::baseUnit())->toBe('dollar');

    $settings->update([
        'store_currency' => 'USD',
        'currency_base_unit' => 'cent',
        'currency_base_locked' => true,
    ]);

    expect(CurrencyUnit::baseCurrency())->toBe('USD')
        ->and(CurrencyUnit::baseUnit())->toBe('cent');
});

it('stores exchange rates as selected currency to base currency and converts with units', function (): void {
    Http::fake([
        'https://open.er-api.com/v6/latest/CNY' => Http::response([
            'rates' => [
                'CNY' => 1,
                'USD' => 0.5,
                'JPY' => 20,
            ],
        ]),
        'https://api.metals.live/v1/spot/gold' => Http::response([
            ['gold' => 2000],
        ]),
        'https://open.er-api.com/v6/latest/USD' => Http::response([
            'rates' => [
                'USD' => 1,
                'CNY' => 7.2,
            ],
        ]),
    ]);

    $settings = SiteSetting::query()->firstOrCreate([], ['site_name' => 'ShopWeb']);
    $settings->update([
        'store_currency' => 'CNY',
        'currency_base_unit' => 'yuan',
        'currency_base_locked' => true,
    ]);

    $fresh = app(CurrencyRateService::class)->refresh($settings->fresh());

    expect((float) $fresh->currency_exchange_rates['CNY'])->toBe(1.0)
        ->and((float) $fresh->currency_exchange_rates['USD'])->toBe(2.0)
        ->and(app(CurrencyRateService::class)->convert(1, 'USD', 'CNY', $fresh->currency_exchange_rates))->toBe(2.0)
        ->and($fresh->currency_gold_price)->not->toBeNull();
});

it('records user birthday and diagnosis certificate profile changes', function (): void {
    $user = User::factory()->create(['role' => 'customer']);

    $this->actingAs($user)
        ->patch(route('user.profile.update'), [
            'name' => $user->name,
            'birthday' => '2000-06-15',
            'has_diagnosis_certificate' => '1',
        ])
        ->assertRedirect();

    $user->refresh();

    expect($user->birthday?->format('Y-m-d'))->toBe('2000-06-15')
        ->and($user->has_diagnosis_certificate)->toBeTrue()
        ->and(UserProfileChangeLog::query()->where('user_id', $user->id)->where('field', 'birthday')->exists())->toBeTrue()
        ->and(UserProfileChangeLog::query()->where('user_id', $user->id)->where('field', 'has_diagnosis_certificate')->exists())->toBeTrue();
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

it('calculates profit from effective costs only', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $category = Category::query()->create(['name' => 'Effective Cost Category', 'slug' => 'effective-cost-category', 'is_active' => true]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => 'Effective Cost Product',
        'slug' => 'effective-cost-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'EFFECTIVE-COST-1',
        'price_cents' => 10000,
        'stock' => 5,
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'EFFECTIVE-COST-1',
        'status' => Order::STATUS_FULFILLED,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 10000,
        'total_cents' => 10000,
        'contact_name' => 'Buyer',
        'contact_phone' => '1',
    ]);
    OrderItem::query()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_title' => $product->title,
        'product_status' => Product::STATUS_PUBLISHED,
        'variant_sku' => $variant->sku,
        'unit_price_cents' => 10000,
        'quantity' => 1,
        'line_total_cents' => 10000,
    ]);
    CostEntry::query()->create([
        'category' => CostEntry::CATEGORY_OTHER,
        'application_type' => CostEntry::APPLICATION_RECURRING,
        'name' => '仓储持续成本',
        'amount_cents' => 700,
        'is_effective' => true,
        'effective_times' => 1,
        'effective_at' => now(),
    ]);
    CostEntry::query()->create([
        'category' => CostEntry::CATEGORY_PURCHASE,
        'application_type' => CostEntry::APPLICATION_PROCUREMENT,
        'name' => '未采购的采购成本',
        'amount_cents' => 3000,
        'is_effective' => false,
        'effective_times' => 0,
    ]);

    $summary = app(ProfitMetrics::class)->summary();

    expect($summary['sales_cents'])->toBe(10000)
        ->and($summary['purchase_cost_cents'])->toBe(0)
        ->and($summary['cost_cents'])->toBe(700)
        ->and($summary['profit_cents'])->toBe(9300);
});

it('calculates fulfillment profit with named multi variable formulas', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $category = Category::query()->create(['name' => '公式分类', 'slug' => 'formula-category', 'is_active' => true]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => '公式商品',
        'slug' => 'formula-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'FORMULA-1',
        'price_cents' => 10000,
        'stock' => 5,
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'FORMULA-1',
        'status' => Order::STATUS_FULFILLED,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 10000,
        'total_cents' => 10000,
        'contact_name' => 'Buyer',
        'contact_phone' => '1',
    ]);
    OrderItem::query()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_title' => $product->title,
        'product_status' => Product::STATUS_PUBLISHED,
        'variant_sku' => $variant->sku,
        'unit_price_cents' => 10000,
        'quantity' => 1,
        'line_total_cents' => 10000,
    ]);
    CostEntry::query()->create(['category' => CostEntry::CATEGORY_PURCHASE, 'name' => '商品成本', 'amount_cents' => 3000]);
    CostEntry::query()->create(['category' => CostEntry::CATEGORY_SHIPPING, 'name' => '运输成本', 'amount_cents' => 500]);
    CostEntry::query()->create(['category' => CostEntry::CATEGORY_OTHER, 'name' => '账号成本', 'amount_cents' => 800]);

    $metrics = app(ProfitMetrics::class);
    $metrics->updateProfitFormulaConfig([
        'result_name' => '商品利润',
        'items' => [
            ['variable' => 'sales'],
            ['operator' => ProfitMetrics::OPERATOR_SUBTRACT, 'variable' => 'purchase_cost'],
            ['operator' => ProfitMetrics::OPERATOR_SUBTRACT, 'variable' => 'shipping_cost'],
            ['operator' => ProfitMetrics::OPERATOR_ADD, 'variable' => $metrics->costNameVariableKey('账号成本')],
        ],
    ]);

    $row = collect($metrics->fulfillmentBreakdown())
        ->firstWhere('type', Product::FULFILLMENT_LOGISTICS);

    expect($metrics->profitFormula())->toBe('商品利润 = sales - purchase_cost - shipping_cost + '.$metrics->costNameVariableKey('账号成本'))
        ->and($row)->not->toBeNull()
        ->and($row['formula_result_name'])->toBe('商品利润')
        ->and($row['formula_profit_cents'])->toBe(7300);
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

it('submits a quick after sales message without touching missing refund review columns', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'customer']);
    $request = AfterSalesRequest::query()->create([
        'user_id' => $user->id,
        'type' => 'support',
        'status' => AfterSalesRequest::STATUS_OPEN,
        'subject' => '快速处理留言测试',
        'message' => '请处理。',
        'admin_note' => '首次联系记录',
    ]);
    $repairMigration = database_path('migrations/2026_07_26_000001_repair_after_sales_refund_columns.php');

    Schema::table('after_sales_requests', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->dropColumn('refund_reviewed_at');
    });

    try {
        Livewire::actingAs($admin)
            ->test(ListAfterSalesRequests::class)
            ->callTableAction('resolve', $request, [
                'resolution_type' => AfterSalesRequest::RESOLUTION_MESSAGE,
                'admin_note' => '快速处理回复内容',
            ])
            ->assertHasNoTableActionErrors();

        $request->refresh();

        expect($request->status)->toBe(AfterSalesRequest::STATUS_RESOLVED)
            ->and($request->resolution_type)->toBe(AfterSalesRequest::RESOLUTION_MESSAGE)
            ->and($request->admin_note)->toBe('首次联系记录'.PHP_EOL.PHP_EOL.'快速处理回复内容')
            ->and($request->resolved_at)->not->toBeNull();
    } finally {
        (require $repairMigration)->up();
    }

    expect(Schema::hasColumn('after_sales_requests', 'refund_reviewed_at'))->toBeTrue();
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

it('adds after sales compensation coupons to the user coupon wallet', function (): void {
    $support = User::factory()->create(['role' => 'support']);
    $user = User::factory()->create(['role' => 'customer']);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'AFTER-SALES-COUPON-1',
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
        'type' => 'support',
        'status' => AfterSalesRequest::STATUS_OPEN,
        'subject' => 'Coupon compensation',
        'message' => 'Please compensate.',
    ]);
    $coupon = Coupon::query()->create([
        'code' => 'AFTERSALE10',
        'name' => '售后补偿券',
        'type' => Coupon::TYPE_FIXED,
        'value' => 1000,
        'scope' => Coupon::SCOPE_GLOBAL,
        'is_active' => true,
    ]);

    app(CouponService::class)->issueToUser(
        $coupon,
        $user,
        \App\Models\UserCoupon::SOURCE_AFTER_SALES,
        $support,
        $request->id,
        '售后补偿',
    );

    $this->assertDatabaseHas('user_coupons', [
        'user_id' => $user->id,
        'coupon_id' => $coupon->id,
        'issued_by_user_id' => $support->id,
        'after_sales_request_id' => $request->id,
        'source' => \App\Models\UserCoupon::SOURCE_AFTER_SALES,
    ]);
});

it('lets support request coupon and refund approvals from chat while reviewers can approve them', function (): void {
    $support = User::factory()->create(['role' => 'support']);
    $finance = User::factory()->create(['role' => 'finance']);
    $user = User::factory()->create(['role' => 'customer']);
    $coupon = Coupon::query()->create([
        'code' => 'CHAT10',
        'name' => 'Chat compensation',
        'type' => Coupon::TYPE_FIXED,
        'value' => 1000,
        'scope' => Coupon::SCOPE_GLOBAL,
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'CHAT-APPROVAL-1',
        'status' => Order::STATUS_FULFILLED,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 3000,
        'total_cents' => 3000,
        'contact_name' => 'Buyer',
        'contact_phone' => '1',
    ]);
    $session = SupportChatSession::query()->create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'status' => SupportChatSession::STATUS_ACTIVE,
        'last_message_at' => now(),
    ]);

    $couponRequest = app(BackofficeApprovalService::class)->requestCouponFromChat($session, $coupon, $support, 'Please compensate.');
    $refundRequest = app(BackofficeApprovalService::class)->requestRefundFromChat($session, $support, 1200, 'Please refund.');

    expect($couponRequest->resolution_type)->toBe(AfterSalesRequest::RESOLUTION_COUPON)
        ->and($couponRequest->status)->toBe(AfterSalesRequest::STATUS_CONTACTING)
        ->and($refundRequest->refund_status)->toBe(AfterSalesRequest::REFUND_REQUESTED);

    app(BackofficeApprovalService::class)->approveCouponRequest($couponRequest->fresh('user'), $finance, 'Approved.');
    app(BackofficeApprovalService::class)->approveRefundRequest($refundRequest->fresh(), $finance, 'Approved.');

    $this->assertDatabaseHas('user_coupons', [
        'user_id' => $user->id,
        'coupon_id' => $coupon->id,
        'issued_by_user_id' => $finance->id,
        'after_sales_request_id' => $couponRequest->id,
        'source' => \App\Models\UserCoupon::SOURCE_AFTER_SALES,
    ]);

    $this->assertDatabaseHas('after_sales_requests', [
        'id' => $refundRequest->id,
        'refund_status' => AfterSalesRequest::REFUND_APPROVED,
        'refund_reviewed_by_id' => $finance->id,
        'status' => AfterSalesRequest::STATUS_RESOLVED,
    ]);
});

it('keeps support coupon compensation requests pending until an approver issues the coupon', function (): void {
    $support = User::factory()->create(['role' => 'support']);
    $finance = User::factory()->create(['role' => 'finance']);
    $user = User::factory()->create(['role' => 'customer']);
    $coupon = Coupon::query()->create([
        'code' => 'PENDING10',
        'name' => 'Pending compensation',
        'type' => Coupon::TYPE_FIXED,
        'value' => 1000,
        'scope' => Coupon::SCOPE_GLOBAL,
        'is_active' => true,
    ]);
    $request = AfterSalesRequest::query()->create([
        'user_id' => $user->id,
        'type' => 'support',
        'status' => AfterSalesRequest::STATUS_OPEN,
        'subject' => 'Coupon approval',
        'message' => 'Need compensation.',
    ]);

    app(BackofficeApprovalService::class)->requestCouponForAfterSales($request, $coupon, $support, 'Please issue.');

    expect($request->fresh()->status)->toBe(AfterSalesRequest::STATUS_CONTACTING)
        ->and($request->fresh()->resolution_type)->toBe(AfterSalesRequest::RESOLUTION_COUPON);

    $this->assertDatabaseMissing('user_coupons', [
        'user_id' => $user->id,
        'coupon_id' => $coupon->id,
    ]);

    app(BackofficeApprovalService::class)->approveCouponRequest($request->fresh('user'), $finance, 'Approved.');

    $this->assertDatabaseHas('user_coupons', [
        'user_id' => $user->id,
        'coupon_id' => $coupon->id,
        'issued_by_user_id' => $finance->id,
    ]);
});

it('lets permitted staff directly issue coupons and register direct refunds from support chat', function (): void {
    $sales = User::factory()->create(['role' => 'sales']);
    $user = User::factory()->create(['role' => 'customer']);
    $coupon = Coupon::query()->create([
        'code' => 'DIRECT10',
        'name' => 'Direct compensation',
        'type' => Coupon::TYPE_FIXED,
        'value' => 1000,
        'scope' => Coupon::SCOPE_GLOBAL,
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'CHAT-DIRECT-1',
        'status' => Order::STATUS_FULFILLED,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 3000,
        'total_cents' => 3000,
        'contact_name' => 'Buyer',
        'contact_phone' => '1',
    ]);
    $session = SupportChatSession::query()->create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'status' => SupportChatSession::STATUS_ACTIVE,
        'last_message_at' => now(),
    ]);

    app(BackofficeApprovalService::class)->issueCouponForChat($session, $coupon, $sales, 'Direct coupon.');
    $refund = app(BackofficeApprovalService::class)->approveRefundForChat($session, $sales, 800, 'Direct refund.');

    $this->assertDatabaseHas('user_coupons', [
        'user_id' => $user->id,
        'coupon_id' => $coupon->id,
        'issued_by_user_id' => $sales->id,
        'source' => \App\Models\UserCoupon::SOURCE_AFTER_SALES,
    ]);

    expect($refund->refund_status)->toBe(AfterSalesRequest::REFUND_APPROVED)
        ->and($refund->status)->toBe(AfterSalesRequest::STATUS_RESOLVED)
        ->and($refund->refund_reviewed_by_id)->toBe($sales->id);
});

it('renders approval and coupon issue backoffice entry points by permission', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $support = User::factory()->create(['role' => 'support']);
    $finance = User::factory()->create(['role' => 'finance']);
    $user = User::factory()->create(['role' => 'customer']);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'CHAT-UI-1',
        'status' => Order::STATUS_FULFILLED,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 3000,
        'total_cents' => 3000,
        'contact_name' => 'Buyer',
        'contact_phone' => '1',
    ]);
    $session = SupportChatSession::query()->create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'status' => SupportChatSession::STATUS_ACTIVE,
        'last_message_at' => now(),
    ]);
    \App\Models\SupportChatMessage::query()->create([
        'support_chat_session_id' => $session->id,
        'sender_user_id' => $user->id,
        'sender_type' => 'customer',
        'body' => 'Need help.',
    ]);

    $this->actingAs($support)
        ->get('/admin/support-chat-sessions/'.$session->id.'/edit')
        ->assertOk()
        ->assertSee('申请优惠码')
        ->assertSee('申请退款')
        ->assertDontSee('发放优惠码')
        ->assertDontSee('直接退款');

    $this->actingAs($finance)
        ->get('/admin/support-chat-sessions/'.$session->id.'/edit')
        ->assertOk()
        ->assertSee('发放优惠码')
        ->assertSee('直接退款');

    $this->actingAs($finance)
        ->get('/admin/approval-requests')
        ->assertOk()
        ->assertSee('审批');

    $this->actingAs($admin)
        ->get('/admin/coupons')
        ->assertOk()
        ->assertSee('发放优惠码');
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

it('uses product sku as the warehouse sku when creating warehouse stock', function (): void {
    $category = Category::query()->create(['name' => '仓库 SKU', 'slug' => 'warehouse-sku', 'is_active' => true]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => '仓库 SKU 商品',
        'slug' => 'warehouse-sku-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'UNIFIED-SKU-1',
        'price_cents' => 1000,
        'stock' => 0,
        'is_active' => true,
    ]);
    $warehouse = Warehouse::query()->create([
        'name' => '统一 SKU 仓库',
        'is_active' => true,
    ]);

    $data = \App\Filament\Resources\WarehouseStockResource::normalizeFormData([
        'warehouse_id' => $warehouse->id,
        'product_id' => null,
        'product_variant_id' => $variant->id,
        'name' => '',
        'sku' => 'OLD-WAREHOUSE-SKU',
        'quantity' => 3,
        'reserved_quantity' => 0,
    ]);

    $stock = WarehouseStock::query()->create($data);

    expect($stock->product_id)->toBe($product->id)
        ->and($stock->sku)->toBe('UNIFIED-SKU-1')
        ->and($stock->name)->toBe('仓库 SKU 商品')
        ->and($stock->variant->sku)->toBe('UNIFIED-SKU-1');
});
