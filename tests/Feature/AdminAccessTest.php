<?php

use App\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Models\User;
use App\Filament\Resources\PageResource\Pages\Concerns\HandlesPageCoverUpload;
use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\OrderStatusSetting;
use App\Models\Page;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\Coupon;
use App\Models\WalletRechargeOption;
use Livewire\Livewire;

it('prevents customers from accessing the admin panel', function (): void {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)->get('/admin')->assertForbidden();
});

it('applies basic backoffice role permissions', function (): void {
    $this->seed();

    $operator = User::factory()->create(['role' => 'operator']);
    $finance = User::factory()->create(['role' => 'finance']);
    $warehouse = User::factory()->create(['role' => 'warehouse']);
    $sales = User::factory()->create(['role' => 'sales']);
    $purchasing = User::factory()->create(['role' => 'purchasing']);
    $support = User::factory()->create(['role' => 'support']);

    $this->actingAs($operator)->get('/admin/products')->assertOk();
    $this->actingAs($operator)->get('/admin/reports')->assertOk();
    $this->actingAs($operator)->get('/admin/system-info')->assertForbidden();

    $this->actingAs($finance)->get('/admin/orders')->assertOk();
    $this->actingAs($finance)->get('/admin/coupons')->assertOk();
    $this->actingAs($finance)->get('/admin/currency-settings')->assertOk();
    $this->actingAs($finance)->get('/admin/payment-verification-logs')->assertOk();
    $this->actingAs($finance)->get('/admin/products')->assertForbidden();

    $this->actingAs($warehouse)->get('/admin/products')->assertOk();
    $this->actingAs($warehouse)->get('/admin/inventory-movements')->assertOk();
    $this->actingAs($warehouse)->get('/admin/warehouses')->assertOk();
    $this->actingAs($warehouse)->get('/admin/orders')->assertForbidden();

    $this->actingAs($sales)->get('/admin/orders')->assertOk();
    $this->actingAs($sales)->get('/admin/products')->assertForbidden();

    $this->actingAs($purchasing)->get('/admin/products')->assertOk();
    $this->actingAs($purchasing)->get('/admin/resource-assets')->assertOk();
    $this->actingAs($purchasing)->get('/admin/orders')->assertForbidden();

    $this->actingAs($support)->get('/admin/forum-activity-logs')->assertOk();
    $this->actingAs($support)->get('/admin/support-chat-sessions')->assertOk();
    $this->actingAs($support)->get('/admin/support-tickets')->assertOk();
    $this->actingAs($support)->get('/admin/support-ai-settings')->assertOk();
    $this->actingAs($support)->get('/admin/payment-verification-logs')->assertForbidden();
    $this->actingAs($support)->get('/admin/orders')->assertForbidden();
});

it('renders admin sidebar navigation for admins', function (): void {
    $this->seed();

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('shopweb:admin-sidebar-reset-version', false)
        ->assertSee('shopweb:admin-prefetch:', false)
        ->assertSee('X-ShopWeb-Purpose', false)
        ->assertSee('requestIdleCallback', false)
        ->assertSee('shop-sidebar-collapsed-group-trigger', false)
        ->assertDontSee('shop-sidebar-collapsed-inline-items', false)
        ->assertDontSee('shop-sidebar-collapsed-inline-item', false)
        ->assertSee('shop-sidebar-expand-btn', false)
        ->assertSee('shop-sidebar-collapse-btn', false)
        ->assertSee('shop-admin-user-menu-trigger', false)
        ->assertSee('shop-admin-user-menu-panel', false)
        ->assertSee('shop-admin-front-link', false)
        ->assertSee('返回前台')
        ->assertSee('主页')
        ->assertSee('商品管理')
        ->assertSee('订单管理')
        ->assertSee('媒体库')
        ->assertSee('报告中心')
        ->assertSee('站点设置');
});

it('returns resource create pages to the listing and keeps create another available', function (): void {
    $this->seed();

    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    Livewire::test(CreateCategory::class)
        ->assertSee('保存')
        ->assertSee('保存并创建新分类')
        ->fillForm([
            'name' => '自动返回分类',
            'slug' => 'auto-return-category',
            'sort_order' => 0,
            'is_active' => true,
        ])
        ->call('create')
        ->assertRedirect('/admin/categories');
});

