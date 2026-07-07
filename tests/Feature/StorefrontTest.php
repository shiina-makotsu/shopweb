<?php

use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\FlashSaleCampaignResource\Pages\CreateFlashSaleCampaign;
use App\Filament\Pages\HomeContentPage;
use App\Filament\Pages\ProductDiscountPage;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\FlashSale;
use App\Models\FlashSaleCampaign;
use App\Models\FlashSaleCampaignItem;
use App\Models\FriendLink;
use App\Models\MediaAsset;
use App\Models\NavigationMenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductComment;
use App\Models\ProductMedia;
use App\Models\ProductTag;
use App\Models\ProductVariant;
use App\Models\ReferralRewardRule;
use App\Models\ShippingCarrier;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\UserCoupon;
use App\Models\WalletRechargeOption;
use App\Models\WalletRedeemCode;
use App\Models\WalletTransaction;
use App\Models\Warehouse;
use App\Models\WarehouseShippingRate;
use App\Models\WarehouseStock;
use App\Services\FlashSaleCampaignService;
use App\Services\CouponService;
use App\Services\OrderService;
use App\Services\StorefrontCache;
use App\Support\Money;
use App\Support\PageMenuPublication;
use App\Support\Url;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('formats sku specification labels with value before name', function (): void {
    $variant = new ProductVariant([
        'spec_name' => '白色常规装',
        'specs' => ['颜色' => '白色', '尺码' => 'M'],
    ]);

    expect(ProductVariant::specsLabel(['颜色' => '白色', '尺码' => 'M']))->toBe('白色颜色 * M尺码')
        ->and(ProductVariant::specsLabel(['' => '标准']))->toBe('标准规格')
        ->and(ProductVariant::specsLabel([]))->toBe('默认规格')
        ->and($variant->displayName())->toBe('白色常规装')
        ->and($variant->detailSpecLabel())->toBe('白色颜色 * M尺码');
});

it('allows identical product slugs in different status routes', function (): void {
    $category = Category::query()->create([
        'name' => '演示分类',
        'slug' => 'demo-category',
        'is_active' => true,
    ]);

    $published = Product::query()->create([
        'category_id' => $category->id,
        'title' => '现货同名商品',
        'slug' => 'same-item',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);

    $presale = Product::query()->create([
        'category_id' => $category->id,
        'title' => '预售同名商品',
        'slug' => 'same-item',
        'status' => Product::STATUS_PRESALE,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);

    ProductVariant::query()->create([
        'product_id' => $published->id,
        'sku' => 'SAME-PUBLISHED',
        'price_cents' => 1000,
        'stock' => 5,
        'is_active' => true,
    ]);

    ProductVariant::query()->create([
        'product_id' => $presale->id,
        'sku' => 'SAME-PRESALE',
        'price_cents' => 2000,
        'stock' => 0,
        'is_active' => true,
    ]);

    $this->get(route('products.status.show', $published->showRouteParameters()))
        ->assertOk()
        ->assertSee('现货同名商品')
        ->assertDontSee('预售同名商品');

    $this->get(route('products.status.show', $presale->showRouteParameters()))
        ->assertOk()
        ->assertSee('预售同名商品')
        ->assertDontSee('现货同名商品');
});

it('parses recipient phone and compact chinese address without leaking them into detail', function (): void {
    $parsed = \App\Support\ChinaRegions::parseAddress('狗狗 12365478945 中国广东广州荔湾区花地大道中市');

    expect($parsed['name'] ?? null)->toBe('狗狗')
        ->and($parsed['phone'] ?? null)->toBe('12365478945')
        ->and($parsed['country'] ?? null)->toBe('中国')
        ->and($parsed['province'] ?? null)->toBe('广东')
        ->and($parsed['city'] ?? null)->toBe('广州市')
        ->and($parsed['district'] ?? null)->toBe('荔湾区')
        ->and($parsed['street'] ?? null)->toBe('花地大道')
        ->and($parsed['detail'] ?? null)->toBe('中市');
});

it('allows a customer to add a sku to cart and create an order', function (): void {
    $this->seed();

    $user = User::query()->where('role', 'customer')->first()
        ?? User::factory()->create(['role' => 'customer']);

    $variant = ProductVariant::query()->firstOrFail();
    $variant->product->update(['fulfillment_type' => Product::FULFILLMENT_IN_PERSON]);

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

    $order = Order::query()->where('user_id', $user->id)->firstOrFail();
    app(OrderService::class)->confirmPayment($order);

    expect($variant->fresh()->stock)->toBe($variant->stock - 2);
});

it('lets customers redeem wallet codes and recharge wallet through payment confirmation', function (): void {
    $user = User::factory()->create(['role' => 'customer', 'wallet_balance_cents' => 0]);

    $code = WalletRedeemCode::query()->create([
        'code' => 'WALLET100',
        'name' => 'Wallet 100',
        'amount_cents' => 10000,
        'usage_limit' => 2,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('user.wallet.redeem'), ['wallet_code' => 'wallet100'])
        ->assertRedirect();

    expect($user->fresh()->wallet_balance_cents)->toBe(10000)
        ->and($code->fresh()->redeemed_count)->toBe(1);

    $this->assertDatabaseHas('wallet_transactions', [
        'user_id' => $user->id,
        'wallet_redeem_code_id' => $code->id,
        'type' => WalletTransaction::TYPE_CREDIT,
        'amount_cents' => 10000,
        'source' => WalletTransaction::SOURCE_REDEEM_CODE,
    ]);

    $this->actingAs($user)
        ->post(route('user.wallet.recharge'), ['wallet_recharge_amount' => '25.50'])
        ->assertRedirect();

    $order = Order::query()->whereBelongsTo($user)->latest('id')->firstOrFail();

    expect($order->isWalletRecharge())->toBeTrue()
        ->and($order->wallet_recharge_cents)->toBe(2550)
        ->and($order->total_cents)->toBe(2550);

    app(OrderService::class)->confirmPayment($order);
    app(OrderService::class)->confirmPayment($order->fresh());

    expect($user->fresh()->wallet_balance_cents)->toBe(12550)
        ->and($order->fresh()->payment_status)->toBe(Order::PAYMENT_CONFIRMED)
        ->and($order->fresh()->status)->toBe(Order::STATUS_FULFILLED)
        ->and(WalletTransaction::query()
            ->where('order_id', $order->id)
            ->where('source', WalletTransaction::SOURCE_WALLET_RECHARGE)
            ->count())->toBe(1);
});

it('automatically uses wallet balance before the selected checkout method', function (): void {
    $user = User::factory()->create(['role' => 'customer', 'wallet_balance_cents' => 3000]);
    $category = Category::query()->create(['name' => 'Wallet', 'slug' => 'wallet', 'is_active' => true]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => 'Wallet product',
        'slug' => 'wallet-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_IN_PERSON,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'WALLET-SKU',
        'price_cents' => 10000,
        'stock' => 5,
        'is_active' => true,
    ]);

    $this->post(route('cart.items.store'), [
        'variant_id' => $variant->id,
        'quantity' => 1,
    ])->assertRedirect(route('cart.show'));

    $this->actingAs($user)->post(route('checkout.store'), [
        'contact_name' => 'Wallet User',
        'contact_phone' => '13800000000',
        'contact_email' => 'wallet@example.com',
    ])->assertRedirect();

    $order = Order::query()->whereBelongsTo($user)->latest('id')->firstOrFail();

    expect($order->payment_method)->toBe(Order::PAYMENT_METHOD_QR_CODE)
        ->and($order->wallet_payment_cents)->toBe(3000)
        ->and($order->total_cents)->toBe(7000)
        ->and($order->paymentTotalCents())->toBe(10000)
        ->and($order->walletPaymentCents())->toBe(3000)
        ->and($order->remainingPaymentCents())->toBe(7000)
        ->and($user->fresh()->wallet_balance_cents)->toBe(0);

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('付款总金额')
        ->assertSee('钱包支付金额')
        ->assertSee('待支付金额')
        ->assertSee('¥100.00')
        ->assertSee('¥30.00')
        ->assertSee('¥70.00');

    $this->assertDatabaseHas('wallet_transactions', [
        'user_id' => $user->id,
        'order_id' => $order->id,
        'type' => WalletTransaction::TYPE_DEBIT,
        'amount_cents' => -3000,
        'source' => WalletTransaction::SOURCE_ORDER_PAYMENT,
    ]);
});

it('lets customers choose full wallet payment or keep their wallet balance for other methods', function (): void {
    $category = Category::query()->create(['name' => 'Wallet Choice', 'slug' => 'wallet-choice', 'is_active' => true]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => 'Wallet choice product',
        'slug' => 'wallet-choice-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_IN_PERSON,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'WALLET-CHOICE',
        'price_cents' => 10000,
        'stock' => 5,
        'is_active' => true,
    ]);

    $qrUser = User::factory()->create(['role' => 'customer', 'wallet_balance_cents' => 15000]);

    $this->actingAs($qrUser)->post(route('cart.items.store'), ['variant_id' => $variant->id, 'quantity' => 1]);
    $this->actingAs($qrUser)
        ->get(route('checkout.create'))
        ->assertOk()
        ->assertSee('value="'.Order::PAYMENT_METHOD_WALLET.'"', false)
        ->assertSee('钱包余额支付');

    $this->actingAs($qrUser)->post(route('checkout.store'), [
        'contact_name' => 'Wallet Choice QR',
        'contact_phone' => '13800000000',
        'payment_method' => Order::PAYMENT_METHOD_QR_CODE,
    ])->assertRedirect();

    $qrOrder = Order::query()->whereBelongsTo($qrUser)->latest('id')->firstOrFail();

    expect($qrOrder->payment_method)->toBe(Order::PAYMENT_METHOD_QR_CODE)
        ->and($qrOrder->wallet_payment_cents)->toBe(0)
        ->and($qrOrder->total_cents)->toBe(10000)
        ->and($qrOrder->payment_status)->toBe(Order::PAYMENT_PENDING)
        ->and($qrUser->fresh()->wallet_balance_cents)->toBe(15000);

    $walletUser = User::factory()->create(['role' => 'customer', 'wallet_balance_cents' => 15000]);

    $this->actingAs($walletUser)->post(route('cart.items.store'), ['variant_id' => $variant->id, 'quantity' => 1]);
    $this->actingAs($walletUser)->post(route('checkout.store'), [
        'contact_name' => 'Wallet Choice Full',
        'contact_phone' => '13800000000',
        'payment_method' => Order::PAYMENT_METHOD_WALLET,
    ])->assertRedirect();

    $walletOrder = Order::query()->whereBelongsTo($walletUser)->latest('id')->firstOrFail();

    expect($walletOrder->payment_method)->toBe(Order::PAYMENT_METHOD_WALLET)
        ->and($walletOrder->wallet_payment_cents)->toBe(10000)
        ->and($walletOrder->total_cents)->toBe(0)
        ->and($walletOrder->payment_status)->toBe(Order::PAYMENT_CONFIRMED)
        ->and($walletUser->fresh()->wallet_balance_cents)->toBe(5000);
});

it('refunds wallet-paid amounts when an order is cancelled', function (): void {
    $user = User::factory()->create(['role' => 'customer', 'wallet_balance_cents' => 3000]);
    $category = Category::query()->create(['name' => 'Wallet Refund', 'slug' => 'wallet-refund', 'is_active' => true]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => 'Wallet refund product',
        'slug' => 'wallet-refund-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_IN_PERSON,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'WALLET-REFUND',
        'price_cents' => 5000,
        'stock' => 5,
        'is_active' => true,
    ]);

    $this->post(route('cart.items.store'), ['variant_id' => $variant->id, 'quantity' => 1]);
    $this->actingAs($user)->post(route('checkout.store'), [
        'contact_name' => 'Wallet Refund User',
        'contact_phone' => '13800000000',
    ])->assertRedirect();

    $order = Order::query()->whereBelongsTo($user)->firstOrFail();
    app(OrderService::class)->cancel($order, $user);

    expect($user->fresh()->wallet_balance_cents)->toBe(3000)
        ->and($order->fresh()->status)->toBe(Order::STATUS_CANCELLED);

    $this->assertDatabaseHas('wallet_transactions', [
        'user_id' => $user->id,
        'order_id' => $order->id,
        'type' => WalletTransaction::TYPE_CREDIT,
        'amount_cents' => 3000,
        'source' => WalletTransaction::SOURCE_ORDER_REFUND,
    ]);
});

