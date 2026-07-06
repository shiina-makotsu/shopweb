<?php

use App\Models\Category;
use App\Models\AdminActivityLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentVerificationLog;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingCarrier;
use App\Models\SiteSetting;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentProofStorage;
use App\Support\OrderPrivacy;
use App\Support\Url;
use App\Filament\Resources\OrderResource\Pages\EditOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

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
        ->and($order->fresh()->userPaymentLabel())->toBe('付款凭证已提交')
        ->and($order->fresh()->userStatusLabel())->toBe('待发货');

    $this->assertDatabaseHas('payment_verification_logs', [
        'order_id' => $order->id,
        'user_id' => $user->id,
        'payment_proof_path' => $path,
        'expected_order_number' => 'PAY-1',
        'detected_order_number' => 'PAY-1',
        'expected_amount_cents' => 100,
        'auto_result' => PaymentVerificationLog::AUTO_PASSED,
    ]);

    app(OrderService::class)->rejectPayment($order);

    expect($order->fresh()->payment_status)->toBe(Order::PAYMENT_PENDING)
        ->and($order->fresh()->status)->toBe(Order::STATUS_PENDING_PAYMENT)
        ->and($order->fresh()->userPaymentLabel())->toBe('待支付');

    $this->assertDatabaseHas('payment_verification_logs', [
        'order_id' => $order->id,
        'manual_result' => PaymentVerificationLog::MANUAL_REJECTED,
    ]);
});

it('ignores duplicate payment proof submissions after the first accepted proof', function (): void {
    Storage::fake('payment_proofs');

    SiteSetting::query()->create([
        'site_name' => 'ShopWeb',
        'payment_auto_check_enabled' => true,
    ]);
    $user = User::factory()->create(['role' => 'customer']);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'PAY-IDEMPOTENT-1',
        'status' => Order::STATUS_PENDING_PAYMENT,
        'payment_status' => Order::PAYMENT_PENDING,
        'subtotal_cents' => 100,
        'total_cents' => 100,
        'contact_name' => 'A',
        'contact_phone' => '1',
    ]);

    $this->actingAs($user)
        ->post(route('orders.payment-proof', $order), [
            'payment_proof' => UploadedFile::fake()->image('proof-a.png'),
        ])
        ->assertRedirect()
        ->assertSessionHas('payment_success', true);

    $firstPath = $order->fresh()->payment_proof_path;

    $this->actingAs($user)
        ->post(route('orders.payment-proof', $order), [
            'payment_proof' => UploadedFile::fake()->image('proof-b.png'),
        ])
        ->assertRedirect()
        ->assertSessionHas('payment_success', true);

    expect($order->fresh()->payment_proof_path)->toBe($firstPath)
        ->and(PaymentVerificationLog::query()->where('order_id', $order->id)->count())->toBe(1);
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
    $category = Category::query()->create(['name' => 'Admin Order Category', 'slug' => 'admin-order-category', 'is_active' => true]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => 'Admin Visible Product',
        'slug' => 'admin-visible-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'ORDER-SKU-1',
        'spec_name' => 'Large Pack',
        'price_cents' => 100,
        'stock' => 5,
        'is_active' => true,
    ]);
    OrderItem::query()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_title' => $product->title,
        'product_status' => $product->status,
        'variant_sku' => $variant->sku,
        'variant_specs' => ['Size' => 'Large'],
        'unit_price_cents' => 100,
        'quantity' => 1,
        'line_total_cents' => 100,
        'status' => $order->status,
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
        ->and($order->fresh()->userPaymentLabel())->toBe('付款凭证已提交');

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('付款成功')
        ->assertSee('待发货')
        ->assertSee('付款凭证已提交')
        ->assertDontSee('待确认收款')
        ->assertSee('data-payment-redirect-url="/forum"', false)
        ->assertDontSee('待后台人工复核')
        ->assertDontSee('后台会继续人工复核');

    $this->actingAs($admin)
        ->get(route('admin.payment-proofs.show', $order))
        ->assertOk();

    $this->actingAs($admin)
        ->get("/admin/orders/{$order->id}/edit")
        ->assertOk()
        ->assertSee('订单商品')
        ->assertSee('Admin Visible Product')
        ->assertSee('ORDER-SKU-1')
        ->assertSee('Large Pack')
        ->assertSee('付款凭证图片')
        ->assertSee(Url::route('admin.payment-proofs.show', $order), false);

    $this->actingAs($admin)
        ->get('/admin/orders')
        ->assertOk()
        ->assertSee('data-shopweb-order-template', false)
        ->assertSee('更新物流')
        ->assertSee('电话')
        ->assertSee('邮箱')
        ->assertSee('Admin Visible Product');
});