it('renders role scoped sidebar navigation for operators', function (): void {
    $this->seed();

    $operator = User::factory()->create(['role' => 'operator']);

    $this->actingAs($operator)
        ->get('/admin')
        ->assertOk()
        ->assertSee('商品管理')
        ->assertSee('订单管理')
        ->assertSee('媒体库')
        ->assertDontSee('站点设置');
});

it('renders csv import actions for admins', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin/products')
        ->assertOk()
        ->assertSee('导入 SKU CSV');

    $this->actingAs($admin)
        ->get('/admin/customers')
        ->assertOk()
        ->assertSee('导入前台用户 CSV')
        ->assertSee('创建前台用户');
});

it('renders compact customer rows with quick detail previews and actions', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create([
        'role' => 'customer',
        'name' => 'Quick Preview User',
        'public_id' => 'quick_preview_user',
        'email' => 'quick-preview@example.com',
    ]);
    User::factory()->create([
        'role' => 'customer',
        'referred_by_user_id' => $customer->id,
    ]);

    $this->actingAs($admin)
        ->get('/admin/customers')
        ->assertOk()
        ->assertSee('data-shopweb-customer-trigger', false)
        ->assertSee('data-shopweb-customer-template', false)
        ->assertSee('保存快速详情')
        ->assertSee('Quick Preview User')
        ->assertSee('quick_preview_user')
        ->assertSee('发放优惠码')
        ->assertSee('编辑');
});

it('updates customer quick detail fields from the expanded preview', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create([
        'role' => 'customer',
        'public_id' => 'quick_update_user',
        'email' => 'before-quick@example.com',
        'account_type' => 'regular',
        'forum_role' => 'member',
        'has_diagnosis_certificate' => false,
        'can_view_order_numbers' => false,
        'can_view_tracking_numbers' => false,
    ]);

    $csrfToken = 'customer-quick-update-test';

    $this->actingAs($admin)
        ->withSession(['_token' => $csrfToken])
        ->post(route('admin.customers.quick-update', $customer), [
            '_token' => $csrfToken,
            'email' => 'after-quick@example.com',
            'birthday' => '2001-02-03',
            'has_diagnosis_certificate' => '1',
            'account_type' => 'member',
            'forum_role' => 'moderator',
            'forum_posting_banned_at' => '2026-07-07 12:30:00',
            'forum_posting_ban_reason' => '测试快速封禁',
            'can_view_order_numbers' => '1',
            'can_view_tracking_numbers' => '1',
        ])
        ->assertRedirect();

    $customer->refresh();

    expect($customer->email)->toBe('after-quick@example.com')
        ->and($customer->birthday?->format('Y-m-d'))->toBe('2001-02-03')
        ->and($customer->has_diagnosis_certificate)->toBeTrue()
        ->and($customer->account_type)->toBe('member')
        ->and($customer->forum_role)->toBe('moderator')
        ->and($customer->forum_posting_banned_at?->format('Y-m-d H:i'))->toBe('2026-07-07 12:30')
        ->and($customer->forum_posting_ban_reason)->toBe('测试快速封禁')
        ->and($customer->can_view_order_numbers)->toBeTrue()
        ->and($customer->can_view_tracking_numbers)->toBeTrue();
});

it('renders catalog reference management pages for admins', function (): void {
    $this->seed();

    $admin = User::factory()->create(['role' => 'admin']);

    foreach ([
        '/admin/manufacturers' => '制造商',
        '/admin/suppliers' => '供应商',
        '/admin/catalog-attributes' => '属性',
        '/admin/delivery-statuses' => '交付状态',
        '/admin/sold-out-statuses' => '售罄状态',
        '/admin/quantity-units' => '数量单位',
        '/admin/product-tags' => '商品标签',
    ] as $path => $label) {
        $this->actingAs($admin)
            ->get($path)
            ->assertOk()
            ->assertSee($label);
    }

    $product = Product::query()->firstOrFail();

    $this->actingAs($admin)
        ->get("/admin/products/{$product->slug}/edit")
        ->assertOk()
        ->assertSee('制造商')
        ->assertSee('供应商')
        ->assertSee('SKU 与库存')
        ->assertSee('SKU 卡片预览')
        ->assertSee('data-card-preview-root="product-sku"', false)
        ->assertSee('data-card-preview-card', false)
        ->assertSee('shopwebProductSkuLocalPreviewBound', false)
        ->assertSee('data-product-sku-preview-card', false)
        ->assertSee('data-product-sku-settings', false)
        ->assertSee('SKU 规格')
        ->assertSee('添加规格值')
        ->assertSee('标签')
        ->assertSee('数量单位');

    $productResourceSource = file_get_contents(app_path('Filament/Resources/ProductResource.php'));

    expect($productResourceSource)
        ->toContain("'key' => 'product-sku'")
        ->toContain("'enableSorting' => false")
        ->not->toContain("'sortSaveMethod' => 'saveProductSkuSortOrder'");
});