it('shows paypal checkout only after a paypal receiver email is configured', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $category = Category::query()->create(['name' => 'Paypal', 'slug' => 'paypal', 'is_active' => true]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => 'Paypal product',
        'slug' => 'paypal-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_IN_PERSON,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'PAYPAL-SKU',
        'price_cents' => 1200,
        'stock' => 5,
        'is_active' => true,
    ]);

    SiteSetting::query()->create(['site_name' => 'ShopWeb']);

    $this->actingAs($user)->post(route('cart.items.store'), [
        'variant_id' => $variant->id,
        'quantity' => 1,
    ])->assertRedirect(route('cart.show'));

    $this->actingAs($user)
        ->get(route('checkout.create'))
        ->assertOk()
        ->assertDontSee('PayPal 支付');

    $this->actingAs($user)
        ->post(route('checkout.store'), [
            'contact_name' => 'Paypal User',
            'contact_phone' => '13800000000',
            'payment_method' => Order::PAYMENT_METHOD_PAYPAL,
        ])
        ->assertSessionHasErrors('payment_method');

    SiteSetting::query()->first()->update([
        'payment_gateway_config' => ['paypal_email' => 'seller@example.com'],
        'payment_fallback_config' => [
            'password_red_packet_enabled' => true,
            'password_red_packet_note' => 'Use a red packet when PayPal is not convenient.',
        ],
    ]);
    Cache::flush();

    $this->actingAs($user)
        ->get(route('checkout.create'))
        ->assertOk()
        ->assertSee('PayPal 支付')
        ->assertSee('seller@example.com');

    $this->actingAs($user)
        ->post(route('checkout.store'), [
            'contact_name' => 'Paypal User',
            'contact_phone' => '13800000000',
            'payment_method' => Order::PAYMENT_METHOD_PAYPAL,
        ])
        ->assertRedirect();

    $order = Order::query()->whereBelongsTo($user)->latest('id')->firstOrFail();

    expect($order->payment_method)->toBe(Order::PAYMENT_METHOD_PAYPAL);

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('PayPal 收款邮箱')
        ->assertSee('seller@example.com')
        ->assertSee('口令红包付款')
        ->assertSee('Use a red packet when PayPal is not convenient.')
        ->assertSee('name="payment_proof"', false)
        ->assertSee('name="payment_text_proof"', false);
});

it('attributes referral registrations and issues configured coupon and wallet rewards', function (): void {
    $inviter = User::factory()->create(['role' => 'customer']);
    $inviter->ensureReferralCode();
    $coupon = Coupon::query()->create([
        'code' => 'INVITE10',
        'name' => 'Invite reward',
        'type' => Coupon::TYPE_FIXED,
        'value' => 1000,
        'scope' => Coupon::SCOPE_GLOBAL,
        'minimum_order_cents' => 0,
        'is_active' => true,
    ]);
    ReferralRewardRule::query()->create([
        'name' => 'Invite reward',
        'trigger_events' => [ReferralRewardRule::EVENT_REFERRAL_REGISTERED],
        'coupon_id' => $coupon->id,
        'wallet_amount_cents' => 500,
        'is_active' => true,
    ]);

    $this->get(route('home', ['invite' => $inviter->referral_code]))
        ->assertOk()
        ->assertSessionHas('referral_code', $inviter->referral_code);

    $this->withSession([
        'register_captcha_answer' => 4,
        'referral_code' => $inviter->referral_code,
    ])->post(route('register'), [
        'public_id' => 'invitee_user',
        'name' => 'Invitee',
        'email' => 'invitee@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'captcha_answer' => 4,
    ])->assertRedirect(route('home'));

    $invitee = User::query()->where('email', 'invitee@example.com')->firstOrFail();

    expect($invitee->referred_by_user_id)->toBe($inviter->id)
        ->and($inviter->fresh()->wallet_balance_cents)->toBe(500);

    $this->assertDatabaseHas('user_coupons', [
        'user_id' => $inviter->id,
        'coupon_id' => $coupon->id,
        'source' => UserCoupon::SOURCE_REFERRAL,
    ]);
    $this->assertDatabaseHas('wallet_transactions', [
        'user_id' => $inviter->id,
        'amount_cents' => 500,
        'source' => WalletTransaction::SOURCE_REFERRAL,
    ]);
    $this->assertDatabaseHas('event_reward_grants', [
        'user_id' => $inviter->id,
        'event' => ReferralRewardRule::EVENT_REFERRAL_REGISTERED,
        'subject_type' => User::class,
        'subject_id' => $invitee->id,
    ]);
});

it('grants configured event rewards for product purchases and wallet payments once', function (): void {
    $user = User::factory()->create(['role' => 'customer', 'wallet_balance_cents' => 500]);
    $category = Category::query()->create(['name' => 'Reward Product Category', 'slug' => 'reward-product-category', 'is_active' => true]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => 'Reward Product',
        'slug' => 'reward-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_IN_PERSON,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'REWARD-SKU',
        'price_cents' => 1000,
        'stock' => 5,
        'is_active' => true,
    ]);
    $coupon = Coupon::query()->create([
        'code' => 'EVENT10',
        'name' => 'Event reward',
        'type' => Coupon::TYPE_FIXED,
        'value' => 100,
        'scope' => Coupon::SCOPE_GLOBAL,
        'minimum_order_cents' => 0,
        'is_active' => true,
    ]);

    ReferralRewardRule::query()->create([
        'name' => 'Product event reward',
        'trigger_events' => [ReferralRewardRule::EVENT_ORDER_PAID_PRODUCT],
        'product_ids' => [$product->id],
        'coupon_id' => $coupon->id,
        'wallet_amount_cents' => 200,
        'is_active' => true,
    ]);
    ReferralRewardRule::query()->create([
        'name' => 'Wallet event reward',
        'trigger_events' => [ReferralRewardRule::EVENT_WALLET_PAYMENT_USED],
        'wallet_amount_cents' => 300,
        'is_active' => true,
    ]);

    $this->post(route('cart.items.store'), ['variant_id' => $variant->id, 'quantity' => 1]);
    $this->actingAs($user)->post(route('checkout.store'), [
        'contact_name' => 'Event Reward User',
        'contact_phone' => '13800000000',
        'payment_method' => Order::PAYMENT_METHOD_QR_CODE,
    ])->assertRedirect();

    $order = Order::query()->whereBelongsTo($user)->firstOrFail();

    expect($order->wallet_payment_cents)->toBe(500)
        ->and($order->total_cents)->toBe(500)
        ->and($user->fresh()->wallet_balance_cents)->toBe(0);

    app(OrderService::class)->confirmPayment($order, $user);
    app(OrderService::class)->confirmPayment($order->fresh(), $user);

    expect($user->fresh()->wallet_balance_cents)->toBe(500);

    $this->assertDatabaseHas('user_coupons', [
        'user_id' => $user->id,
        'coupon_id' => $coupon->id,
        'source' => UserCoupon::SOURCE_EVENT_REWARD,
    ]);
    $this->assertDatabaseCount('event_reward_grants', 2);
});

it('renders referral copy links with an absolute local-aware host', function (): void {
    $user = User::factory()->create(['role' => 'customer', 'public_id' => 'local_inviter']);
    $user->ensureReferralCode();

    $this->actingAs($user)
        ->get('http://127.0.0.1/user/invitations')
        ->assertOk()
        ->assertSee('data-absolute-url', false)
        ->assertSee('value="http://localhost/?invite='.$user->referral_code.'"', false)
        ->assertDontSee('value="/?invite='.$user->referral_code.'"', false);
});

it('creates wallet recharge orders from configured recharge options', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $option = WalletRechargeOption::query()->create([
        'name' => '充值 10 送 2',
        'currency_code' => 'CNY',
        'currency_unit' => 'yuan',
        'amount_cents' => 1000,
        'discount_percent' => 90,
        'bonus_cents' => 200,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('user.section', 'wallet'))
        ->assertOk()
        ->assertSee('充值 10 送 2')
        ->assertSee('按 90% 付款');

    $this->actingAs($user)
        ->post(route('user.wallet.recharge-option'), [
            'wallet_recharge_option_id' => $option->id,
        ])
        ->assertRedirect();

    $order = Order::query()->whereBelongsTo($user)->latest('id')->firstOrFail();

    expect($order->total_cents)->toBe(900)
        ->and($order->wallet_recharge_cents)->toBe(1200)
        ->and($order->discount_cents)->toBe(100);
});

it('issues generated standard coupons after a configured wallet recharge is confirmed once', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $category = Category::query()->create(['name' => 'Recharge Coupon', 'slug' => 'recharge-coupon', 'is_active' => true]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => 'Recharge coupon product',
        'slug' => 'recharge-coupon-product',
        'status' => Product::STATUS_PUBLISHED,
    ]);
    $option = WalletRechargeOption::query()->create([
        'name' => '充值 50 赠券',
        'currency_code' => 'CNY',
        'currency_unit' => 'yuan',
        'amount_cents' => 5000,
        'bonus_cents' => 0,
        'is_active' => true,
        'coupon_reward_enabled' => true,
        'coupon_reward_rules' => [
            [
                'name' => 'fixed reward',
                'currency_code' => 'CNY',
                'currency_unit' => 'yuan',
                'type' => Coupon::TYPE_FIXED,
                'value' => 800,
                'valid_days' => 30,
                'scope' => Coupon::SCOPE_PRODUCT,
                'product_ids' => [$product->id],
                'minimum_order_cents' => 3000,
                'quantity' => 2,
                'usage_limit' => 1,
            ],
            [
                'name' => 'percent reward',
                'currency_code' => 'CNY',
                'currency_unit' => 'yuan',
                'type' => Coupon::TYPE_PERCENT,
                'value' => 90,
                'valid_days' => null,
                'scope' => Coupon::SCOPE_GLOBAL,
                'product_ids' => [],
                'minimum_order_cents' => 10000,
                'quantity' => 1,
                'usage_limit' => 3,
            ],
        ],
    ]);

    $this->actingAs($user)
        ->post(route('user.wallet.recharge-option'), [
            'wallet_recharge_option_id' => $option->id,
        ])
        ->assertRedirect();

    $order = Order::query()->whereBelongsTo($user)->latest('id')->firstOrFail();

    expect($order->wallet_recharge_option_id)->toBe($option->id);

    app(OrderService::class)->confirmPayment($order);
    app(OrderService::class)->confirmPayment($order->fresh());

    expect($user->fresh()->wallet_balance_cents)->toBe(5000)
        ->and(UserCoupon::query()->whereBelongsTo($user)->where('source', UserCoupon::SOURCE_WALLET_RECHARGE)->count())->toBe(3)
        ->and(WalletTransaction::query()
            ->where('order_id', $order->id)
            ->where('source', WalletTransaction::SOURCE_WALLET_RECHARGE)
            ->count())->toBe(1);

    $issuedCoupons = Coupon::query()
        ->whereIn('id', UserCoupon::query()
            ->whereBelongsTo($user)
            ->where('source', UserCoupon::SOURCE_WALLET_RECHARGE)
            ->pluck('coupon_id'))
        ->with('products')
        ->get();

    expect($issuedCoupons)->toHaveCount(3)
        ->and($issuedCoupons->pluck('code')->unique())->toHaveCount(3);

    $fixedCoupons = $issuedCoupons->where('type', Coupon::TYPE_FIXED)->values();
    $percentCoupon = $issuedCoupons->firstWhere('type', Coupon::TYPE_PERCENT);

    expect($fixedCoupons)->toHaveCount(2)
        ->and($percentCoupon)->not->toBeNull();

    foreach ($fixedCoupons as $coupon) {
        expect($coupon->name)->toContain('fixed reward')
            ->and($coupon->value)->toBe(800)
            ->and($coupon->scope)->toBe(Coupon::SCOPE_PRODUCT)
            ->and($coupon->is_stackable)->toBeFalse()
            ->and($coupon->minimum_order_cents)->toBe(3000)
            ->and($coupon->usage_limit)->toBe(1)
            ->and($coupon->ends_at)->not->toBeNull()
            ->and($coupon->products->pluck('id')->all())->toBe([$product->id]);
    }

    expect($percentCoupon->name)->toContain('percent reward')
        ->and($percentCoupon->value)->toBe(90)
        ->and($percentCoupon->scope)->toBe(Coupon::SCOPE_GLOBAL)
        ->and($percentCoupon->minimum_order_cents)->toBe(10000)
        ->and($percentCoupon->usage_limit)->toBe(3)
        ->and($percentCoupon->ends_at)->toBeNull();
});

it('matches custom wallet recharge amounts to configured payable option rewards', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $option = WalletRechargeOption::query()->create([
        'name' => '自定义命中 100',
        'currency_code' => 'CNY',
        'currency_unit' => 'yuan',
        'amount_cents' => 10000,
        'discount_percent' => 80,
        'bonus_cents' => 1500,
        'is_active' => true,
        'coupon_reward_enabled' => true,
        'coupon_reward_rules' => [
            [
                'name' => 'custom match reward',
                'type' => Coupon::TYPE_FIXED,
                'value' => 500,
                'scope' => Coupon::SCOPE_GLOBAL,
                'quantity' => 1,
                'usage_limit' => 1,
            ],
        ],
    ]);

    $this->actingAs($user)
        ->post(route('user.wallet.recharge'), [
            'wallet_recharge_amount' => '80',
        ])
        ->assertRedirect();

    $order = Order::query()->whereBelongsTo($user)->latest('id')->firstOrFail();

    expect($order->wallet_recharge_option_id)->toBe($option->id)
        ->and($order->total_cents)->toBe(8000)
        ->and($order->wallet_recharge_cents)->toBe(11500);

    app(OrderService::class)->confirmPayment($order);

    $coupon = Coupon::query()
        ->whereIn('id', UserCoupon::query()
            ->whereBelongsTo($user)
            ->where('source', UserCoupon::SOURCE_WALLET_RECHARGE)
            ->pluck('coupon_id'))
        ->firstOrFail();

    expect($user->fresh()->wallet_balance_cents)->toBe(11500)
        ->and($coupon->value)->toBe(500)
        ->and($coupon->is_stackable)->toBeFalse();
});