it('does not count submitted payment proof orders as pending payment notices for customers', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'PAY-NOTICE-1',
        'status' => Order::STATUS_PENDING_PAYMENT,
        'payment_status' => Order::PAYMENT_SUBMITTED,
        'payment_text_proof' => 'red-packet-code',
        'payment_submitted_at' => now(),
        'subtotal_cents' => 9900,
        'total_cents' => 9900,
        'contact_name' => 'Notice User',
        'contact_phone' => '10086',
    ]);

    $this->actingAs($user)
        ->get(route('orders.index'))
        ->assertOk()
        ->assertSee('待发货')
        ->assertSee('付款凭证已提交')
        ->assertDontSee('待确认收款')
        ->assertDontSee('>待支付<', false);

    $this->actingAs($user)
        ->get(route('user.center'))
        ->assertOk()
        ->assertSee('待付款')
        ->assertSee('>0<', false);

    expect($order->fresh()->userPaymentLabel())->toBe('付款凭证已提交')
        ->and($order->fresh()->userStatusLabel())->toBe('待发货');
});

it('stores payment proof files into an ensured private directory', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'PAY-STORAGE-1',
        'status' => Order::STATUS_PENDING_PAYMENT,
        'payment_status' => Order::PAYMENT_PENDING,
        'subtotal_cents' => 100,
        'total_cents' => 100,
        'contact_name' => 'Storage User',
        'contact_phone' => '1',
    ]);

    $root = storage_path('framework/testing/payment-proofs-'.uniqid());
    Config::set('filesystems.disks.payment_proofs.root', $root);

    $path = app(PaymentProofStorage::class)->store($order, UploadedFile::fake()->image('proof.png'));

    expect($path)->toStartWith('PAY-STORAGE-1/')
        ->and(is_file($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path)))->toBeTrue()
        ->and(app(PaymentProofStorage::class)->exists($path))->toBeTrue();
});

it('falls back to database storage when the payment proof disk is unavailable', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'PAY-DB-FALLBACK-1',
        'status' => Order::STATUS_PENDING_PAYMENT,
        'payment_status' => Order::PAYMENT_PENDING,
        'subtotal_cents' => 100,
        'total_cents' => 100,
        'contact_name' => 'Db Fallback User',
        'contact_phone' => '1',
    ]);
    $file = UploadedFile::fake()->image('proof.png');
    $expectedContent = file_get_contents($file->getRealPath());

    Storage::partialMock()
        ->shouldReceive('disk')
        ->with('payment_proofs')
        ->andThrow(new RuntimeException('disk unavailable'));

    $path = app(PaymentProofStorage::class)->store($order, $file);

    expect($path)->toStartWith('db:')
        ->and(app(PaymentProofStorage::class)->exists($path))->toBeTrue()
        ->and(app(PaymentProofStorage::class)->response($path)->getContent())->toBe($expectedContent);
});