it('opens product and customer edit pages by stable ids after route key changes', function (): void {
    $this->seed();

    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create([
        'role' => 'customer',
        'public_id' => 'route_customer',
    ]);
    $category = \App\Models\Category::query()->firstOrFail();
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => '中文商品标题',
        'slug' => '',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);

    expect($product->slug)->not->toBe('');

    $this->actingAs($admin)
        ->get("/admin/products/{$product->id}/edit")
        ->assertOk();

    $this->actingAs($admin)
        ->get("/admin/customers/{$customer->id}/edit")
        ->assertOk();
});

it('renders admin search with regex support', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin/admin-search')
        ->assertOk()
        ->assertSee('后台搜索')
        ->assertSee('regex:^订单')
        ->assertSee('商品标签');
});

it('renders system information for admins', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin/system-info')
        ->assertOk()
        ->assertSee('系统信息')
        ->assertSee('PHP')
        ->assertSee('数据库')
        ->assertSee('目录权限')
        ->assertSee('安装锁');
});

it('renders store information, cache management, and order status settings for admins', function (): void {
    $this->seed();

    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin/store-info')
        ->assertOk()
        ->assertSee('设置 - 商店信息')
        ->assertSee('商店邮箱')
        ->assertSee('主色');

    $this->actingAs($admin)
        ->get('/admin/currency-settings')
        ->assertOk()
        ->assertSee('货币设置')
        ->assertSee('汇率换算器');

    $this->actingAs($admin)
        ->get('/admin/cache-management')
        ->assertOk()
        ->assertSee('缓存管理')
        ->assertSee('缓存驱动')
        ->assertSee('清理全部缓存');

    $this->actingAs($admin)
        ->get('/admin/order-status-settings')
        ->assertOk()
        ->assertSee('订单状态设置')
        ->assertSee('待付款');

    expect(OrderStatusSetting::query()->count())->toBeGreaterThanOrEqual(4)
        ->and(SiteSetting::query()->first()->store_currency)->toBe('CNY');
});

it('renders payment settings with storefront payment preview', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    SiteSetting::query()->firstOrCreate([], [
        'site_name' => 'ShopWeb',
    ])->forceFill([
        'payment_qr_path' => 'payments/main.png',
        'payment_account_name' => 'ShopWeb Pay',
        'payment_gateway_config' => ['paypal_email' => 'pay@example.com'],
        'payment_fallback_config' => [
            'password_red_packet_enabled' => true,
            'password_red_packet_note' => '口令红包备用说明',
        ],
    ])->save();

    $this->actingAs($admin)
        ->get('/admin/payment-settings')
        ->assertOk()
        ->assertSee('付款页面预览')
        ->assertSee('示例订单 SW2026070712340001')
        ->assertSee('付款凭证上传入口')
        ->assertSee('ShopWeb Pay')
        ->assertSee('pay@example.com');
});

it('renders support ai connection fields for admins', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin/support-ai-settings')
        ->assertOk()
        ->assertSee('support_ai_endpoint', false)
        ->assertSee('support_ai_api_key', false)
        ->assertSee('support_ai_model', false);
});

it('renders the dedicated not found content settings page for admins', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin/not-found-content')
        ->assertOk()
        ->assertSee('cover_upload', false)
        ->assertSee('cover_external_url', false)
        ->assertSee('slug=404', false);
});

