<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingCarrier;
use App\Models\SiteSetting;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\OrderService;
use App\Support\OrderPrivacy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

it('shows domestic tracking by default and hides international tracking until opened for the user', function (): void {
    $settings = SiteSetting::query()->create([
        'site_name' => 'ShopWeb',
        'show_tracking_numbers_to_users' => true,
    ]);
    $user = User::factory()->create(['role' => 'customer']);
    $domestic = ShippingCarrier::query()->create([
        'name' => '国内物流',
        'code' => 'CN',
        'is_international' => false,
    ]);
    $international = ShippingCarrier::query()->create([
        'name' => '国际物流',
        'code' => 'INTL',
        'is_international' => true,
    ]);
    $domesticOrder = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'D-1',
        'status' => Order::STATUS_SHIPPED,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 100,
        'total_cents' => 100,
        'contact_name' => 'A',
        'contact_phone' => '1',
        'shipping_carrier_id' => $domestic->id,
        'tracking_number' => 'CN123',
    ]);
    $internationalOrder = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'I-1',
        'status' => Order::STATUS_SHIPPED,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 100,
        'total_cents' => 100,
        'contact_name' => 'A',
        'contact_phone' => '1',
        'shipping_carrier_id' => $international->id,
        'tracking_number' => 'INTL123',
    ]);

    $privacy = app(OrderPrivacy::class);

    expect($privacy->displayTrackingNumber($domesticOrder, $user, $settings))->toBe('CN123')
        ->and($privacy->displayTrackingNumber($internationalOrder, $user, $settings))->toBe('后台已隐藏');

    $user->update(['can_view_tracking_numbers' => true]);

    expect($privacy->displayTrackingNumber($internationalOrder->fresh('shippingCarrier'), $user->fresh(), $settings))->toBe('INTL123');
});

it('auto checks payment proof for user display while keeping backend payment submitted', function (): void {
    Storage::fake('payment_proofs');

    SiteSetting::query()->create([
        'site_name' => 'ShopWeb',
        'payment_auto_check_enabled' => true,
    ]);
    $user = User::factory()->create(['role' => 'customer']);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'PAY-1',
        'status' => Order::STATUS_PENDING_PAYMENT,
        'payment_status' => Order::PAYMENT_PENDING,
        'subtotal_cents' => 100,
        'total_cents' => 100,
        'contact_name' => 'A',
        'contact_phone' => '1',
    ]);

    $path = UploadedFile::fake()->image('proof.png')->store($order->order_number, 'payment_proofs');
    app(OrderService::class)->markPaymentSubmitted($order, $path);

    expect($order->fresh()->payment_status)->toBe(Order::PAYMENT_SUBMITTED)
        ->and($order->fresh()->payment_auto_check_status)->toBe(Order::AUTO_CHECK_PASSED)
        ->and($order->fresh()->userPaymentLabel())->toBe('已付款');

    app(OrderService::class)->rejectPayment($order);

    expect($order->fresh()->payment_status)->toBe(Order::PAYMENT_PENDING)
        ->and($order->fresh()->status)->toBe(Order::STATUS_PENDING_PAYMENT)
        ->and($order->fresh()->userPaymentLabel())->toBe('待支付');
});

it('shows payment proof images to admins and a payment success state to customers', function (): void {
    Storage::fake('payment_proofs');

    SiteSetting::query()->create([
        'site_name' => 'ShopWeb',
        'payment_auto_check_enabled' => true,
    ]);
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'customer']);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'PAY-IMG-1',
        'status' => Order::STATUS_PENDING_PAYMENT,
        'payment_status' => Order::PAYMENT_PENDING,
        'subtotal_cents' => 100,
        'total_cents' => 100,
        'contact_name' => 'A',
        'contact_phone' => '1',
    ]);

    $this->actingAs($user)
        ->post(route('orders.payment-proof', $order), [
            'payment_proof' => UploadedFile::fake()->image('proof.png'),
        ])
        ->assertRedirect()
        ->assertSessionHas('payment_success', true);

    expect($order->fresh()->payment_proof_path)->not->toBeNull()
        ->and($order->fresh()->payment_status)->toBe(Order::PAYMENT_SUBMITTED)
        ->and($order->fresh()->payment_auto_check_status)->toBe(Order::AUTO_CHECK_PASSED)
        ->and($order->fresh()->userPaymentLabel())->toBe('已付款');

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('付款成功')
        ->assertDontSee('待后台人工复核')
        ->assertDontSee('后台会继续人工复核');

    $this->actingAs($admin)
        ->get(route('admin.payment-proofs.show', $order))
        ->assertOk();

    $this->actingAs($admin)
        ->get("/admin/orders/{$order->id}/edit")
        ->assertOk()
        ->assertSee('付款凭证图片')
        ->assertSee(route('admin.payment-proofs.show', $order), false);
});