it('uses wallet balance when completing a flash sale order selection', function (): void {
    $user = User::factory()->create(['role' => 'customer', 'wallet_balance_cents' => 1990]);
    $category = Category::query()->create(['name' => 'Wallet Flash', 'slug' => 'wallet-flash', 'is_active' => true]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => 'Wallet flash product',
        'slug' => 'wallet-flash-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'WALLET-FLASH',
        'price_cents' => 5000,
        'stock' => 5,
        'is_active' => true,
    ]);
    $flashSale = FlashSale::query()->create([
        'product_id' => $product->id,
        'product_variant_ids' => [$variant->id],
        'name' => 'Wallet flash',
        'sale_price_cents' => 1990,
        'quantity_limit' => 1,
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addHour(),
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('flash-sales.reserve', $flashSale), ['quantity' => 1])
        ->assertRedirect();

    $order = Order::query()->whereBelongsTo($user)->firstOrFail();

    $this->actingAs($user)
        ->post(route('flash-sales.store', $order), [
            'product_variant_id' => $variant->id,
            'contact_name' => 'Flash Wallet User',
            'contact_phone' => '13800000000',
            'contact_email' => 'flash-wallet@example.com',
            'payment_method' => Order::PAYMENT_METHOD_WALLET,
        ])
        ->assertRedirect(route('orders.show', $order));

    $order->refresh();

    expect($order->wallet_payment_cents)->toBe(1990)
        ->and($order->total_cents)->toBe(0)
        ->and($order->paymentTotalCents())->toBe(1990)
        ->and($order->remainingPaymentCents())->toBe(0)
        ->and($order->payment_status)->toBe(Order::PAYMENT_CONFIRMED)
        ->and($user->fresh()->wallet_balance_cents)->toBe(0);
});

it('lets customers switch pending orders to fallback payment methods only', function (): void {
    $user = User::factory()->create(['role' => 'customer', 'wallet_balance_cents' => 5000]);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'PAY-SWITCH-1',
        'status' => Order::STATUS_PENDING_PAYMENT,
        'payment_status' => Order::PAYMENT_PENDING,
        'payment_method' => Order::PAYMENT_METHOD_QR_CODE,
        'subtotal_cents' => 5000,
        'total_cents' => 5000,
        'contact_name' => 'Switch User',
        'contact_phone' => '13800000000',
    ]);

    $this->actingAs($user)
        ->post(route('orders.payment-method', $order), [
            'payment_method' => Order::PAYMENT_METHOD_FALLBACK_QR,
        ])
        ->assertRedirect();

    expect($order->fresh()->payment_method)->toBe(Order::PAYMENT_METHOD_FALLBACK_QR);

    $this->actingAs($user)
        ->post(route('orders.payment-method', $order), [
            'payment_method' => Order::PAYMENT_METHOD_WALLET,
        ])
        ->assertSessionHasErrors('payment_method');

    $order->refresh();

    expect($order->payment_method)->toBe(Order::PAYMENT_METHOD_FALLBACK_QR)
        ->and($order->wallet_payment_cents)->toBe(0)
        ->and($order->total_cents)->toBe(5000)
        ->and($order->payment_status)->toBe(Order::PAYMENT_PENDING)
        ->and($user->fresh()->wallet_balance_cents)->toBe(5000);
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
        ->assertSee(Url::route('tags.show', $tag), false);
});

it('renders the tag index and exposes it under the home navigation menu', function (): void {
    $this->seed();

    $tag = ProductTag::query()->firstOrFail();

    $this->get(route('tags.index'))
        ->assertOk()
        ->assertSee('标签')
        ->assertSee('# '.$tag->name)
        ->assertSee(Url::route('tags.show', $tag), false);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-mobile-menu-open', false)
        ->assertSee('data-mobile-menu', false)
        ->assertSee(Url::route('tags.index'), false);
});

it('registers customers with a simple human verification challenge', function (): void {
    Storage::fake('public_uploads');

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
            'public_id' => 'staff_taken',
            'name' => 'staff-prefix-user',
            'email' => 'staff-prefix-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'captcha_answer' => 7,
        ])
        ->assertInvalid(['public_id']);

    $this->withSession(['register_captcha_answer' => 7])
        ->post(route('register'), [
            'public_id' => 'new_user',
            'name' => 'new-user',
            'email' => 'new-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'avatar' => UploadedFile::fake()->image('register-avatar.png', 120, 120),
            'captcha_answer' => 7,
        ])
        ->assertRedirect(route('home'))
        ->assertSessionHas('show_registration_onboarding', true);

    $this->assertDatabaseHas('users', [
        'public_id' => 'new_user',
        'email' => 'new-user@example.com',
        'role' => 'customer',
        'account_type' => 'regular',
    ]);

    $registered = User::query()->where('email', 'new-user@example.com')->firstOrFail();
    expect($registered->avatar_path)->not->toBeNull();
    Storage::disk('public_uploads')->assertExists($registered->avatar_path);

    $this->actingAs($registered)
        ->withSession(['show_registration_onboarding' => true])
        ->get(route('home'))
        ->assertOk()
        ->assertSee('data-registration-onboarding', false)
        ->assertSee(route('user.addresses.create', absolute: false), false)
        ->assertSee(route('user.section', 'wallet', absolute: false), false);
});

it('lets users switch storefront theme mode', function (): void {
    $user = User::factory()->create(['role' => 'customer']);

    $this->actingAs($user)
        ->patch(route('user.interface.update'), ['theme_mode' => 'dark'])
        ->assertRedirect();

    expect($user->fresh()->interface_settings['theme_mode'] ?? null)->toBe('dark');

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('shop-mode-dark', false);
});

it('repairs mojibake display text without changing valid utf8 text', function (): void {
    expect(\App\Support\Text::display('鐢ㄦ埛'))->toBe('用户')
        ->and(\App\Support\Text::display('枫桦林'))->toBe('枫桦林')
        ->and(\App\Support\Text::display('ShopWeb'))->toBe('ShopWeb');
});

it('renders the user center with clean utf8 labels', function (): void {
    $user = User::factory()->create(['role' => 'customer', 'wallet_balance_cents' => 1234]);

    $response = $this->actingAs($user)->get(route('user.center'));

    $response
        ->assertOk()
        ->assertSee('用户中心')
        ->assertSee('钱包余额')
        ->assertSee('浏览记录')
        ->assertDontSee('鐢', false)
        ->assertDontSee('閽', false)
        ->assertDontSee('娆', false);
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
    $product->update(['fulfillment_type' => Product::FULFILLMENT_IN_PERSON]);
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

    $conceptOrder = Order::query()->where('user_id', $user->id)->firstOrFail();
    app(OrderService::class)->confirmPayment($conceptOrder);

    expect($variant->fresh()->stock)->toBe($variant->stock);

    $product->update(['status' => Product::STATUS_PRESALE]);
    $variant->update(['stock' => 0]);

    $this->post(route('cart.items.store'), [
        'variant_id' => $variant->id,
        'quantity' => 25,
    ])->assertRedirect(route('cart.show'));

    $this->actingAs($user)->post(route('checkout.store'), [
        'contact_name' => '预售用户',
        'contact_phone' => '13900000000',
        'contact_email' => 'presale@example.com',
    ])->assertRedirect();

    $order = Order::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
    app(OrderService::class)->confirmPayment($order);

    expect($variant->fresh()->stock)->toBe(0)
        ->and($order->fresh()->status)->toBe(Order::STATUS_PENDING_SHIPMENT)
        ->and($order->items()->first()->quantity)->toBe(25);
});

it('treats online delivery products as unlimited stock', function (): void {
    $this->seed();

    $user = User::factory()->create(['role' => 'customer']);
    $category = Category::query()->firstOrFail();
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => '线上无限库存商品',
        'slug' => 'online-unlimited-stock-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'ONLINE-UNLIMITED-1',
        'price_cents' => 1800,
        'stock' => 0,
        'low_stock_threshold' => 5,
        'is_active' => true,
    ]);

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertSee('不限库存')
        ->assertDontSee('该商品已售罄');

    $this->post(route('cart.items.store'), [
        'variant_id' => $variant->id,
        'quantity' => 12,
    ])->assertRedirect(route('cart.show'));

    $this->actingAs($user)->post(route('checkout.store'), [
        'contact_name' => '线上交付用户',
        'contact_phone' => '13800000000',
        'contact_email' => 'online@example.com',
    ])->assertRedirect();

    $order = Order::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
    app(OrderService::class)->confirmPayment($order);

    expect($variant->fresh()->stock)->toBe(0)
        ->and($product->fresh()->status)->toBe(Product::STATUS_PUBLISHED)
        ->and($order->fresh()->status)->toBe(Order::STATUS_PAID)
        ->and($order->items()->first()->quantity)->toBe(12);

    $this->assertDatabaseMissing('inventory_movements', [
        'product_variant_id' => $variant->id,
        'reason' => 'payment_confirmed',
    ]);

    app(OrderService::class)->cancel($order->fresh());

    expect($variant->fresh()->stock)->toBe(0);
});

it('marks in stock products sold out after confirmed payment consumes stock', function (): void {
    $this->seed();

    $user = User::factory()->create(['role' => 'customer']);
    $product = Product::query()->firstOrFail();
    $product->update(['fulfillment_type' => Product::FULFILLMENT_IN_PERSON]);
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

    app(OrderService::class)->confirmPayment(Order::query()->where('user_id', $user->id)->firstOrFail());

    expect($variant->fresh()->stock)->toBe(0)
        ->and($product->fresh()->status)->toBe(Product::STATUS_SOLD_OUT);
});

it('hides storefront order numbers by default', function (): void {
    $this->seed();

    $user = User::factory()->create(['role' => 'customer']);
    $otherUser = User::factory()->create(['role' => 'customer']);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'PRIVATE-ORDER-1',
        'status' => Order::STATUS_PENDING_PAYMENT,
        'payment_status' => Order::PAYMENT_PENDING,
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
        'body' => "## 购买说明\n\n- 人工确认付款 [fa:cart-shopping]\n- 收藏提示 [fa:regular:heart 愿望单]\n\n<script>alert('xss')</script>",
        'excerpt' => '轻量商城说明页',
        'seo_title' => '关于 ShopWeb',
        'seo_description' => 'ShopWeb 自定义说明页面',
        'is_published' => true,
    ]);

    $this->followingRedirects()
        ->get(route('pages.show', $page))
        ->assertOk()
        ->assertSee('关于我们')
        ->assertSee('购买说明')
        ->assertSee('人工确认付款')
        ->assertSee('fa-solid fa-cart-shopping', false)
        ->assertSee('fa-regular fa-heart', false)
        ->assertSee('aria-label="愿望单"', false)
        ->assertDontSee('<script>', false)
        ->assertDontSee('alert', false);
});

it('renders product comment font awesome shortcodes and icon inserters', function (): void {
    $category = Category::query()->create([
        'name' => '评论分类',
        'slug' => 'comment-category',
        'is_active' => true,
    ]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => '评论图标商品',
        'slug' => 'comment-icon-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
        'comments_enabled' => true,
    ]);
    ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'COMMENT-ICON-SKU',
        'spec_name' => '默认规格',
        'price_cents' => 1200,
        'stock' => 5,
        'is_active' => true,
    ]);
    ProductComment::query()->create([
        'product_id' => $product->id,
        'user_id' => User::factory()->create(['role' => 'customer'])->id,
        'rating' => 5,
        'body' => '喜欢这个 [fa:regular:heart 愿望单]',
    ]);

    $this->actingAs(User::factory()->create(['role' => 'customer']))
        ->get(route('products.show', $product))
        ->assertOk()
        ->assertSee('fa-regular fa-heart', false)
        ->assertSee('aria-label="愿望单"', false)
        ->assertSee('data-fa-textarea-target', false);
});