it('renders report center for admins', function (): void {
    $this->seed();

    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin/reports')
        ->assertOk()
        ->assertSee('报告中心')
        ->assertSee('今日销售')
        ->assertSee('低库存 SKU')
        ->assertSee('客户排行')
        ->assertSee('优惠码使用')
        ->assertSee('购买意愿投票')
        ->assertSee('价格区间投票');
});

it('renders presentation asset management for admins', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    MediaAsset::query()->create([
        'name' => 'Sales Deck',
        'path' => 'media/sales-deck.pptx',
        'disk' => 'public_uploads',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'usage' => MediaAsset::USAGE_PRESENTATION,
    ]);
    MediaAsset::query()->create([
        'name' => 'Plain Asset',
        'path' => 'media/plain.txt',
        'disk' => 'public_uploads',
        'mime_type' => 'text/plain',
        'usage' => MediaAsset::USAGE_GENERAL,
    ]);

    $this->actingAs($admin)
        ->get('/admin/media-assets/presentation')
        ->assertOk()
        ->assertSee('Sales Deck')
        ->assertDontSee('Plain Asset');
});

it('renders direct page cover upload field for admins', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin/pages/create')
        ->assertOk()
        ->assertSee('cover_upload', false)
        ->assertSee('shop-md-tool-preview', false)
        ->assertSee('fontAwesomeIconOptions', false)
        ->assertSee('font-awesome', false)
        ->assertSee('data-shop-fa-custom', false)
        ->assertSee('renderMarkdownIntoPreview', false)
        ->assertSee('上传新封面图')
        ->assertSee('编辑模式')
        ->assertSee('传统 Markdown')
        ->assertSee('交互式区块')
        ->assertSee('交互式区块编辑')
        ->assertSee('添加区块')
        ->assertSee('头图横幅')
        ->assertSee('菜单模块')
        ->assertSee('文章模块')
        ->assertSee('资源模块')
        ->assertSee('发布到菜单')
        ->assertSee('不添加到菜单')
        ->assertSee('顶部导航')
        ->assertSee('首页商店信息');
});

it('stores the preferred custom page editor mode', function (): void {
    $page = Page::query()->create([
        'title' => '交互式页面',
        'slug' => 'interactive-page',
        'editor_mode' => 'interactive',
        'body' => '传统正文',
        'blocks' => [
            [
                'type' => 'heading',
                'data' => ['text' => '拖拽模块', 'level' => 'h2'],
            ],
        ],
        'is_published' => true,
    ]);

    expect($page->fresh()->editor_mode)->toBe('interactive');

    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin/pages')
        ->assertOk()
        ->assertSee('交互式页面')
        ->assertSee('交互式');
});

it('renders configurable storefront navigation menu fields for admins', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin/navigation-menu-items')
        ->assertOk()
        ->assertSee('菜单目录树')
        ->assertSee('保存排序')
        ->assertSee('拖动菜单项可调整显示顺序')
        ->assertSee('dragMove', false)
        ->assertSee('dragWheel', false)
        ->assertSee('stopAutoScroll', false);

    $this->actingAs($admin)
        ->get('/admin/navigation-menu-items/create')
        ->assertOk()
        ->assertSee('显示位置')
        ->assertSee('首页商店信息')
        ->assertSee('文章')
        ->assertSee('内置功能')
        ->assertSee('链接提示文本')
        ->assertSee('鼠标悬停在菜单链接上时显示')
        ->assertSee('自定义页面')
        ->assertSee('没有子菜单的无页面菜单不会在前台显示');
});

it('creates a media asset when a page cover upload path is submitted', function (): void {
    $handler = new class {
        use HandlesPageCoverUpload;

        /**
         * @param  array<string, mixed>  $data
         * @return array<string, mixed>
         */
        public function run(array $data): array
        {
            return $this->attachUploadedCover($data);
        }
    };

    $data = $handler->run([
        'title' => 'Upload cover page',
        'slug' => 'upload-cover-page',
        'cover_upload' => ['pages/covers/new-cover.jpg'],
    ]);

    $asset = MediaAsset::query()->where('path', 'pages/covers/new-cover.jpg')->first();

    expect($asset)->not->toBeNull()
        ->and($asset->usage)->toBe(MediaAsset::USAGE_PAGE)
        ->and($data['cover_media_asset_id'])->toBe($asset->id)
        ->and($data)->not->toHaveKey('cover_upload');
});