it('preloads payment codes and accepts red packet text as a manual payment fallback', function (): void {
    SiteSetting::query()->create([
        'site_name' => 'ShopWeb',
        'payment_qr_path' => 'payments/main.png',
        'payment_fallback_config' => [
            'fallback_qr_path' => 'payments/fallback.png',
            'friend_qr_path' => 'payments/friend.png',
            'password_red_packet_enabled' => true,
            'password_red_packet_note' => '支付失败时请提交口令红包。',
            'wallet_enabled' => true,
            'wallet_note' => '可联系客服充值钱包。',
            'support_enabled' => true,
        ],
    ]);

    $user = User::factory()->create(['role' => 'customer']);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'PAY-FALLBACK-1',
        'status' => Order::STATUS_PENDING_PAYMENT,
        'payment_status' => Order::PAYMENT_PENDING,
        'subtotal_cents' => 9900,
        'total_cents' => 9900,
        'contact_name' => 'Fallback User',
        'contact_phone' => '10086',
    ]);

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('rel="preload"', false)
        ->assertSee('/uploads/payments/main.png', false)
        ->assertSee('/uploads/payments/fallback.png', false)
        ->assertSee('/uploads/payments/friend.png', false)
        ->assertSee('data-payment-countdown', false)
        ->assertSee('支付受限时的备选方案')
        ->assertSee('支付失败时请提交口令红包。')
        ->assertSee('可联系客服充值钱包。');

    $this->actingAs($user)
        ->post(route('orders.payment-proof', $order), [
            'payment_text_proof' => '支付宝口令红包：枫桦林-114514',
        ])
        ->assertRedirect()
        ->assertSessionHas('payment_success', true);

    expect($order->fresh()->payment_text_proof)->toBe('支付宝口令红包：枫桦林-114514')
        ->and($order->fresh()->payment_status)->toBe(Order::PAYMENT_SUBMITTED)
        ->and($order->fresh()->payment_auto_check_status)->toBe(Order::AUTO_CHECK_PENDING);

    $this->assertDatabaseHas('payment_verification_logs', [
        'order_id' => $order->id,
        'payment_proof_path' => null,
        'auto_result' => Order::AUTO_CHECK_PENDING,
    ]);
});

it('keeps payment submission successful when verification logging is unavailable', function (): void {
    Schema::dropIfExists('payment_verification_logs');

    $user = User::factory()->create(['role' => 'customer']);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'PAY-LOG-MISSING-1',
        'status' => Order::STATUS_PENDING_PAYMENT,
        'payment_status' => Order::PAYMENT_PENDING,
        'subtotal_cents' => 9900,
        'total_cents' => 9900,
        'contact_name' => 'Missing Log User',
        'contact_phone' => '10086',
    ]);

    $this->actingAs($user)
        ->post(route('orders.payment-proof', $order), [
            'payment_text_proof' => 'red-packet-code-114514',
        ])
        ->assertRedirect()
        ->assertSessionHas('payment_success', true);

    expect($order->fresh()->payment_status)->toBe(Order::PAYMENT_SUBMITTED)
        ->and($order->fresh()->payment_text_proof)->toBe('red-packet-code-114514');
});

it('returns a validation error when payment proof submission is empty', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'PAY-EMPTY-1',
        'status' => Order::STATUS_PENDING_PAYMENT,
        'payment_status' => Order::PAYMENT_PENDING,
        'subtotal_cents' => 9900,
        'total_cents' => 9900,
        'contact_name' => 'Empty Proof User',
        'contact_phone' => '10086',
    ]);

    $this->actingAs($user)
        ->from(route('orders.show', $order))
        ->post(route('orders.payment-proof', $order), [])
        ->assertRedirect(route('orders.show', $order))
        ->assertSessionHasErrors('payment_proof');

    expect($order->fresh()->payment_status)->toBe(Order::PAYMENT_PENDING)
        ->and($order->fresh()->payment_proof_path)->toBeNull()
        ->and($order->fresh()->payment_text_proof)->toBeNull();
});