it('syncs a published custom page into a selected storefront menu', function (): void {
    $page = Page::query()->create([
        'title' => '发布菜单页面',
        'slug' => 'published-menu-page',
        'body' => '页面正文',
        'is_published' => true,
    ]);

    PageMenuPublication::sync($page, [
        'placement' => NavigationMenuItem::PLACEMENT_HOME_INFO,
        'label' => '菜单发布入口',
        'tooltip_text' => '从页面发布时写入的提示',
        'sort_order' => 30,
    ]);

    $this->assertDatabaseHas('navigation_menu_items', [
        'placement' => NavigationMenuItem::PLACEMENT_HOME_INFO,
        'label' => '菜单发布入口',
        'tooltip_text' => '从页面发布时写入的提示',
        'route_name' => 'pages.show',
        'sort_order' => 30,
    ]);

    $page->update(['slug' => 'published-menu-page-new']);

    PageMenuPublication::sync($page, [
        'placement' => NavigationMenuItem::PLACEMENT_TOP_NAV,
        'label' => '更新后的菜单',
        'tooltip_text' => '更新后的提示',
        'sort_order' => 40,
    ], 'published-menu-page');

    $menu = NavigationMenuItem::query()->where('label', '更新后的菜单')->firstOrFail();

    expect($menu->placement)->toBe(NavigationMenuItem::PLACEMENT_TOP_NAV)
        ->and($menu->route_parameters['page'])->toBe('published-menu-page-new')
        ->and($menu->tooltip_text)->toBe('更新后的提示');
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
        ->assertSee('--shop-page-background-image: url(/uploads/site/home-bg.jpg)', false);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('--shop-page-background-image: url(/uploads/site/auth-bg.jpg)', false);
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
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'SHIP-ONLY-1',
        'status' => Order::STATUS_SHIPPED,
        'payment_status' => Order::PAYMENT_CONFIRMED,
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

    $category = Category::query()->firstOrFail();
    Page::query()->updateOrCreate(
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

    $presaleProduct = Product::query()->create([
        'category_id' => $category->id,
        'title' => '预售测试商品',
        'slug' => 'presale-home-product',
        'status' => Product::STATUS_PRESALE,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);
    ProductVariant::query()->create([
        'product_id' => $presaleProduct->id,
        'sku' => 'HOME-PRESALE-1',
        'price_cents' => 3000,
        'stock' => 0,
        'is_active' => true,
    ]);
    $incomingProduct = Product::query()->create([
        'category_id' => $category->id,
        'title' => '进货中测试商品',
        'slug' => 'incoming-home-product',
        'status' => Product::STATUS_INCOMING,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
        'incoming_quantity' => 12,
    ]);
    ProductVariant::query()->create([
        'product_id' => $incomingProduct->id,
        'sku' => 'HOME-INCOMING-1',
        'price_cents' => 3500,
        'stock' => 0,
        'is_active' => true,
    ]);

    $response = $this->get(route('home'))
        ->assertOk()
        ->assertSee('商店信息')
        ->assertSee('关于我们')
        ->assertSee('推荐商品')
        ->assertSee('默认商品')
        ->assertSee('折扣商品')
        ->assertSee('秒杀商品')
        ->assertSee('概念商品')
        ->assertSee('客服会话')
        ->assertSee('客服工单')
        ->assertSee('折扣测试商品')
        ->assertSee('概念测试商品')
        ->assertSee('right-4 top-4', false)
        ->assertSee('购买')
        ->assertSee('加入购物车')
        ->assertSee('投票')
        ->assertSee('筹款')
        ->assertDontSee('<h2 class="text-base font-semibold">预售商品</h2>', false)
        ->assertDontSee('<h2 class="text-base font-semibold">进货中商品</h2>', false);

    $html = $response->getContent();

    expect(strpos($html, '推荐商品'))->toBeLessThan(strpos($html, '折扣商品'))
        ->and(strpos($html, '折扣商品'))->toBeLessThan(strpos($html, '默认商品'))
        ->and(strpos($html, '默认商品'))->toBeLessThan(strpos($html, '秒杀商品'))
        ->and(strpos($html, '秒杀商品'))->toBeLessThan(strpos($html, '概念商品'))
        ->and(strpos($html, '折扣商品'))->toBeLessThan(strpos($html, '概念商品'));
});

it('lets admins customize the home product section order from the home page settings', function (): void {
    $this->seed();

    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::query()->firstOrFail();

    $featuredProduct = Product::query()->create([
        'category_id' => $category->id,
        'title' => '自定义顺序推荐商品',
        'slug' => 'custom-order-featured-product',
        'status' => Product::STATUS_PUBLISHED,
        'is_featured' => true,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);
    ProductVariant::query()->create([
        'product_id' => $featuredProduct->id,
        'sku' => 'CUSTOM-HOME-FEATURED',
        'price_cents' => 1000,
        'stock' => 5,
        'is_active' => true,
    ]);

    $discountProduct = Product::query()->create([
        'category_id' => $category->id,
        'title' => '自定义顺序折扣商品',
        'slug' => 'custom-order-discount-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);
    ProductVariant::query()->create([
        'product_id' => $discountProduct->id,
        'sku' => 'CUSTOM-HOME-DISCOUNT',
        'price_cents' => 2000,
        'discount_price_cents' => 1200,
        'stock' => 5,
        'is_active' => true,
    ]);

    Product::query()->create([
        'category_id' => $category->id,
        'title' => '自定义顺序概念商品',
        'slug' => 'custom-order-concept-product',
        'status' => Product::STATUS_CONCEPT,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);

    $this->actingAs($admin);

    Livewire::test(HomeContentPage::class)
        ->fillForm([
            'home_welcome_enabled' => true,
            'home_title' => '首页',
            'home_welcome_image_path' => null,
            'home_content' => null,
            'home_product_section_order' => [
                ['section' => 'default'],
                ['section' => 'concept'],
                ['section' => 'discount'],
                ['section' => 'flash'],
                ['section' => 'featured'],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(SiteSetting::query()->firstOrFail()->homeProductSectionOrder())
        ->toBe(['default', 'concept', 'discount', 'flash', 'featured']);

    $html = $this->get(route('home'))
        ->assertOk()
        ->getContent();

    expect(strpos($html, '<h2 class="text-base font-semibold">默认商品</h2>'))
        ->toBeLessThan(strpos($html, '<h2 class="text-base font-semibold">概念商品</h2>'))
        ->and(strpos($html, '<h2 class="text-base font-semibold">概念商品</h2>'))
        ->toBeLessThan(strpos($html, '<h2 class="text-base font-semibold">折扣商品</h2>'))
        ->and(strpos($html, '<h2 class="text-base font-semibold">折扣商品</h2>'))
        ->toBeLessThan(strpos($html, '<h2 class="text-base font-semibold">秒杀商品</h2>'))
        ->and(strpos($html, '<h2 class="text-base font-semibold">秒杀商品</h2>'))
        ->toBeLessThan(strpos($html, '<h2 class="text-base font-semibold">推荐商品</h2>'));
});

it('shows default home products by sort order when there are no discount products', function (): void {
    $this->seed();

    ProductVariant::query()->update([
        'discount_price_cents' => null,
        'discount_starts_at' => null,
        'discount_ends_at' => null,
    ]);

    $category = Category::query()->firstOrFail();
    $lateProduct = Product::query()->create([
        'category_id' => $category->id,
        'title' => '默认排序靠后商品',
        'slug' => 'default-order-late-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
        'sort_order' => 20,
    ]);
    ProductVariant::query()->create([
        'product_id' => $lateProduct->id,
        'sku' => 'DEFAULT-LATE',
        'price_cents' => 2000,
        'stock' => 5,
        'is_active' => true,
    ]);
    $earlyProduct = Product::query()->create([
        'category_id' => $category->id,
        'title' => '默认排序靠前商品',
        'slug' => 'default-order-early-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
        'sort_order' => -10,
    ]);
    ProductVariant::query()->create([
        'product_id' => $earlyProduct->id,
        'sku' => 'DEFAULT-EARLY',
        'price_cents' => 1000,
        'stock' => 5,
        'is_active' => true,
    ]);

    $response = $this->get(route('home'))
        ->assertOk()
        ->assertSee('默认商品')
        ->assertDontSee('<h2 class="text-base font-semibold">折扣商品</h2>', false);

    $html = $response->getContent();

    expect(strpos($html, '默认排序靠前商品'))->toBeLessThan(strpos($html, '默认排序靠后商品'));
});

it('renders configurable top navigation and home information menu items separately', function (): void {
    $this->seed();

    NavigationMenuItem::query()->delete();

    $page = Page::query()->create([
        'title' => '关于枫桦林',
        'slug' => 'maple-birch-about',
        'body' => '关于页面正文',
        'is_published' => true,
    ]);

    NavigationMenuItem::query()->create([
        'placement' => NavigationMenuItem::PLACEMENT_TOP_NAV,
        'label' => '自定义关于',
        'tooltip_text' => '查看关于页面说明',
        'route_name' => 'pages.show',
        'route_parameters' => ['page' => $page->slug],
        'sort_order' => 10,
        'is_active' => true,
    ]);

    NavigationMenuItem::query()->create([
        'placement' => NavigationMenuItem::PLACEMENT_TOP_NAV,
        'label' => '论坛入口',
        'route_name' => 'forum.index',
        'sort_order' => 20,
        'is_active' => true,
    ]);

    NavigationMenuItem::query()->create([
        'placement' => NavigationMenuItem::PLACEMENT_HOME_INFO,
        'label' => '商店声明',
        'tooltip_text' => '阅读商店声明',
        'route_name' => 'pages.show',
        'route_parameters' => ['page' => $page->slug],
        'sort_order' => 5,
        'is_active' => true,
    ]);

    $response = $this->get(route('home'))
        ->assertOk()
        ->assertSee('自定义关于')
        ->assertSee('论坛入口')
        ->assertSee('商店声明')
        ->assertSee('title="查看关于页面说明"', false)
        ->assertSee('title="阅读商店声明"', false)
        ->assertSee('/p/maple-birch-about', false);

    $html = $response->getContent();

    preg_match_all('/>\s*商店声明\s*<\/a>/u', $html, $storeInfoMatches);

    expect(strpos($html, '自定义关于'))->toBeLessThan(strpos($html, '论坛入口'))
        ->and(count($storeInfoMatches[0]))->toBe(3);
});

it('keeps store information menu managed and hides empty placeholder menus', function (): void {
    $this->seed();

    NavigationMenuItem::query()->delete();

    $page = Page::query()->create([
        'title' => '菜单中的关于我们',
        'slug' => 'menu-managed-about',
        'body' => '关于页面正文',
        'is_published' => true,
    ]);
    Page::query()->create([
        'title' => '404 页面文案',
        'slug' => '404',
        'template' => 'not_found',
        'body' => '404 正文',
        'is_published' => true,
    ]);
    Page::query()->create([
        'title' => '未挂菜单页面',
        'slug' => 'not-in-menu',
        'body' => '不应该自动进入信息菜单',
        'is_published' => true,
    ]);

    $emptyParent = NavigationMenuItem::query()->create([
        'placement' => NavigationMenuItem::PLACEMENT_TOP_NAV,
        'label' => '空目录',
        'sort_order' => 5,
        'is_active' => true,
    ]);
    $parent = NavigationMenuItem::query()->create([
        'placement' => NavigationMenuItem::PLACEMENT_TOP_NAV,
        'label' => '有子目录',
        'sort_order' => 10,
        'is_active' => true,
    ]);
    NavigationMenuItem::query()->create([
        'placement' => NavigationMenuItem::PLACEMENT_TOP_NAV,
        'parent_id' => $parent->id,
        'label' => '子菜单页面',
        'route_name' => 'pages.show',
        'route_parameters' => ['page' => $page->slug],
        'sort_order' => 10,
        'is_active' => true,
    ]);
    NavigationMenuItem::query()->create([
        'placement' => NavigationMenuItem::PLACEMENT_HOME_INFO,
        'label' => '信息菜单关于',
        'route_name' => 'pages.show',
        'route_parameters' => ['page' => $page->slug],
        'sort_order' => 10,
        'is_active' => true,
    ]);

    $response = $this->get(route('home'))
        ->assertOk()
        ->assertSee('信息菜单关于')
        ->assertSee('有子目录')
        ->assertSee('子菜单页面')
        ->assertSee('请选择下方子菜单')
        ->assertDontSee('空目录')
        ->assertDontSee('404 页面文案')
        ->assertDontSee('未挂菜单页面');

    expect($emptyParent->hasDestination())->toBeFalse()
        ->and($response->getContent())->toContain('/p/menu-managed-about');
});

it('renders default and concept home sections when there are no discounts', function (): void {
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
        ->assertSee('默认商品')
        ->assertSee('商品列表')
        ->assertSee('概念商品')
        ->assertSee('暂无概念商品')
        ->assertSee(Url::route('products.index'), false)
        ->assertSee(Url::route('products.index', ['status' => Product::STATUS_CONCEPT]), false)
        ->assertDontSee('<h2 class="text-base font-semibold">折扣商品</h2>', false)
        ->assertDontSee('<h2 class="text-base font-semibold">预售商品</h2>', false)
        ->assertDontSee('<h2 class="text-base font-semibold">进货中商品</h2>', false);
});

it('filters storefront product lists by featured discount and concept sections', function (): void {
    $this->seed();

    $category = Category::query()->firstOrFail();

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

    $presaleProduct = Product::query()->create([
        'category_id' => $category->id,
        'title' => '首页预售筛选商品',
        'slug' => 'presale-filter-product',
        'status' => Product::STATUS_PRESALE,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);
    ProductVariant::query()->create([
        'product_id' => $presaleProduct->id,
        'sku' => 'PRESALE-FILTER-1',
        'price_cents' => 3000,
        'stock' => 0,
        'is_active' => true,
    ]);
    $incomingProduct = Product::query()->create([
        'category_id' => $category->id,
        'title' => '首页进货中筛选商品',
        'slug' => 'incoming-filter-product',
        'status' => Product::STATUS_INCOMING,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
        'incoming_quantity' => 5,
    ]);
    ProductVariant::query()->create([
        'product_id' => $incomingProduct->id,
        'sku' => 'INCOMING-FILTER-1',
        'price_cents' => 3500,
        'stock' => 0,
        'is_active' => true,
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

    $this->get(route('products.index', ['status' => Product::STATUS_PRESALE]))
        ->assertOk()
        ->assertSee('预售商品')
        ->assertSee($presaleProduct->title)
        ->assertDontSee($discountProduct->title)
        ->assertDontSee($conceptProduct->title);

    $this->get(route('products.index', ['status' => Product::STATUS_INCOMING]))
        ->assertOk()
        ->assertSee('进货中商品')
        ->assertSee($incomingProduct->title)
        ->assertDontSee($discountProduct->title)
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
    $category = Category::query()->firstOrFail();

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

    app(OrderService::class)->confirmPayment(Order::query()
        ->whereHas('items', fn ($query) => $query->where('product_id', $concept->id))
        ->firstOrFail());

    expect($conceptVariant->fresh()->stock)->toBe(0);
});

it('shows storefront purchase actions on product details and sold out badges', function (): void {
    $this->seed();

    $category = Category::query()->firstOrFail();
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => '详情购买商品',
        'slug' => 'detail-buy-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);
    ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'DETAIL-BUY-1',
        'price_cents' => 1200,
        'stock' => 5,
        'is_active' => true,
    ]);

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertSee('加入购物车')
        ->assertSee('立即购买')
        ->assertSee('data-cart-add-form', false)
        ->assertSee('data-cart-count', false)
        ->assertSee('data-cart-subtotal', false)
        ->assertSee(Url::route('cart.buy-now'), false);

    $presale = Product::query()->create([
        'category_id' => $category->id,
        'title' => '详情预售商品',
        'slug' => 'detail-presale-product',
        'status' => Product::STATUS_PRESALE,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);
    ProductVariant::query()->create([
        'product_id' => $presale->id,
        'sku' => 'DETAIL-PRESALE-1',
        'price_cents' => 2200,
        'stock' => 0,
        'is_active' => true,
    ]);

    $this->get(route('products.show', $presale))
        ->assertOk()
        ->assertSee('right-4 top-4', false)
        ->assertSee('预售')
        ->assertSee('加入预售购物车')
        ->assertSee('预售下单');

    $soldOut = Product::query()->create([
        'category_id' => $category->id,
        'title' => '首页售罄商品',
        'slug' => 'home-sold-out-product',
        'status' => Product::STATUS_SOLD_OUT,
        'is_featured' => true,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);
    ProductVariant::query()->create([
        'product_id' => $soldOut->id,
        'sku' => 'SOLD-OUT-1',
        'price_cents' => 9900,
        'stock' => 0,
        'is_active' => true,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('首页售罄商品')
        ->assertSee('售罄');

    $this->get(route('products.show', $soldOut))
        ->assertOk()
        ->assertSee('该商品已售罄')
        ->assertSee('售罄')
        ->assertDontSee('立即购买');
});

it('prices orders by the selected sku variant', function (): void {
    $this->seed();

    $user = User::factory()->create(['role' => 'customer']);
    $category = Category::query()->firstOrFail();
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => '多规格价格商品',
        'slug' => 'sku-price-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);
    $variantA = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'SKU-PRICE-A',
        'spec_name' => '白色常规装',
        'specs' => ['颜色' => '白色', '尺码' => 'M'],
        'price_cents' => 1000,
        'stock' => 5,
        'is_active' => true,
    ]);
    $variantB = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'SKU-PRICE-B',
        'spec_name' => '黑色大包装',
        'specs' => ['颜色' => '黑色'],
        'price_cents' => 2500,
        'stock' => 5,
        'is_active' => true,
    ]);

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertSee($variantA->specLabel())
        ->assertSee($variantB->specLabel())
        ->assertSee('白色常规装')
        ->assertSee('颜色')
        ->assertSee('白色')
        ->assertSee('尺码')
        ->assertDontSee('颜色: 白色')
        ->assertSee('data-product-spec-list', false)
        ->assertSee('data-product-variant-option', false)
        ->assertSee('data-product-stock', false)
        ->assertSee('aria-pressed="true"', false)
        ->assertSee('data-spec-label="白色常规装"', false)
        ->assertSee(Money::format($variantA->price_cents))
        ->assertSee(Money::format($variantB->price_cents));

    $this->get(route('products.index'))
        ->assertOk()
        ->assertSee('白色常规装')
        ->assertDontSee('颜色: 白色');

    $this->post(route('cart.items.store'), [
        'variant_id' => $variantB->id,
        'quantity' => 2,
    ])->assertRedirect(route('cart.show'));

    $this->actingAs($user)->post(route('checkout.store'), [
        'contact_name' => 'SKU 价格用户',
        'contact_phone' => '13800000000',
    ])->assertRedirect();

    $order = Order::query()->where('user_id', $user->id)->latest('id')->firstOrFail();

    expect($order->total_cents)->toBe(5000)
        ->and($order->items()->first()->unit_price_cents)->toBe(2500)
        ->and($order->items()->first()->line_total_cents)->toBe(5000)
        ->and($order->items()->first()->variant_sku)->toBe('SKU-PRICE-B');
});

it('keeps long sku option labels inside the product detail boundary', function (): void {
    $this->seed();

    $category = Category::query()->firstOrFail();
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => '长规格商品',
        'slug' => 'long-sku-label-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);

    ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'LONG-SKU-LABEL',
        'spec_name' => '11片戊酸雌二醇片2mg * 10片戊酸雌二醇2mg/醋酸环丙孕酮1mg 复合',
        'specs' => [
            '戊酸雌二醇片2mg' => '11片',
            '戊酸雌二醇2mg/醋酸环丙孕酮1mg 复合' => '10片',
        ],
        'price_cents' => 8000,
        'stock' => 10,
        'is_active' => true,
    ]);

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertSee('data-product-variant-options', false)
        ->assertSee('max-w-full', false)
        ->assertSee('break-words', false)
        ->assertSee('grid-cols-[minmax(0,1fr)_auto]', false);
});

it('saves multiple sku rows with independent image links from the admin product form', function (): void {
    $this->seed();

    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::query()->firstOrFail();
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => '后台多 SKU 商品',
        'slug' => 'admin-multi-sku-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);

    $this->actingAs($admin);

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->fillForm([
            'variants' => [
                [
                    'sku' => 'ADMIN-SKU-WHITE-M',
                    'spec_name' => '白色 M 码',
                    'specs' => ['颜色' => '白色', '尺码' => 'M'],
                    'image_path' => 'https://cdn.example.com/white-m.jpg',
                    'price_cents' => '19.90',
                    'stock' => 8,
                    'low_stock_threshold' => 2,
                    'is_active' => true,
                ],
                [
                    'sku' => 'ADMIN-SKU-BLACK-L',
                    'spec_name' => '黑色 L 码',
                    'specs' => ['颜色' => '黑色', '尺码' => 'L'],
                    'image_path' => '/uploads/products/black-l.webp',
                    'price_cents' => '29.90',
                    'stock' => 6,
                    'low_stock_threshold' => 2,
                    'is_active' => true,
                ],
            ],
            'media' => [
                [
                    'type' => ProductMedia::TYPE_PREVIEW,
                    'media_kind' => ProductMedia::KIND_IMAGE,
                    'path' => 'https://cdn.example.com/product-main.jpg',
                    'alt' => '商品主图',
                    'sort_order' => 0,
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->variants()->count())->toBe(2)
        ->and(ProductVariant::query()->where('sku', 'ADMIN-SKU-WHITE-M')->first()?->spec_name)->toBe('白色 M 码')
        ->and(ProductVariant::query()->where('sku', 'ADMIN-SKU-WHITE-M')->first()?->image_path)->toBe('https://cdn.example.com/white-m.jpg')
        ->and(ProductVariant::query()->where('sku', 'ADMIN-SKU-WHITE-M')->first()?->price_cents)->toBe(1990)
        ->and(ProductVariant::query()->where('sku', 'ADMIN-SKU-BLACK-L')->first()?->specs)->toBe(['颜色' => '黑色', '尺码' => 'L'])
        ->and($product->media()->first()?->path)->toBe('https://cdn.example.com/product-main.jpg');
});

it('shows media library upload pickers beside product image inputs', function (): void {
    $this->seed();

    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::query()->firstOrFail();
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => '后台图片选择商品',
        'slug' => 'admin-image-picker-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);

    ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'ADMIN-PICKER-SKU',
        'specs' => ['颜色' => '蓝色'],
        'image_path' => 'products/skus/blue.jpg',
        'price_cents' => 1990,
        'stock' => 3,
        'is_active' => true,
    ]);

    ProductMedia::query()->create([
        'product_id' => $product->id,
        'type' => ProductMedia::TYPE_PREVIEW,
        'media_kind' => ProductMedia::KIND_IMAGE,
        'path' => 'products/media/main.jpg',
        'alt' => '商品图',
    ]);

    $this->actingAs($admin);

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->assertSee('图片链接')
        ->assertSee('选择资源库文件/上传')
        ->assertSee('图片/视频链接或路径')
        ->assertSee('资源库 / 上传文件');
});

it('uses sku images as product detail fallback and gallery entries', function (): void {
    $this->seed();

    $category = Category::query()->firstOrFail();
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => 'SKU 图片商品',
        'slug' => 'sku-image-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);
    ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'SKU-IMAGE-WHITE',
        'specs' => ['颜色' => '白色'],
        'image_path' => 'products/sku-white.jpg',
        'price_cents' => 1000,
        'stock' => 5,
        'is_active' => true,
    ]);
    ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'SKU-IMAGE-BLACK',
        'specs' => ['颜色' => '黑色'],
        'image_path' => 'https://cdn.example.com/sku-black.webp',
        'price_cents' => 1200,
        'stock' => 5,
        'is_active' => true,
    ]);

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertSee('data-product-main-media', false)
        ->assertSee('src="/uploads/products/sku-white.jpg"', false)
        ->assertSee('data-image-url="/uploads/products/sku-white.jpg"', false)
        ->assertSee('data-image-url="https://cdn.example.com/sku-black.webp"', false)
        ->assertSee('SKU 图');
});

it('applies global coupons and restricts product coupons to a single matching cart item', function (): void {
    $this->seed();

    $user = User::factory()->create(['role' => 'customer']);
    $category = Category::query()->firstOrFail();
    $productA = Product::query()->create([
        'category_id' => $category->id,
        'title' => '优惠商品 A',
        'slug' => 'coupon-product-a',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);
    $variantA = ProductVariant::query()->create([
        'product_id' => $productA->id,
        'sku' => 'COUPON-A',
        'price_cents' => 10000,
        'stock' => 5,
        'is_active' => true,
    ]);
    $productB = Product::query()->create([
        'category_id' => $category->id,
        'title' => '优惠商品 B',
        'slug' => 'coupon-product-b',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);
    $variantB = ProductVariant::query()->create([
        'product_id' => $productB->id,
        'sku' => 'COUPON-B',
        'price_cents' => 5000,
        'stock' => 5,
        'is_active' => true,
    ]);

    Coupon::query()->create([
        'code' => 'GLOBAL20',
        'name' => '全场八折',
        'type' => Coupon::TYPE_PERCENT,
        'value' => 20,
        'scope' => Coupon::SCOPE_GLOBAL,
        'usage_limit' => 2,
        'is_active' => true,
    ]);
    Coupon::query()->create([
        'code' => 'ONLYA',
        'name' => 'A 商品券',
        'type' => Coupon::TYPE_FIXED,
        'value' => 1000,
        'scope' => Coupon::SCOPE_PRODUCT,
        'product_id' => $productA->id,
        'is_active' => true,
    ]);

    $this->post(route('cart.items.store'), ['variant_id' => $variantA->id, 'quantity' => 1]);
    $this->actingAs($user)->post(route('checkout.store'), [
        'contact_name' => '优惠用户',
        'contact_phone' => '13800000000',
        'coupon_code' => 'GLOBAL20',
    ])->assertRedirect();

    $this->assertDatabaseHas('orders', [
        'user_id' => $user->id,
        'coupon_code' => 'GLOBAL20',
        'discount_cents' => 2000,
        'total_cents' => 8000,
    ]);

    $this->post(route('cart.items.store'), ['variant_id' => $variantA->id, 'quantity' => 1]);
    $this->actingAs($user)->post(route('checkout.store'), [
        'contact_name' => '优惠用户',
        'contact_phone' => '13800000000',
        'coupon_code' => 'GLOBAL20',
    ])->assertRedirect();

    $this->post(route('cart.items.store'), ['variant_id' => $variantA->id, 'quantity' => 1]);
    $this->actingAs($user)->post(route('checkout.store'), [
        'contact_name' => '优惠用户',
        'contact_phone' => '13800000000',
        'coupon_code' => 'GLOBAL20',
    ])->assertInvalid(['coupon_code']);

    $this->flushSession();
    $this->post(route('cart.items.store'), ['variant_id' => $variantA->id, 'quantity' => 1]);
    $this->post(route('cart.items.store'), ['variant_id' => $variantB->id, 'quantity' => 1]);
    $this->actingAs($user)->post(route('checkout.store'), [
        'contact_name' => '优惠用户',
        'contact_phone' => '13800000000',
        'coupon_code' => 'ONLYA',
    ])->assertInvalid(['coupon_code']);

    $this->flushSession();
    $this->post(route('cart.items.store'), ['variant_id' => $variantA->id, 'quantity' => 1]);
    $this->actingAs($user)->post(route('checkout.store'), [
        'contact_name' => '优惠用户',
        'contact_phone' => '13800000000',
        'coupon_code' => 'ONLYA',
    ])->assertRedirect();

    $this->assertDatabaseHas('orders', [
        'user_id' => $user->id,
        'coupon_code' => 'ONLYA',
        'discount_cents' => 1000,
        'total_cents' => 9000,
    ]);
});

it('shows product price ranges and defaults detail price to the first sku', function (): void {
    $this->seed();

    $category = Category::query()->firstOrFail();
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => '多价格商品',
        'slug' => 'range-price-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);
    ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'RANGE-HIGH',
        'specs' => ['规格' => '高价'],
        'price_cents' => 3000,
        'stock' => 5,
        'is_active' => true,
    ]);
    ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'RANGE-LOW',
        'specs' => ['规格' => '低价'],
        'price_cents' => 1000,
        'stock' => 5,
        'is_active' => true,
    ]);

    $this->get(route('products.index'))
        ->assertOk()
        ->assertSee('¥10.00~¥30.00');

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertSee('data-product-price', false)
        ->assertSee('¥30.00')
        ->assertSee('data-price="¥10.00"', false);
});

it('refreshes cached storefront prices when the discount page updates a sku', function (): void {
    $this->seed();

    Cache::flush();

    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::query()->firstOrFail();
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => 'Cache Discount Product',
        'slug' => 'cache-discount-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);
    $lowVariant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'CACHE-DISCOUNT-LOW',
        'specs' => ['规格' => '低价'],
        'price_cents' => 1000,
        'stock' => 5,
        'is_active' => true,
    ]);
    ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'CACHE-DISCOUNT-HIGH',
        'specs' => ['规格' => '高价'],
        'price_cents' => 3000,
        'stock' => 5,
        'is_active' => true,
    ]);

    app(StorefrontCache::class)->homeProducts('latest');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Cache Discount Product')
        ->assertSee(Money::format(1000).'~'.Money::format(3000));

    $this->actingAs($admin);

    Livewire::test(ProductDiscountPage::class)
        ->fillForm([
            'variant_ids' => [$lowVariant->id],
            'discount_enabled' => true,
            'discount_price_cents' => '5.00',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($lowVariant->fresh()->effectivePriceCents())->toBe(500);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Cache Discount Product')
        ->assertSee(Money::format(1000), false)
        ->assertSee(Money::format(500), false);

    Livewire::test(ProductDiscountPage::class)
        ->assertSee('当前折扣商品')
        ->assertSee('Cache Discount Product')
        ->set("discountRows.{$lowVariant->id}.discount_price", '4.00')
        ->call('updateDiscount', $lowVariant->id);

    expect($lowVariant->fresh()->discount_price_cents)->toBe(400);

    Livewire::test(ProductDiscountPage::class)
        ->call('cancelDiscount', $lowVariant->id);

    expect($lowVariant->fresh()->discount_price_cents)->toBeNull()
        ->and($lowVariant->fresh()->discount_starts_at)->toBeNull()
        ->and($lowVariant->fresh()->discount_ends_at)->toBeNull();
});

it('lets users claim coupons and apply one coupon per cart sku', function (): void {
    $this->seed();

    $user = User::factory()->create(['role' => 'customer']);
    $category = Category::query()->firstOrFail();
    $productA = Product::query()->create([
        'category_id' => $category->id,
        'title' => '券包商品 A',
        'slug' => 'wallet-coupon-product-a',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);
    $variantA = ProductVariant::query()->create([
        'product_id' => $productA->id,
        'sku' => 'WALLET-A',
        'price_cents' => 10000,
        'stock' => 5,
        'is_active' => true,
    ]);
    $productB = Product::query()->create([
        'category_id' => $category->id,
        'title' => '券包商品 B',
        'slug' => 'wallet-coupon-product-b',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);
    $variantB = ProductVariant::query()->create([
        'product_id' => $productB->id,
        'sku' => 'WALLET-B',
        'price_cents' => 5000,
        'stock' => 5,
        'is_active' => true,
    ]);

    $couponA = Coupon::query()->create([
        'code' => 'WALLETA',
        'name' => 'A 商品券',
        'type' => Coupon::TYPE_FIXED,
        'value' => 1000,
        'scope' => Coupon::SCOPE_PRODUCT,
        'product_id' => $productA->id,
        'is_active' => true,
    ]);
    $couponA->products()->sync([$productA->id, $productB->id]);
    $couponB = Coupon::query()->create([
        'code' => 'WALLETB',
        'name' => '全场九折',
        'type' => Coupon::TYPE_PERCENT,
        'value' => 10,
        'scope' => Coupon::SCOPE_GLOBAL,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('user.coupons.store'), ['coupon_code' => 'WALLETA'])
        ->assertRedirect();
    $this->actingAs($user)
        ->post(route('user.coupons.store'), ['coupon_code' => 'WALLETB'])
        ->assertRedirect();

    $this->actingAs($user)
        ->get(route('user.section', 'coupons'))
        ->assertOk()
        ->assertSee('A 商品券')
        ->assertSee('券包商品 A')
        ->assertSee('券包商品 B')
        ->assertSee('全场九折')
        ->assertSee('可叠加使用');

    $userCouponA = UserCoupon::query()->whereBelongsTo($user)->whereBelongsTo($couponA)->firstOrFail();
    $userCouponB = UserCoupon::query()->whereBelongsTo($user)->whereBelongsTo($couponB)->firstOrFail();

    $this->post(route('cart.items.store'), ['variant_id' => $variantA->id, 'quantity' => 1]);
    $this->post(route('cart.items.store'), ['variant_id' => $variantB->id, 'quantity' => 1]);

    $this->actingAs($user)
        ->get(route('checkout.create'))
        ->assertOk()
        ->assertSee('name="coupon_items['.$variantA->id.']"', false)
        ->assertSee('name="coupon_items['.$variantB->id.']"', false)
        ->assertSee('WALLETA')
        ->assertSee('WALLETB')
        ->assertSee('可叠加使用');

    $this->actingAs($user)->post(route('checkout.store'), [
        'contact_name' => '券包用户',
        'contact_phone' => '13800000000',
        'coupon_items' => [
            $variantA->id => $userCouponA->id,
            $variantB->id => $userCouponB->id,
        ],
    ])->assertRedirect();

    $this->assertDatabaseHas('orders', [
        'user_id' => $user->id,
        'discount_cents' => 1500,
        'total_cents' => 13500,
    ]);
    $this->assertDatabaseHas('order_items', [
        'variant_sku' => 'WALLET-A',
        'coupon_code' => 'WALLETA',
        'discount_cents' => 1000,
    ]);
    $this->assertDatabaseHas('order_items', [
        'variant_sku' => 'WALLET-B',
        'coupon_code' => 'WALLETB',
        'discount_cents' => 500,
    ]);
});

it('prevents non stackable coupons from being used with another coupon in one order', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $category = Category::query()->create(['name' => 'No stack', 'slug' => 'no-stack', 'is_active' => true]);
    $productA = Product::query()->create([
        'category_id' => $category->id,
        'title' => 'No stack A',
        'slug' => 'no-stack-a',
        'status' => Product::STATUS_PUBLISHED,
    ]);
    $variantA = ProductVariant::query()->create([
        'product_id' => $productA->id,
        'sku' => 'NO-STACK-A',
        'price_cents' => 10000,
        'stock' => 5,
        'is_active' => true,
    ]);
    $productB = Product::query()->create([
        'category_id' => $category->id,
        'title' => 'No stack B',
        'slug' => 'no-stack-b',
        'status' => Product::STATUS_PUBLISHED,
    ]);
    $variantB = ProductVariant::query()->create([
        'product_id' => $productB->id,
        'sku' => 'NO-STACK-B',
        'price_cents' => 5000,
        'stock' => 5,
        'is_active' => true,
    ]);
    $couponA = Coupon::query()->create([
        'code' => 'NOSTACKA',
        'name' => '不可叠加券',
        'type' => Coupon::TYPE_FIXED,
        'value' => 1000,
        'scope' => Coupon::SCOPE_GLOBAL,
        'is_stackable' => false,
        'is_active' => true,
    ]);
    $couponB = Coupon::query()->create([
        'code' => 'STACKB',
        'name' => '普通券',
        'type' => Coupon::TYPE_FIXED,
        'value' => 500,
        'scope' => Coupon::SCOPE_GLOBAL,
        'is_stackable' => true,
        'is_active' => true,
    ]);

    $userCouponA = app(CouponService::class)->issueToUser($couponA, $user);
    $userCouponB = app(CouponService::class)->issueToUser($couponB, $user);
    $cartItems = collect([
        ['variant' => $variantA, 'product' => $productA, 'line_total_cents' => 10000],
        ['variant' => $variantB, 'product' => $productB, 'line_total_cents' => 5000],
    ]);

    app(CouponService::class)->resolveForCart($user, $cartItems, [
        $variantA->id => $userCouponA->id,
    ]);

    expect(fn () => app(CouponService::class)->resolveForCart($user, $cartItems, [
        $variantA->id => $userCouponA->id,
        $variantB->id => $userCouponB->id,
    ]))->toThrow(ValidationException::class);
});

it('cleans stale product links from global coupons and tracks coupon holders', function (): void {
    $this->seed();

    $user = User::factory()->create(['role' => 'customer']);
    $category = Category::query()->firstOrFail();
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => 'Legacy Coupon Product',
        'slug' => 'legacy-coupon-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);
    $coupon = Coupon::query()->create([
        'code' => 'GLOBALSTALE',
        'name' => 'Global With Stale Product',
        'type' => Coupon::TYPE_FIXED,
        'value' => 1000,
        'scope' => Coupon::SCOPE_GLOBAL,
        'product_id' => $product->id,
        'is_active' => true,
    ]);
    $coupon->products()->sync([$product->id]);

    UserCoupon::query()->create([
        'user_id' => $user->id,
        'coupon_id' => $coupon->id,
        'source' => UserCoupon::SOURCE_CLAIMED,
        'claimed_at' => now(),
    ]);

    $migration = include database_path('migrations/2026_06_18_000003_cleanup_global_coupon_product_links.php');
    $migration->up();

    $coupon->refresh();

    expect($coupon->product_id)->toBeNull()
        ->and($coupon->products()->count())->toBe(0)
        ->and($coupon->userCoupons()->count())->toBe(1);

    $this->assertDatabaseMissing('coupon_product', [
        'coupon_id' => $coupon->id,
        'product_id' => $product->id,
    ]);

    expect(DB::table('user_coupons')->where('coupon_id', $coupon->id)->count())->toBe(1);
});

it('shows concrete coupon holder details in the backoffice coupon list', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create([
        'role' => 'customer',
        'name' => 'Coupon Holder',
        'public_id' => 'holder_001',
        'email' => 'holder@example.com',
    ]);
    $coupon = Coupon::query()->create([
        'code' => 'HOLDERLIST',
        'name' => 'Holder List Coupon',
        'type' => Coupon::TYPE_FIXED,
        'value' => 1000,
        'scope' => Coupon::SCOPE_GLOBAL,
        'is_active' => true,
    ]);

    UserCoupon::query()->create([
        'user_id' => $user->id,
        'coupon_id' => $coupon->id,
        'issued_by_user_id' => $admin->id,
        'source' => UserCoupon::SOURCE_ADMIN,
        'claimed_at' => now(),
        'note' => 'manual holder check',
    ]);

    $this->actingAs($admin)
        ->get('/admin/coupons')
        ->assertOk()
        ->assertSee('HOLDERLIST')
        ->assertSee('Coupon Holder')
        ->assertSee('holder_001')
        ->assertSee('holder@example.com')
        ->assertSee('查看持有人');
});

it('does not show coupon controls during flash sale checkout', function (): void {
    $this->seed();

    $user = User::factory()->create(['role' => 'customer']);
    $category = Category::query()->firstOrFail();
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => '秒杀无优惠商品',
        'slug' => 'flash-sale-no-coupon-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'FLASH-NO-COUPON',
        'price_cents' => 3000,
        'stock' => 5,
        'is_active' => true,
    ]);
    $flashSale = FlashSale::query()->create([
        'product_id' => $product->id,
        'name' => '无优惠秒杀',
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addHour(),
        'sale_price_cents' => 1000,
        'quantity_limit' => 2,
        'is_active' => true,
        'product_variant_ids' => [$variant->id],
    ]);

    $this->actingAs($user)
        ->post(route('flash-sales.reserve', $flashSale), ['quantity' => 1])
        ->assertRedirect();

    $order = Order::query()->whereBelongsTo($user)->latest()->firstOrFail();

    $this->actingAs($user)
        ->get(route('flash-sales.checkout', $order))
        ->assertOk()
        ->assertDontSee('coupon_code', false)
        ->assertDontSee('优惠码');
});