it('keeps awaiting receipt orders open until the customer confirms receipt', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $otherUser = User::factory()->create(['role' => 'customer']);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'RECEIPT-1',
        'status' => Order::STATUS_AWAITING_RECEIPT,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 100,
        'total_cents' => 100,
        'contact_name' => 'A',
        'contact_phone' => '1',
    ]);

    $this->actingAs($otherUser)
        ->post(route('orders.confirm-receipt', $order))
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(Order::STATUS_AWAITING_RECEIPT);

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('确认签收');

    $this->actingAs($user)
        ->post(route('orders.confirm-receipt', $order))
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(Order::STATUS_FULFILLED)
        ->and($order->fresh()->fulfilled_at)->not->toBeNull();
});

it('lets customers hide orders while backoffice can still see the deletion flag', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'customer']);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'USER-DELETE-1',
        'status' => Order::STATUS_PENDING_PAYMENT,
        'payment_status' => Order::PAYMENT_PENDING,
        'subtotal_cents' => 100,
        'total_cents' => 100,
        'contact_name' => 'A',
        'contact_phone' => '1',
    ]);

    $this->actingAs($user)
        ->delete(route('orders.destroy', $order))
        ->assertRedirect(route('orders.index'));

    expect($order->fresh()->user_deleted_at)->not->toBeNull();

    $this->actingAs($user)
        ->get(route('orders.index'))
        ->assertOk()
        ->assertDontSee('USER-DELETE-1');

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertNotFound();

    $this->actingAs($admin)
        ->get("/admin/orders/{$order->id}/edit")
        ->assertOk()
        ->assertSee('用户已删除');
});

it('shows forced fulfillment reasons to customers', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'customer']);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'FORCED-FULFILL-1',
        'status' => Order::STATUS_AWAITING_RECEIPT,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 100,
        'total_cents' => 100,
        'contact_name' => 'A',
        'contact_phone' => '1',
    ]);

    app(OrderService::class)->fulfill($order, $admin, '线下已完成交付，按特殊原因直接完成。');

    expect($order->fresh()->status)->toBe(Order::STATUS_FULFILLED)
        ->and($order->fresh()->admin_note)->toBe('线下已完成交付，按特殊原因直接完成。');

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('后台处理备注')
        ->assertSee('线下已完成交付，按特殊原因直接完成。');
});

it('moves linked presale orders to shipped when an incoming product becomes in stock with tracking', function (): void {
    Mail::fake();

    $user = User::factory()->create(['role' => 'customer']);
    $carrier = ShippingCarrier::query()->create([
        'name' => '国际物流',
        'code' => 'INTL',
        'is_international' => true,
        'tracking_url_template' => 'https://example.test/{tracking_number}',
    ]);
    $category = Category::query()->create(['name' => '预售', 'slug' => 'presale', 'is_active' => true]);
    $presale = Product::query()->create([
        'category_id' => $category->id,
        'title' => '预售商品',
        'slug' => 'presale-product',
        'status' => Product::STATUS_PRESALE,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);
    $incoming = Product::query()->create([
        'category_id' => $category->id,
        'source_product_id' => $presale->id,
        'title' => '预售商品 - 进货中',
        'slug' => 'presale-product-incoming',
        'status' => Product::STATUS_INCOMING,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
        'incoming_quantity' => 10,
        'shipping_carrier_id' => $carrier->id,
        'tracking_number' => 'TRACK-1',
        'tracking_url' => 'https://example.test/TRACK-1',
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $presale->id,
        'sku' => 'PRE-1',
        'price_cents' => 1000,
        'stock' => 0,
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'PRE-ORDER-1',
        'status' => Order::STATUS_INCOMING,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 1000,
        'total_cents' => 1000,
        'contact_name' => 'A',
        'contact_phone' => '1',
        'contact_email' => 'buyer@example.com',
        'requires_shipping' => true,
    ]);
    OrderItem::query()->create([
        'order_id' => $order->id,
        'product_id' => $presale->id,
        'product_variant_id' => $variant->id,
        'product_title' => $presale->title,
        'product_status' => Product::STATUS_PRESALE,
        'variant_sku' => $variant->sku,
        'unit_price_cents' => 1000,
        'quantity' => 1,
        'line_total_cents' => 1000,
        'status' => Order::STATUS_INCOMING,
        'incoming_product_id' => $incoming->id,
    ]);

    $incoming->update(['status' => Product::STATUS_PUBLISHED]);

    expect($order->fresh()->status)->toBe(Order::STATUS_SHIPPED)
        ->and($order->fresh()->tracking_number)->toBe('TRACK-1')
        ->and($order->items()->first()->status)->toBe(Order::STATUS_SHIPPED);
});

it('lets customers create support tickets and admins view them in backoffice', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($user)
        ->post(route('support.store'), [
            'category' => 'complaint',
            'subject' => '物流问题',
            'message' => '希望客服帮我看一下。',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('support_tickets', [
        'user_id' => $user->id,
        'category' => 'complaint',
        'subject' => '物流问题',
        'status' => SupportTicket::STATUS_OPEN,
    ]);

    $this->actingAs($admin)
        ->get('/admin/support-tickets')
        ->assertOk()
        ->assertSee('物流问题')
        ->assertSee('客服/售后需求');
});