it('auto closes pending payment orders without proof after the configured timeout', function (): void {
    SiteSetting::query()->create([
        'site_name' => 'ShopWeb',
        'payment_pending_timeout_minutes' => 10,
    ]);
    $user = User::factory()->create(['role' => 'customer']);
    $expired = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'TIMEOUT-1',
        'status' => Order::STATUS_PENDING_PAYMENT,
        'payment_status' => Order::PAYMENT_PENDING,
        'subtotal_cents' => 1000,
        'total_cents' => 1000,
        'contact_name' => 'A',
        'contact_phone' => '1',
    ]);
    $expired->forceFill(['created_at' => now()->subMinutes(11), 'updated_at' => now()->subMinutes(11)])->save();
    $submitted = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'TIMEOUT-SUBMITTED',
        'status' => Order::STATUS_PENDING_PAYMENT,
        'payment_status' => Order::PAYMENT_SUBMITTED,
        'payment_text_proof' => 'red packet',
        'subtotal_cents' => 1000,
        'total_cents' => 1000,
        'contact_name' => 'A',
        'contact_phone' => '1',
    ]);
    $submitted->forceFill(['created_at' => now()->subMinutes(11), 'updated_at' => now()->subMinutes(11)])->save();
    $confirmed = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'TIMEOUT-CONFIRMED',
        'status' => Order::STATUS_PENDING_SHIPMENT,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 1000,
        'total_cents' => 1000,
        'contact_name' => 'A',
        'contact_phone' => '1',
    ]);
    $confirmed->forceFill(['created_at' => now()->subMinutes(11), 'updated_at' => now()->subMinutes(11)])->save();

    $this->artisan('shop:orders-expire-pending-payments')->assertSuccessful();

    expect($expired->fresh()->status)->toBe(Order::STATUS_CANCELLED)
        ->and($expired->fresh()->user_deleted_at)->not->toBeNull()
        ->and($submitted->fresh()->status)->toBe(Order::STATUS_PENDING_PAYMENT)
        ->and($confirmed->fresh()->payment_status)->toBe(Order::PAYMENT_CONFIRMED);

    $this->actingAs($user)
        ->get(route('orders.index'))
        ->assertOk()
        ->assertDontSee('TIMEOUT-1');

    expect(Order::query()->where('user_id', $user->id)->whereNull('user_deleted_at')->count())->toBe(2);
});

it('lets admins add shipping information from the expanded order row', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'customer']);
    $carrier = ShippingCarrier::query()->create([
        'name' => 'Row Carrier',
        'code' => 'ROW',
        'tracking_url_template' => 'https://track.example.test/{tracking_number}',
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'ROW-SHIP-1',
        'status' => Order::STATUS_PENDING_SHIPMENT,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 100,
        'total_cents' => 100,
        'contact_name' => 'A',
        'contact_phone' => '1',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.orders.quick-shipping', $order), [
            'shipping_carrier_id' => $carrier->id,
            'tracking_number' => 'ROW-TRACK-1',
            'admin_note' => '列表展开层补充物流',
        ])
        ->assertRedirect();

    $order->refresh();

    expect($order->shipping_carrier_id)->toBe($carrier->id)
        ->and($order->tracking_number)->toBe('ROW-TRACK-1')
        ->and($order->tracking_url)->toBe('https://track.example.test/ROW-TRACK-1');

    $this->assertDatabaseHas('admin_activity_logs', [
        'user_id' => $admin->id,
        'action' => 'order_quick_shipping_updated',
        'subject_type' => Order::class,
        'subject_id' => $order->id,
        'description' => '列表展开层补充物流',
    ]);
});

it('ships pending orders from the expanded row when only a tracking number is filled', function (): void {
    Mail::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'customer']);
    $category = Category::query()->create([
        'name' => 'Quick Ship Category',
        'slug' => 'quick-ship-category',
        'is_active' => true,
    ]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => 'Quick Ship Product',
        'slug' => 'quick-ship-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'QUICK-SHIP-1',
        'price_cents' => 1000,
        'stock' => 5,
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'ROW-SHIP-TRACKING-ONLY',
        'status' => Order::STATUS_PENDING_SHIPMENT,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 1000,
        'total_cents' => 1000,
        'contact_name' => 'A',
        'contact_phone' => '1',
        'requires_shipping' => true,
    ]);
    $item = OrderItem::query()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_title' => $product->title,
        'product_status' => Product::STATUS_PUBLISHED,
        'variant_sku' => $variant->sku,
        'unit_price_cents' => 1000,
        'quantity' => 1,
        'line_total_cents' => 1000,
        'status' => Order::STATUS_PENDING_SHIPMENT,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.orders.quick-shipping', $order), [
            'tracking_number' => 'TRACKING-ONLY-1',
            'admin_note' => 'ship from expanded row',
        ])
        ->assertRedirect();

    $order->refresh();

    expect($order->status)->toBe(Order::STATUS_AWAITING_RECEIPT)
        ->and($order->shipping_carrier_id)->toBeNull()
        ->and($order->tracking_number)->toBe('TRACKING-ONLY-1')
        ->and($order->shipped_at)->not->toBeNull()
        ->and($item->fresh()->status)->toBe(Order::STATUS_AWAITING_RECEIPT);

    $this->assertDatabaseHas('admin_activity_logs', [
        'user_id' => $admin->id,
        'action' => 'order_awaiting_receipt',
        'subject_type' => Order::class,
        'subject_id' => $order->id,
    ]);
});