it('lets users manage addresses and preloads the default address during checkout', function (): void {
    $this->seed();

    $user = User::factory()->create(['role' => 'customer']);
    $category = Category::query()->firstOrFail();
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => '地址物流商品',
        'slug' => 'address-shipping-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'ADDRESS-SHIPPING-1',
        'price_cents' => 8800,
        'stock' => 5,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('user.addresses.store'), [
            'raw_text' => '中国北京市朝阳区望京街道 88 号',
            'recipient_name' => '枫桦',
            'phone' => '13800000000',
            'is_default' => '1',
            'is_visible' => '1',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('user_addresses', [
        'user_id' => $user->id,
        'recipient_name' => '枫桦',
        'country' => '中国',
        'province' => '北京市',
        'city' => '北京市',
        'district' => '朝阳区',
        'is_default' => true,
        'is_visible' => true,
    ]);

    $address = $user->addresses()->firstOrFail();

    $this->actingAs($user)
        ->get(route('user.section', 'addresses'))
        ->assertOk()
        ->assertSee('我的地址')
        ->assertSee('新增地址')
        ->assertSee(route('user.addresses.edit', $address, false), false)
        ->assertDontSee('智能识别地址');

    $this->actingAs($user)
        ->get(route('user.addresses.create'))
        ->assertOk()
        ->assertSee('智能识别地址')
        ->assertSee('详细地址');

    $this->post(route('cart.items.store'), [
        'variant_id' => $variant->id,
        'quantity' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('checkout.create'))
        ->assertOk()
        ->assertSee('value="枫桦"', false)
        ->assertSee('value="13800000000"', false)
        ->assertSee('中国 北京市 北京市 朝阳区 望京街道88号');

    $this->get(route('users.show', $user))
        ->assertOk()
        ->assertSee('公开地址')
        ->assertSee('望京街道88号');
});

it('renders product videos and optional product introduction', function (): void {
    $category = Category::query()->create(['name' => '媒体', 'slug' => 'media', 'is_active' => true]);
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
    $category = Category::query()->create(['name' => '秒杀', 'slug' => 'flash', 'is_active' => true]);
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

    $order = Order::query()->where('user_id', $user->id)->firstOrFail();

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

it('allows presale products to join flash sales without stock limits', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $category = Category::query()->create(['name' => '预售秒杀', 'slug' => 'presale-flash', 'is_active' => true]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => '预售秒杀商品',
        'slug' => 'presale-flash-product',
        'status' => Product::STATUS_PRESALE,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'PRESALE-FLASH',
        'price_cents' => 5000,
        'stock' => 0,
        'is_active' => true,
    ]);
    $flashSale = FlashSale::query()->create([
        'product_id' => $product->id,
        'product_variant_ids' => [$variant->id],
        'name' => '预售也秒杀',
        'sale_price_cents' => 1990,
        'quantity_limit' => 3,
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addHour(),
        'is_active' => true,
    ]);

    expect($flashSale->availableQuantity())->toBe(3);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('预售秒杀商品');

    $this->actingAs($user)
        ->post(route('flash-sales.reserve', $flashSale), ['quantity' => 1])
        ->assertRedirect();

    $order = Order::query()->where('user_id', $user->id)->firstOrFail();

    $this->actingAs($user)
        ->post(route('flash-sales.store', $order), [
            'product_variant_id' => $variant->id,
            'contact_name' => '预售用户',
            'contact_phone' => '13800000000',
            'contact_email' => 'presale@example.com',
            'shipping_address' => '预售地址',
        ])
        ->assertRedirect(route('orders.show', $order));

    $this->assertDatabaseHas('order_items', [
        'order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'product_status' => Product::STATUS_PRESALE,
        'unit_price_cents' => 1990,
        'flash_sale_id' => $flashSale->id,
    ]);
});

it('opens the flash sale campaign create page and creates a one-time campaign', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::query()->create(['name' => '秒杀计划', 'slug' => 'flash-campaign-create', 'is_active' => true]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => '秒杀计划商品',
        'slug' => 'flash-campaign-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'FLASH-CAMPAIGN-SKU',
        'price_cents' => 10000,
        'stock' => 5,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->get('/admin/flash-sale-campaigns/create')
        ->assertOk();

    Livewire::actingAs($admin)
        ->test(CreateFlashSaleCampaign::class)
        ->fillForm([
            'name' => '一次性秒杀计划',
            'schedule_type' => FlashSaleCampaign::TYPE_ONCE,
            'starts_on' => now()->toDateString(),
            'starts_at_time' => now()->subMinute()->format('H:i'),
            'ends_at_time' => now()->addHour()->format('H:i'),
            'generate_days_ahead' => 1,
            'is_active' => true,
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_variant_ids' => [$variant->id],
                    'sale_price_cents' => '8.00',
                    'quantity_limit' => 3,
                    'is_active' => true,
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $campaign = FlashSaleCampaign::query()->where('name', '一次性秒杀计划')->firstOrFail();

    expect($campaign->items()->count())->toBe(1)
        ->and(FlashSale::query()->where('flash_sale_campaign_id', $campaign->id)->count())->toBe(1);
});

it('generates recurring flash sale sessions with multiple campaign products', function (): void {
    $category = Category::query()->create(['name' => '周期秒杀', 'slug' => 'recurring-flash', 'is_active' => true]);
    $productA = Product::query()->create([
        'category_id' => $category->id,
        'title' => '每日秒杀 A',
        'slug' => 'daily-flash-a',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);
    $productB = Product::query()->create([
        'category_id' => $category->id,
        'title' => '每日秒杀 B',
        'slug' => 'daily-flash-b',
        'status' => Product::STATUS_PRESALE,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);
    $variantA = ProductVariant::query()->create([
        'product_id' => $productA->id,
        'sku' => 'DAILY-A',
        'price_cents' => 5000,
        'stock' => 10,
        'is_active' => true,
    ]);
    $variantB = ProductVariant::query()->create([
        'product_id' => $productB->id,
        'sku' => 'DAILY-B',
        'price_cents' => 8000,
        'stock' => 0,
        'is_active' => true,
    ]);

    $campaign = FlashSaleCampaign::query()->create([
        'name' => '每日固定秒杀',
        'schedule_type' => FlashSaleCampaign::TYPE_DAILY,
        'starts_on' => '2026-06-15',
        'ends_on' => '2026-06-16',
        'starts_at_time' => '10:00:00',
        'ends_at_time' => '11:00:00',
        'generate_days_ahead' => 3,
        'is_active' => true,
    ]);

    FlashSaleCampaignItem::query()->create([
        'flash_sale_campaign_id' => $campaign->id,
        'product_id' => $productA->id,
        'product_variant_ids' => [$variantA->id],
        'sale_price_cents' => 990,
        'quantity_limit' => 5,
        'is_active' => true,
    ]);
    FlashSaleCampaignItem::query()->create([
        'flash_sale_campaign_id' => $campaign->id,
        'product_id' => $productB->id,
        'product_variant_ids' => [$variantB->id],
        'sale_price_cents' => 1990,
        'quantity_limit' => 3,
        'is_active' => true,
    ]);

    $created = app(FlashSaleCampaignService::class)->syncCampaign($campaign, CarbonImmutable::parse('2026-06-15'), CarbonImmutable::parse('2026-06-17'));

    expect($created)->toBe(4)
        ->and(FlashSale::query()->where('flash_sale_campaign_id', $campaign->id)->count())->toBe(4);

    $this->assertDatabaseHas('flash_sales', [
        'flash_sale_campaign_id' => $campaign->id,
        'product_id' => $productA->id,
        'sale_price_cents' => 990,
        'quantity_limit' => 5,
    ]);
    $this->assertDatabaseHas('flash_sales', [
        'flash_sale_campaign_id' => $campaign->id,
        'product_id' => $productB->id,
        'sale_price_cents' => 1990,
        'quantity_limit' => 3,
    ]);

    $createdAgain = app(FlashSaleCampaignService::class)->syncCampaign($campaign, CarbonImmutable::parse('2026-06-15'), CarbonImmutable::parse('2026-06-17'));

    expect($createdAgain)->toBe(0)
        ->and(FlashSale::query()->where('flash_sale_campaign_id', $campaign->id)->count())->toBe(4);
});

it('renders friend links from the homepage and friend link listing', function (): void {
    FriendLink::query()->create([
        'site_name' => '伙伴站点',
        'url' => 'https://example.test',
        'image_path' => 'https://cdn.example.test/friend.png',
        'description' => '一个友情链接。',
        'is_active' => true,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('友情链接')
        ->assertSee(Url::route('friend-links.index'), false);

    $this->get(route('friend-links.index'))
        ->assertOk()
        ->assertSee('md:grid-cols-3', false)
        ->assertSee('gap-4', false)
        ->assertSee('https://cdn.example.test/friend.png', false)
        ->assertSee('data-friend-link-placeholder', false)
        ->assertSee('onerror=', false)
        ->assertSee('伙伴站点')
        ->assertSee('一个友情链接。');
});

it('requires explicit receipt confirmation for online delivery orders', function (): void {
    Storage::fake('digital_deliveries');

    $user = User::factory()->create(['role' => 'customer']);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'DIGI-1',
        'status' => Order::STATUS_AWAITING_RECEIPT,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 1000,
        'total_cents' => 1000,
        'contact_name' => 'Digital',
        'contact_phone' => '1',
        'requires_shipping' => false,
        'digital_delivery_content' => '请使用下方兑换码。',
        'digital_delivery_code' => 'CODE-123',
        'digital_delivery_attachment_paths' => ['DIGI-1/file.txt'],
        'digital_delivery_sent_at' => now(),
    ]);
    Storage::disk('digital_deliveries')->put('DIGI-1/file.txt', 'secret-file');

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('线上交付内容')
        ->assertSee('CODE-123')
        ->assertSee('确认收货并完成订单');

    $this->actingAs($user)
        ->post(route('orders.digital-delivery.copied', $order))
        ->assertRedirect();

    $order->refresh();

    expect($order->status)->toBe(Order::STATUS_AWAITING_RECEIPT)
        ->and($order->digital_delivery_viewed_at)->not->toBeNull()
        ->and($order->digital_delivery_completed_at)->toBeNull()
        ->and($order->fulfilled_at)->toBeNull();

    $this->actingAs($user)
        ->get(route('orders.digital-delivery.download', [$order, 0]))
        ->assertOk();

    expect($order->fresh()->status)->toBe(Order::STATUS_AWAITING_RECEIPT);

    $this->actingAs($user)
        ->post(route('orders.confirm-receipt', $order))
        ->assertRedirect();

    $order->refresh();

    expect($order->status)->toBe(Order::STATUS_FULFILLED)
        ->and($order->digital_delivery_completed_at)->not->toBeNull()
        ->and($order->fulfilled_at)->not->toBeNull();
});

it('shows order details without payment blocks after payment is confirmed', function (): void {
    SiteSetting::query()->firstOrCreate([])->update([
        'payment_qr_path' => 'payments/main.png',
        'show_tracking_numbers_to_users' => true,
        'payment_fallback_config' => [
            'fallback_qr_path' => 'payments/fallback.png',
            'friend_qr_path' => 'payments/friend.png',
        ],
    ]);

    $user = User::factory()->create(['role' => 'customer']);
    $category = Category::query()->create(['name' => 'Order Detail Category', 'slug' => 'order-detail-category', 'is_active' => true]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => 'Order Detail Product',
        'slug' => 'order-detail-product',
        'status' => Product::STATUS_PUBLISHED,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'DETAIL-SKU',
        'spec_name' => 'Detail Pack',
        'price_cents' => 1000,
        'stock' => 5,
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'DETAIL-1',
        'status' => Order::STATUS_AWAITING_RECEIPT,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 1000,
        'total_cents' => 1000,
        'contact_name' => 'Detail',
        'contact_phone' => '1',
        'requires_shipping' => true,
        'tracking_number' => 'TRACK-DETAIL-1',
    ]);
    OrderItem::query()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_title' => $product->title,
        'product_status' => $product->status,
        'variant_sku' => $variant->sku,
        'variant_specs' => ['规格' => '标准'],
        'unit_price_cents' => 1000,
        'quantity' => 1,
        'line_total_cents' => 1000,
        'status' => Order::STATUS_AWAITING_RECEIPT,
    ]);

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('Order Detail Product')
        ->assertSee('TRACK-DETAIL-1')
        ->assertSee('订单时间')
        ->assertSee('创建时间')
        ->assertSee('完成时间')
        ->assertSee('确认收货')
        ->assertDontSee('付款说明')
        ->assertDontSee('付款凭证')
        ->assertDontSee('/uploads/payments/main.png', false)
        ->assertDontSee('/uploads/payments/fallback.png', false)
        ->assertDontSee('/uploads/payments/friend.png', false);
});

it('shows the next flash sale time when a flash sale has not started yet', function (): void {
    $category = Category::query()->create(['name' => '下次秒杀', 'slug' => 'next-flash', 'is_active' => true]);
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

it('calculates checkout shipping from warehouse province rates and product extra fees', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $category = Category::query()->create(['name' => '邮费', 'slug' => 'shipping-fee', 'is_active' => true]);

    $warehouse = Warehouse::query()->create([
        'name' => '测试 A 仓',
        'country' => '中国',
        'street' => '测试占位地址',
        'is_active' => true,
    ]);
    WarehouseShippingRate::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => '北京',
        'provinces' => ['北京'],
        'fee_cents' => 1200,
        'is_active' => true,
    ]);
    WarehouseShippingRate::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => '其他地区',
        'fee_cents' => 2000,
        'is_default' => true,
        'is_active' => true,
    ]);

    $productA = Product::query()->create([
        'category_id' => $category->id,
        'title' => '大件商品 A',
        'slug' => 'heavy-product-a',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
        'shipping_extra_fee_cents' => 200,
    ]);
    $variantA = ProductVariant::query()->create([
        'product_id' => $productA->id,
        'sku' => 'SHIP-A',
        'price_cents' => 10000,
        'stock' => 5,
        'is_active' => true,
    ]);

    $productB = Product::query()->create([
        'category_id' => $category->id,
        'title' => '大件商品 B',
        'slug' => 'heavy-product-b',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
        'shipping_extra_fee_cents' => 300,
    ]);
    $variantB = ProductVariant::query()->create([
        'product_id' => $productB->id,
        'sku' => 'SHIP-B',
        'price_cents' => 20000,
        'stock' => 5,
        'is_active' => true,
    ]);

    foreach ([$variantA, $variantB] as $variant) {
        WarehouseStock::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'name' => $variant->product->title,
            'sku' => $variant->sku,
            'quantity' => 5,
        ]);
    }

    $this->post(route('cart.items.store'), ['variant_id' => $variantA->id, 'quantity' => 1]);
    $this->post(route('cart.items.store'), ['variant_id' => $variantB->id, 'quantity' => 1]);

    $this->actingAs($user)->post(route('checkout.store'), [
        'contact_name' => '收件人',
        'contact_phone' => '13800000000',
        'contact_email' => 'ship@example.com',
        'shipping_province' => '北京',
        'shipping_address' => '中国 北京市 朝阳区 测试路 1 号',
    ])->assertRedirect();

    $order = Order::query()->where('user_id', $user->id)->firstOrFail();

    expect($order->shipping_fee_cents)->toBe(1700)
        ->and($order->total_cents)->toBe(31700)
        ->and($order->shipment_notice)->toBeNull()
        ->and($order->items()->where('warehouse_id', $warehouse->id)->count())->toBe(2);
});