it('shows wallet payment breakdown on the admin order form', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'customer']);
    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'ADMIN-WALLET-1',
        'status' => Order::STATUS_PENDING_PAYMENT,
        'payment_status' => Order::PAYMENT_PENDING,
        'subtotal_cents' => 10000,
        'discount_cents' => 0,
        'shipping_fee_cents' => 0,
        'wallet_payment_cents' => 3000,
        'total_cents' => 7000,
        'contact_name' => 'Admin Wallet',
        'contact_phone' => '13800000000',
    ]);

    $this->actingAs($admin)
        ->get(\App\Filament\Resources\OrderResource::getUrl('edit', ['record' => $order]))
        ->assertOk()
        ->assertSee('付款总金额')
        ->assertSee('钱包支付金额')
        ->assertSee('待支付金额')
        ->assertSee('¥100.00')
        ->assertSee('¥30.00')
        ->assertSee('¥70.00');
});

it('keeps payment breakdown details out of the admin order table columns', function (): void {
    $source = file_get_contents(app_path('Filament/Resources/OrderResource.php'));

    expect($source)
        ->toContain("TextColumn::make('payment_total_cents')")
        ->not->toContain("TextColumn::make('wallet_payment_cents')")
        ->not->toContain("TextColumn::make('total_cents')->label")
        ->not->toContain("TextColumn::make('shipping_fee_cents')")
        ->not->toContain("TextColumn::make('payment_status')->label");
});