it('uses an existing tracking number to mark an order awaiting receipt without reshipping inventory', function (): void {
    Mail::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'customer']);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'ROW-SHIP-EXISTING-TRACKING',
        'status' => Order::STATUS_PENDING_SHIPMENT,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 1000,
        'total_cents' => 1000,
        'contact_name' => 'A',
        'contact_phone' => '1',
        'requires_shipping' => true,
        'tracking_number' => 'EXISTING-TRACKING-1',
    ]);

    app(OrderService::class)->ship($order->fresh(), [], $admin);

    $order->refresh();

    expect($order->status)->toBe(Order::STATUS_AWAITING_RECEIPT)
        ->and($order->tracking_number)->toBe('EXISTING-TRACKING-1')
        ->and($order->shipped_at)->not->toBeNull();

    $this->assertDatabaseHas('admin_activity_logs', [
        'user_id' => $admin->id,
        'action' => 'order_awaiting_receipt',
        'subject_type' => Order::class,
        'subject_id' => $order->id,
    ]);
});

it('repairs missing quick shipping columns before saving logistics from the expanded order row', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'customer']);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'ROW-SHIP-REPAIR',
        'status' => Order::STATUS_PENDING_SHIPMENT,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 100,
        'total_cents' => 100,
        'contact_name' => 'A',
        'contact_phone' => '1',
    ]);

    Schema::table('orders', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->dropColumn(['tracking_number', 'tracking_url', 'shipped_at', 'delivered_at']);
    });

    $this->actingAs($admin)
        ->post(route('admin.orders.quick-shipping', $order), [
            'tracking_number' => 'ROW-REPAIRED-1',
            'tracking_url' => 'https://track.example.test/ROW-REPAIRED-1',
            'admin_note' => 'repair missing logistics schema',
        ])
        ->assertRedirect();

    expect(Schema::hasTable('shipping_carriers'))->toBeTrue()
        ->and(Schema::hasColumn('orders', 'tracking_number'))->toBeTrue()
        ->and(Schema::hasColumn('orders', 'tracking_url'))->toBeTrue()
        ->and(Schema::hasColumn('orders', 'shipped_at'))->toBeTrue()
        ->and(Schema::hasColumn('orders', 'delivered_at'))->toBeTrue()
        ->and($order->fresh()->tracking_number)->toBe('ROW-REPAIRED-1')
        ->and($order->fresh()->tracking_url)->toBe('https://track.example.test/ROW-REPAIRED-1');
});