it('stores private shipping requests and resolves category or tag defaults', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $category = Category::query()->create([
        'name' => 'Private Shipping',
        'slug' => 'private-shipping',
        'is_active' => true,
        'private_shipping_default' => true,
    ]);
    $tag = ProductTag::query()->create([
        'name' => 'No Private',
        'slug' => 'no-private',
        'is_active' => true,
        'private_shipping_default' => false,
    ]);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => 'Private Shipping Product',
        'slug' => 'private-shipping-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'PRIVATE-SHIP',
        'price_cents' => 1000,
        'stock' => 5,
        'is_active' => true,
    ]);

    expect($product->fresh()->defaultsToPrivateShipping())->toBeTrue();

    $product->tags()->attach($tag);
    expect($product->fresh()->defaultsToPrivateShipping())->toBeFalse();

    $tag->update(['private_shipping_default' => true]);
    expect($product->fresh()->defaultsToPrivateShipping())->toBeTrue();

    $this->post(route('cart.items.store'), ['variant_id' => $variant->id, 'quantity' => 1]);
    $this->actingAs($user)->post(route('checkout.store'), [
        'contact_name' => 'Private User',
        'contact_phone' => '13800000000',
        'shipping_country' => '中国',
        'shipping_province' => '北京',
        'shipping_city' => '北京市',
        'shipping_detail' => 'Private Address',
        'private_shipping_requested' => '1',
    ])->assertRedirect();

    $order = Order::query()->whereBelongsTo($user)->firstOrFail();

    expect($order->private_shipping_requested)->toBeTrue();
});