it('renders merged admin management tabs for wallet flash sale and comments', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    WalletRechargeOption::query()->create([
        'name' => '后台预览充值卡',
        'currency_code' => 'CNY',
        'currency_unit' => 'yuan',
        'amount_cents' => 10000,
        'discount_percent' => 90,
        'bonus_cents' => 1000,
        'is_active' => true,
    ]);
    WalletRechargeOption::query()->create([
        'name' => null,
        'currency_code' => 'CNY',
        'currency_unit' => 'yuan',
        'amount_cents' => 1000,
        'discount_percent' => null,
        'bonus_cents' => 0,
        'is_active' => true,
    ]);
    WalletRechargeOption::query()->create([
        'name' => 'Coupon reward option',
        'currency_code' => 'CNY',
        'currency_unit' => 'yuan',
        'amount_cents' => 3000,
        'discount_percent' => null,
        'bonus_cents' => 0,
        'is_active' => true,
        'coupon_reward_enabled' => true,
        'coupon_reward_currency_code' => 'CNY',
        'coupon_reward_currency_unit' => 'yuan',
        'coupon_reward_type' => Coupon::TYPE_FIXED,
        'coupon_reward_value' => 500,
        'coupon_reward_quantity' => 2,
        'coupon_reward_usage_limit' => 1,
        'coupon_reward_rules' => [
            [
                'name' => 'editable fixed reward',
                'currency_code' => 'CNY',
                'currency_unit' => 'yuan',
                'type' => Coupon::TYPE_FIXED,
                'value' => 500,
                'valid_days' => null,
                'scope' => Coupon::SCOPE_GLOBAL,
                'product_ids' => [],
                'minimum_order_cents' => 0,
                'quantity' => 2,
                'usage_limit' => 1,
                'is_stackable' => false,
            ],
        ],
    ]);

    $this->actingAs($admin)
        ->get(\App\Filament\Pages\WalletSettingsPage::getUrl())
        ->assertOk()
        ->assertSee('钱包设置')
        ->assertSee('兑换码管理');

    $this->actingAs($admin)
        ->get(\App\Filament\Pages\WalletSettingsPage::getUrl())
        ->assertOk()
        ->assertSee('用户端充值页面整体预览')
        ->assertSee('充值钱包')
        ->assertSee('后台预览充值卡')
        ->assertSee('data-card-preview-root="wallet-recharge"', false)
        ->assertSee('data-card-preview-card', false)
        ->assertSee('data-wallet-recharge-preview-card', false)
        ->assertSee('data-wallet-recharge-settings', false)
        ->assertSee('data-wallet-recharge-save-order', false)
        ->assertSee('data-card-preview-sort-key', false)
        ->assertSee('data-card-preview-sort-save-method', false)
        ->assertSee('onclick=', false)
        ->assertSee('data-wallet-recharge-option-id', false)
        ->assertSee(\App\Support\Money::format(1000))
        ->assertDontSee(\App\Support\Money::format(10))
        ->assertSee('Coupon reward option')
        ->assertSee('editable fixed reward')
        ->assertSee('新增充值选项');

    $templateSource = file_get_contents(app_path('Filament/Support/CardPreviewTemplate.php'));

    expect($templateSource)
        ->toContain('topLevelSettingItems')
        ->toContain('topLevelAddButton')
        ->toContain('settings.contains(parentItem)')
        ->toContain('touchesSettings')
        ->toContain('scheduleRestore();')
        ->toContain('}), 20);')
        ->toContain('rememberScroll')
        ->toContain('restoreScroll')
        ->toContain("['click', 'change', 'input']")
        ->toContain('root.dataset.cardPreviewOriginalOrder = current.join')
        ->toContain("return current.join(',') !== original.join(',')")
        ->toContain('wireModelName')
        ->toContain('syncLivewireModel')
        ->toContain('component.set(model, value, false)')
        ->toContain('eventElement')
        ->toContain('aria-disabled="true"')
        ->toContain('data-card-preview-sort-save-method')
        ->toContain('onclick="{$inlineSaveAction}"')
        ->toContain('$inlineSaveAction = e')
        ->toContain("button.setAttribute('aria-disabled'")
        ->toContain('saveSortOrder(root, button)')
        ->toContain('savingLabel')
        ->toContain('savedLabel')
        ->toContain('failedLabel')
        ->toContain('sortSaveMethod')
        ->toContain('button.textContent = savingLabel')
        ->toContain("root.dispatchEvent(new CustomEvent('shopweb-card-preview-save-sort'")
        ->toContain('currentSortOrder')
        ->toContain('component.call(method, order)')
        ->toContain("document.addEventListener('shopweb-card-preview-sort-saved'")
        ->toContain('movePlaceholdersLast')
        ->toContain('insertionCardForPoint')
        ->toContain("document.addEventListener('submit'")
        ->not->toContain('syncSortInputs(root, false);'.PHP_EOL.'                        dragState.card = null');

    $walletPageSource = file_get_contents(app_path('Filament/Pages/WalletSettingsPage.php'));

    expect($walletPageSource)
        ->toContain("->key('_recharge_page_preview')")
        ->toContain("->key('recharge_options')")
        ->toContain("->partiallyRenderComponentsAfterStateUpdated(['_recharge_page_preview'])")
        ->toContain("->partiallyRenderComponentsAfterStateUpdated(['coupon_reward_rules'])")
        ->toContain('->skipRenderAfterStateUpdated()')
        ->toContain("->key('coupon_reward_rules')")
        ->toContain('orderedRechargeOptionPreviewEntries')
        ->toContain("->reject(fn (array \$entry): bool => static::isBlankRechargeOptionData(\$entry['state']))")
        ->toContain("'sortSaveMethod' => 'saveRechargeOptionSortOrder'")
        ->toContain("'legacySaveAttributes' => 'data-wallet-recharge-save-order'")
        ->toContain('data-card-preview-sort-key')
        ->toContain('data-wallet-recharge-option-id')
        ->toContain('syncRechargeOptionStateOrder')
        ->toContain('$this->skipRender();');

    $this->actingAs($admin)
        ->get(\App\Filament\Resources\WalletRedeemCodeResource::getUrl('index'))
        ->assertOk()
        ->assertSee('钱包设置')
        ->assertSee('兑换码管理');

    $this->actingAs($admin)
        ->get(\App\Filament\Resources\FlashSaleResource::getUrl('index'))
        ->assertOk()
        ->assertSee('秒杀活动')
        ->assertSee('秒杀计划');

    $this->actingAs($admin)
        ->get(\App\Filament\Resources\FlashSaleCampaignResource::getUrl('index'))
        ->assertOk()
        ->assertSee('秒杀活动')
        ->assertSee('秒杀计划');

    $this->actingAs($admin)
        ->get(\App\Filament\Resources\ProductCommentResource::getUrl('index'))
        ->assertOk()
        ->assertSee('商品评论')
        ->assertSee('页面评论')
        ->assertSee('公告评论')
        ->assertSee('论坛回复');
});