it('lets admins quick edit products and sku values from the expanded product row', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::query()->create([
        'name' => 'Quick Product Category',
        'slug' => 'quick-product-category',
        'is_active' => true,
    ]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => 'Quick Product',
        'slug' => 'quick-product',
        'status' => Product::STATUS_DRAFT,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
        'is_featured' => false,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'QUICK-SKU-1',
        'spec_name' => 'Old Spec',
        'price_cents' => 1000,
        'stock' => 2,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.products.quick-update', $product), [
            'title' => 'Quick Product Updated',
            'status' => Product::STATUS_PRESALE,
            'is_featured' => '1',
            'variants' => [
                $variant->id => [
                    'id' => $variant->id,
                    'spec_name' => 'New Spec',
                    'price_cents' => '25.50',
                    'stock' => 12,
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('status', '商品已更新。');

    $product->refresh();
    $variant->refresh();

    expect($product->title)->toBe('Quick Product Updated')
        ->and($product->status)->toBe(Product::STATUS_PRESALE)
        ->and($product->is_featured)->toBeTrue()
        ->and($variant->spec_name)->toBe('New Spec')
        ->and($variant->price_cents)->toBe(2550)
        ->and($variant->stock)->toBe(12);

    $this->assertDatabaseHas('admin_activity_logs', [
        'user_id' => $admin->id,
        'action' => 'product_quick_updated',
        'subject_type' => Product::class,
        'subject_id' => $product->id,
        'description' => '后台列表快速更新商品',
    ]);
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
        ->assertSee('确认收货');

    $this->actingAs($user)
        ->post(route('orders.confirm-receipt', $order))
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(Order::STATUS_FULFILLED)
        ->and($order->fresh()->fulfilled_at)->not->toBeNull();
});

it('lets admins edit order details only with a manual update note and records the change', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'customer']);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'ADMIN-EDIT-1',
        'status' => Order::STATUS_PENDING_PAYMENT,
        'payment_status' => Order::PAYMENT_PENDING,
        'subtotal_cents' => 100,
        'total_cents' => 100,
        'contact_name' => 'Old Name',
        'contact_phone' => '10086',
        'shipping_address' => 'Old Address',
    ]);

    $this->actingAs($admin);

    Livewire::test(EditOrder::class, ['record' => $order->id])
        ->fillForm([
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_status' => Order::PAYMENT_PENDING,
            'contact_name' => 'New Name',
            'contact_phone' => '10086',
            'contact_email' => null,
            'shipping_address' => 'Old Address',
            'shipping_province' => null,
            'shipping_city' => null,
            'shipping_district' => null,
            'shipping_street' => null,
            'shipping_detail' => null,
            'shipping_carrier_id' => null,
            'tracking_number' => null,
            'tracking_url' => null,
            'digital_delivery_content' => null,
            'digital_delivery_code' => null,
            'admin_note' => null,
            'manual_update_note' => '',
        ])
        ->call('save')
        ->assertHasFormErrors(['manual_update_note']);

    expect($order->fresh()->contact_name)->toBe('Old Name');

    Livewire::test(EditOrder::class, ['record' => $order->id])
        ->fillForm([
            'status' => Order::STATUS_PENDING_SHIPMENT,
            'payment_status' => Order::PAYMENT_CONFIRMED,
            'contact_name' => 'New Name',
            'contact_phone' => '10086',
            'contact_email' => null,
            'shipping_address' => 'New Address',
            'shipping_province' => null,
            'shipping_city' => null,
            'shipping_district' => null,
            'shipping_street' => null,
            'shipping_detail' => null,
            'shipping_carrier_id' => null,
            'tracking_number' => null,
            'tracking_url' => null,
            'digital_delivery_content' => null,
            'digital_delivery_code' => null,
            'admin_note' => '后台已协助修正',
            'manual_update_note' => '用户下单信息填错，后台按客服确认内容修正',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $order->refresh();

    expect($order->status)->toBe(Order::STATUS_PENDING_SHIPMENT)
        ->and($order->payment_status)->toBe(Order::PAYMENT_CONFIRMED)
        ->and($order->contact_name)->toBe('New Name')
        ->and($order->shipping_address)->toBe('New Address');

    $log = AdminActivityLog::query()
        ->where('action', 'order_manually_updated')
        ->where('subject_id', $order->id)
        ->firstOrFail();

    expect($log->description)->toBe('用户下单信息填错，后台按客服确认内容修正')
        ->and(data_get($log->properties, 'changes.contact_name.old'))->toBe('Old Name')
        ->and(data_get($log->properties, 'changes.contact_name.new'))->toBe('New Name')
        ->and($log->actionLabel())->toBe('后台修改订单');
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

it('moves linked presale orders to pending shipment when an incoming product becomes in stock', function (): void {
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

    expect($order->fresh()->status)->toBe(Order::STATUS_PENDING_SHIPMENT)
        ->and($order->fresh()->tracking_number)->toBeNull()
        ->and($order->items()->first()->status)->toBe(Order::STATUS_PENDING_SHIPMENT);

    app(OrderService::class)->ship($order->fresh(), [
        'shipping_carrier_id' => $carrier->id,
        'tracking_number' => 'TRACK-USER-1',
    ]);

    expect($order->fresh()->status)->toBe(Order::STATUS_AWAITING_RECEIPT)
        ->and($order->fresh()->tracking_number)->toBe('TRACK-USER-1')
        ->and($order->items()->first()->status)->toBe(Order::STATUS_AWAITING_RECEIPT);
});

it('ships logistics orders when only a tracking number is filled', function (): void {
    Mail::fake();

    $user = User::factory()->create(['role' => 'customer']);
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::query()->create(['name' => 'Logistics', 'slug' => 'logistics', 'is_active' => true]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => 'Logistics Product',
        'slug' => 'logistics-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'LOG-1',
        'price_cents' => 1000,
        'stock' => 5,
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'LOG-SHIP-ONLY-TRACKING',
        'status' => Order::STATUS_PENDING_SHIPMENT,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 1000,
        'total_cents' => 1000,
        'contact_name' => 'A',
        'contact_phone' => '1',
        'requires_shipping' => true,
    ]);
    OrderItem::query()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_title' => $product->title,
        'product_status' => $product->status,
        'variant_sku' => $variant->sku,
        'unit_price_cents' => 1000,
        'quantity' => 1,
        'line_total_cents' => 1000,
        'status' => Order::STATUS_PENDING_SHIPMENT,
    ]);

    app(OrderService::class)->ship($order->fresh(), [
        'tracking_number' => 'ONLY-TRACK-1',
        'digital_delivery_content' => 'should be ignored for logistics',
    ], $admin);

    $order->refresh();

    expect($order->status)->toBe(Order::STATUS_AWAITING_RECEIPT)
        ->and($order->tracking_number)->toBe('ONLY-TRACK-1')
        ->and($order->shipping_carrier_id)->toBeNull()
        ->and($order->digital_delivery_content)->toBeNull()
        ->and($order->items()->first()->status)->toBe(Order::STATUS_AWAITING_RECEIPT);
});