it('charges shipping for presale logistics products without requiring stock', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $category = Category::query()->create(['name' => '预售邮费', 'slug' => 'presale-shipping', 'is_active' => true]);
    $warehouse = Warehouse::query()->create(['name' => '预售默认仓', 'country' => '中国', 'street' => '预售仓占位', 'is_active' => true]);

    WarehouseShippingRate::query()->create([
        'warehouse_id' => $warehouse->id,
        'name' => '默认邮费',
        'fee_cents' => 1500,
        'is_default' => true,
        'is_active' => true,
    ]);

    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => '物流预售商品',
        'slug' => 'logistics-presale-product',
        'status' => Product::STATUS_PRESALE,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
        'shipping_extra_fee_cents' => 300,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'PRESALE-SHIP-1',
        'price_cents' => 6000,
        'stock' => 0,
        'low_stock_threshold' => 5,
        'is_active' => true,
    ]);

    $this->post(route('cart.items.store'), ['variant_id' => $variant->id, 'quantity' => 2]);

    $this->actingAs($user)->post(route('checkout.store'), [
        'contact_name' => '预售收件人',
        'contact_phone' => '13800000000',
        'contact_email' => 'presale-ship@example.com',
        'shipping_province' => '北京',
        'shipping_address' => '中国 北京市 朝阳区 预售路 1 号',
    ])->assertRedirect();

    $order = Order::query()->where('user_id', $user->id)->firstOrFail();

    expect($order->shipping_fee_cents)->toBe(2100)
        ->and($order->total_cents)->toBe(14100)
        ->and($order->items()->first()->warehouse_id)->toBe($warehouse->id);
});