it('persists wallet recharge option preview drag sort order', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $first = WalletRechargeOption::query()->create([
        'name' => 'First recharge option',
        'currency_code' => 'CNY',
        'currency_unit' => 'yuan',
        'amount_cents' => 1000,
        'discount_percent' => null,
        'bonus_cents' => 0,
        'is_active' => true,
        'sort_order' => 10,
    ]);
    $second = WalletRechargeOption::query()->create([
        'name' => 'Second recharge option',
        'currency_code' => 'CNY',
        'currency_unit' => 'yuan',
        'amount_cents' => 2000,
        'discount_percent' => null,
        'bonus_cents' => 0,
        'is_active' => true,
        'sort_order' => 20,
    ]);

    $component = Livewire::actingAs($admin)
        ->test(\App\Filament\Pages\WalletSettingsPage::class)
        ->call('saveRechargeOptionSortOrder', [$second->id, $first->id])
        ->assertHasNoErrors();

    expect(collect($component->instance()->data['recharge_options'])
        ->reject(fn (array $option): bool => empty($option['id']))
        ->pluck('id')
        ->all())
        ->toBe([$second->id, $first->id]);

    $component
        ->call('save')
        ->assertHasNoErrors();

    expect($second->fresh()->sort_order)->toBe(10)
        ->and($first->fresh()->sort_order)->toBe(20)
        ->and(WalletRechargeOption::query()->orderBy('sort_order')->pluck('id')->all())
        ->toBe([$second->id, $first->id]);

    $component = Livewire::actingAs($admin)
        ->test(\App\Filament\Pages\WalletSettingsPage::class)
        ->call('saveRechargeOptionSortOrder', [$first->id, $second->id])
        ->assertHasNoErrors();

    expect(collect($component->instance()->data['recharge_options'])
        ->reject(fn (array $option): bool => empty($option['id']))
        ->pluck('id')
        ->all())
        ->toBe([$first->id, $second->id]);

    $component
        ->call('save')
        ->assertHasNoErrors();

    expect($first->fresh()->sort_order)->toBe(10)
        ->and($second->fresh()->sort_order)->toBe(20)
        ->and(WalletRechargeOption::query()->orderBy('sort_order')->pluck('id')->all())
        ->toBe([$first->id, $second->id]);
});

it('removes persisted blank wallet recharge options when wallet settings are saved', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $valid = WalletRechargeOption::query()->create([
        'name' => 'Keep recharge option',
        'currency_code' => 'CNY',
        'currency_unit' => 'yuan',
        'amount_cents' => 1000,
        'discount_percent' => null,
        'bonus_cents' => 0,
        'is_active' => true,
    ]);
    $blank = WalletRechargeOption::query()->create([
        'name' => null,
        'currency_code' => 'CNY',
        'currency_unit' => 'yuan',
        'amount_cents' => 0,
        'discount_percent' => null,
        'bonus_cents' => 0,
        'is_active' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(\App\Filament\Pages\WalletSettingsPage::class)
        ->call('save')
        ->assertHasNoErrors();

    expect(WalletRechargeOption::query()->whereKey($valid->id)->exists())->toBeTrue()
        ->and(WalletRechargeOption::query()->whereKey($blank->id)->exists())->toBeFalse();
});

it('persists edited wallet recharge option settings', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $option = WalletRechargeOption::query()->create([
        'name' => 'Old recharge option',
        'currency_code' => 'CNY',
        'currency_unit' => 'yuan',
        'amount_cents' => 1000,
        'discount_percent' => null,
        'bonus_cents' => 0,
        'is_active' => true,
    ]);

    $component = Livewire::actingAs($admin)->test(\App\Filament\Pages\WalletSettingsPage::class);
    $state = $component->instance()->data['recharge_options'];
    $key = array_key_first($state);

    $component
        ->set("data.recharge_options.{$key}.name", 'Updated recharge option')
        ->set("data.recharge_options.{$key}.discount_percent", 80)
        ->call('save')
        ->assertHasNoErrors();

    expect($option->fresh())
        ->name->toBe('Updated recharge option')
        ->discount_percent->toBe(80);
});