it('uses online delivery fields only for online delivery orders', function (): void {
    Mail::fake();

    $user = User::factory()->create(['role' => 'customer']);
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::query()->create(['name' => 'Online', 'slug' => 'online', 'is_active' => true]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => 'Online Product',
        'slug' => 'online-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'ONLINE-1',
        'price_cents' => 1000,
        'stock' => 0,
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'ONLINE-SHIP-1',
        'status' => Order::STATUS_PAID,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 1000,
        'total_cents' => 1000,
        'contact_name' => 'A',
        'contact_phone' => '1',
        'requires_shipping' => false,
    ]);
    OrderItem::query()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_title' => $product->title,
        'product_status' => $product->status,
        'variant_sku' => $variant->sku,
        'unit_price_cents' => 1000,
        'quantity' => 1,
        'line_total_cents' => 1000,
        'status' => Order::STATUS_PAID,
    ]);

    app(OrderService::class)->ship($order->fresh(), [
        'tracking_number' => 'should-not-be-used',
        'digital_delivery_content' => 'Download from account center.',
        'digital_delivery_code' => 'CODE-1',
    ], $admin);

    $order->refresh();

    expect($order->status)->toBe(Order::STATUS_AWAITING_RECEIPT)
        ->and($order->tracking_number)->toBeNull()
        ->and($order->digital_delivery_content)->toBe('Download from account center.')
        ->and($order->digital_delivery_code)->toBe('CODE-1')
        ->and($order->digital_delivery_sent_at)->not->toBeNull()
        ->and($order->items()->first()->status)->toBe(Order::STATUS_AWAITING_RECEIPT);
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
        ->assertSee('客服工单');
});
