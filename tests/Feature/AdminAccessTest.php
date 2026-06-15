<?php

use App\Models\User;
use App\Filament\Resources\PageResource\Pages\Concerns\HandlesPageCoverUpload;
use App\Models\MediaAsset;
use App\Models\OrderStatusSetting;
use App\Models\Page;
use App\Models\Product;
use App\Models\SiteSetting;

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
        ->assertSee('标签')
        ->assertSee('数量单位');
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