it('renders resource library forum logs and admin role defaults for admins', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    MediaAsset::query()->create([
        'name' => 'Forum File',
        'path' => 'forum/file.txt',
        'disk' => 'public_uploads',
        'mime_type' => 'text/plain',
        'usage' => MediaAsset::USAGE_FORUM,
        'library' => MediaAsset::LIBRARY_FORUM_USER,
    ]);
    MediaAsset::query()->create([
        'name' => 'Site Image',
        'path' => 'media/site.png',
        'disk' => 'public_uploads',
        'mime_type' => 'image/png',
        'usage' => MediaAsset::USAGE_GENERAL,
        'library' => MediaAsset::LIBRARY_SITE,
    ]);

    $this->actingAs($admin)
        ->get('/admin/resource-assets')
        ->assertOk()
        ->assertSee('Forum File')
        ->assertSee('网站资源文件')
        ->assertSee('论坛用户资源文件');

    $this->actingAs($admin)
        ->get('/admin/media-assets')
        ->assertOk()
        ->assertSee('Site Image')
        ->assertDontSee('Forum File');

    $this->actingAs($admin)
        ->get('/admin/forum-activity-logs')
        ->assertOk()
        ->assertSee('论坛操作记录');

    $this->actingAs($admin)
        ->get('/admin/admin-users/create')
        ->assertOk()
        ->assertSee('超级管理员')
        ->assertDontSee('会员用户');
});

it('renders backend and frontend user edit forms by database id for legacy public ids', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $legacyAdmin = User::factory()->create(['role' => 'admin']);
    $legacyCustomer = User::factory()->create(['role' => 'customer']);

    $legacyAdmin->forceFill(['public_id' => null])->saveQuietly();
    $legacyCustomer->forceFill(['public_id' => null])->saveQuietly();

    $this->actingAs($legacyAdmin)
        ->get(route('user.section', 'profile'))
        ->assertOk()
        ->assertSee('个人资料');

    $this->actingAs($legacyCustomer)
        ->get(route('user.section', 'profile'))
        ->assertOk()
        ->assertSee('个人资料');

    $legacyAdmin->refresh();
    $legacyCustomer->refresh();

    expect($legacyAdmin->public_id)->toStartWith('staff_')
        ->and($legacyCustomer->public_id)->toStartWith('user_');

    $this->actingAs($admin)
        ->get("/admin/admin-users/{$legacyAdmin->public_id}/edit")
        ->assertOk()
        ->assertSee('头像')
        ->assertSee('个人简介');

    $this->actingAs($admin)
        ->get("/admin/admin-users/{$legacyAdmin->id}/edit")
        ->assertOk()
        ->assertSee('后台用户 ID');

    $this->actingAs($admin)
        ->get("/admin/customers/{$legacyCustomer->public_id}/edit")
        ->assertOk()
        ->assertSee('头像')
        ->assertSee('个人简介');

    $this->actingAs($admin)
        ->get("/admin/customers/{$legacyCustomer->id}/edit")
        ->assertOk()
        ->assertSee('用户 ID');
});

it('namespaces backoffice public ids away from customer ids', function (): void {
    $admin = User::factory()->create(['role' => 'admin', 'public_id' => 'plain_admin']);
    $operator = User::factory()->create(['role' => 'operator', 'public_id' => 'operator_plain']);
    $customer = User::factory()->create(['role' => 'customer', 'public_id' => 'customer_plain']);

    expect($admin->fresh()->public_id)->toStartWith('staff_')
        ->and($operator->fresh()->public_id)->toStartWith('staff_')
        ->and($customer->fresh()->public_id)->toBe('customer_plain');
});

it('configures event reward generated coupons instead of requiring manual coupon selection', function (): void {
    $source = file_get_contents(app_path('Filament/Resources/ReferralRewardRuleResource.php'));

    expect($source)
        ->toContain("Toggle::make('coupon_reward_enabled')")
        ->toContain("Repeater::make('coupon_reward_rules')")
        ->toContain("Select::make('coupon_id')")
        ->toContain('->hidden()')
        ->not->toContain("->label('自动发放优惠码')");
});