it('uses the selected logistics carrier without exposing warehouse choice at checkout', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $category = Category::query()->create(['name' => '物流选择', 'slug' => 'carrier-choice', 'is_active' => true]);
    $warehouse = Warehouse::query()->create([
        'name' => '内部仓库',
        'country' => '中国',
        'province' => '北京',
        'city' => '北京市',
        'is_active' => true,
    ]);
    $carrierA = ShippingCarrier::query()->create(['name' => '标准快递', 'code' => 'standard', 'is_active' => true]);
    $carrierB = ShippingCarrier::query()->create(['name' => '特快物流', 'code' => 'express', 'is_active' => true]);

    WarehouseShippingRate::query()->create([
        'warehouse_id' => $warehouse->id,
        'shipping_carrier_id' => $carrierA->id,
        'name' => '标准',
        'provinces' => ['北京'],
        'fee_cents' => 1200,
        'is_active' => true,
        'sort_order' => 1,
    ]);
    WarehouseShippingRate::query()->create([
        'warehouse_id' => $warehouse->id,
        'shipping_carrier_id' => $carrierB->id,
        'name' => '特快',
        'provinces' => ['北京'],
        'fee_cents' => 800,
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => '可选物流商品',
        'slug' => 'carrier-choice-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
        'shipping_extra_fee_cents' => 200,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'CARRIER-1',
        'price_cents' => 5000,
        'stock' => 5,
        'is_active' => true,
    ]);
    WarehouseStock::query()->create([
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'name' => $product->title,
        'sku' => $variant->sku,
        'quantity' => 5,
    ]);

    $this->post(route('cart.items.store'), ['variant_id' => $variant->id, 'quantity' => 1]);

    $this->actingAs($user)
        ->get(route('checkout.create'))
        ->assertOk()
        ->assertSee('物流方式')
        ->assertSee('标准快递')
        ->assertSee('特快物流')
        ->assertDontSee('内部仓库');

    $this->actingAs($user)->post(route('checkout.store'), [
        'contact_name' => '收件人',
        'contact_phone' => '13800000000',
        'contact_email' => 'carrier@example.com',
        'shipping_country' => '中国',
        'shipping_province' => '北京',
        'shipping_city' => '北京市',
        'shipping_detail' => '测试路 1 号',
        'shipping_carriers' => [$warehouse->id => $carrierB->id],
    ])->assertRedirect();

    $order = Order::query()->where('user_id', $user->id)->firstOrFail();
    $plan = $order->shipment_plan[0] ?? [];

    expect($order->shipping_fee_cents)->toBe(1000)
        ->and($order->total_cents)->toBe(6000)
        ->and($order->shipping_carrier_id)->toBe($carrierB->id)
        ->and($plan['shipping_carrier_id'])->toBe($carrierB->id)
        ->and($plan['fee_cents'])->toBe(1000);
});

it('uses the presale default warehouse unless the product overrides it', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $category = Category::query()->create(['name' => '预售默认仓', 'slug' => 'presale-default-warehouse', 'is_active' => true]);
    $wrongWarehouse = Warehouse::query()->create(['name' => '非默认仓', 'country' => '中国', 'is_active' => true, 'sort_order' => 0]);
    $defaultWarehouse = Warehouse::query()->create(['name' => '预售默认仓', 'country' => '中国', 'is_active' => true, 'sort_order' => 10]);
    $carrier = ShippingCarrier::query()->create(['name' => '预售物流', 'code' => 'presale', 'is_active' => true]);

    SiteSetting::query()->create(['presale_default_warehouse_id' => $defaultWarehouse->id]);

    WarehouseShippingRate::query()->create([
        'warehouse_id' => $wrongWarehouse->id,
        'shipping_carrier_id' => $carrier->id,
        'name' => '默认',
        'fee_cents' => 9900,
        'is_default' => true,
        'is_active' => true,
    ]);
    WarehouseShippingRate::query()->create([
        'warehouse_id' => $defaultWarehouse->id,
        'shipping_carrier_id' => $carrier->id,
        'name' => '默认',
        'fee_cents' => 1500,
        'is_default' => true,
        'is_active' => true,
    ]);

    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => '默认仓预售商品',
        'slug' => 'default-presale-warehouse-product',
        'status' => Product::STATUS_PRESALE,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
        'shipping_extra_fee_cents' => 300,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'PRESALE-DEFAULT-WAREHOUSE',
        'price_cents' => 6000,
        'stock' => 0,
        'is_active' => true,
    ]);

    $this->post(route('cart.items.store'), ['variant_id' => $variant->id, 'quantity' => 1]);

    $this->actingAs($user)->post(route('checkout.store'), [
        'contact_name' => '预售收件人',
        'contact_phone' => '13800000000',
        'contact_email' => 'presale-default@example.com',
        'shipping_country' => '中国',
        'shipping_province' => '北京',
        'shipping_city' => '北京市',
        'shipping_detail' => '预售路 1 号',
    ])->assertRedirect();

    $order = Order::query()->where('user_id', $user->id)->firstOrFail();

    expect($order->shipping_fee_cents)->toBe(1800)
        ->and($order->items()->first()->warehouse_id)->toBe($defaultWarehouse->id);
});

it('warns and charges per warehouse when an order must ship from multiple warehouses', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $category = Category::query()->create(['name' => '多仓', 'slug' => 'multi-warehouse', 'is_active' => true]);

    $warehouseA = Warehouse::query()->create(['name' => '测试 A 仓', 'country' => '中国', 'street' => 'A 仓占位', 'is_active' => true]);
    $warehouseB = Warehouse::query()->create(['name' => '测试 B 仓', 'country' => '中国', 'street' => 'B 仓占位', 'is_active' => true]);

    WarehouseShippingRate::query()->create([
        'warehouse_id' => $warehouseA->id,
        'name' => '北京',
        'provinces' => ['北京'],
        'fee_cents' => 1200,
        'is_active' => true,
    ]);
    WarehouseShippingRate::query()->create([
        'warehouse_id' => $warehouseB->id,
        'name' => '北京',
        'provinces' => ['北京'],
        'fee_cents' => 1800,
        'is_active' => true,
    ]);

    $productA = Product::query()->create([
        'category_id' => $category->id,
        'title' => '多仓商品 A',
        'slug' => 'multi-product-a',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
        'shipping_extra_fee_cents' => 200,
    ]);
    $variantA = ProductVariant::query()->create(['product_id' => $productA->id, 'sku' => 'MULTI-A', 'price_cents' => 10000, 'stock' => 5, 'is_active' => true]);

    $productB = Product::query()->create([
        'category_id' => $category->id,
        'title' => '多仓商品 B',
        'slug' => 'multi-product-b',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
        'shipping_extra_fee_cents' => 300,
    ]);
    $variantB = ProductVariant::query()->create(['product_id' => $productB->id, 'sku' => 'MULTI-B', 'price_cents' => 20000, 'stock' => 5, 'is_active' => true]);

    WarehouseStock::query()->create([
        'warehouse_id' => $warehouseA->id,
        'product_id' => $productA->id,
        'product_variant_id' => $variantA->id,
        'name' => $productA->title,
        'sku' => $variantA->sku,
        'quantity' => 5,
    ]);
    WarehouseStock::query()->create([
        'warehouse_id' => $warehouseB->id,
        'product_id' => $productB->id,
        'product_variant_id' => $variantB->id,
        'name' => $productB->title,
        'sku' => $variantB->sku,
        'quantity' => 5,
    ]);

    $this->post(route('cart.items.store'), ['variant_id' => $variantA->id, 'quantity' => 1]);
    $this->post(route('cart.items.store'), ['variant_id' => $variantB->id, 'quantity' => 1]);

    $this->actingAs($user)
        ->get(route('checkout.create'))
        ->assertOk()
        ->assertSee('物流方式')
        ->assertSee('包裹 1')
        ->assertSee('包裹 2')
        ->assertDontSee('测试 A 仓')
        ->assertDontSee('测试 B 仓');

    $this->actingAs($user)->post(route('checkout.store'), [
        'contact_name' => '收件人',
        'contact_phone' => '13800000000',
        'contact_email' => 'multi@example.com',
        'shipping_province' => '北京',
        'shipping_address' => '中国 北京市 朝阳区 测试路 2 号',
    ])->assertRedirect();

    $order = Order::query()->where('user_id', $user->id)->firstOrFail();

    expect($order->shipping_fee_cents)->toBe(3500)
        ->and($order->total_cents)->toBe(33500)
        ->and($order->shipment_notice)->toContain('分批发货')
        ->and($order->items()->where('warehouse_id', $warehouseA->id)->count())->toBe(1)
        ->and($order->items()->where('warehouse_id', $warehouseB->id)->count())->toBe(1);
});
