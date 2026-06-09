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
    $this->actingAs($finance)->get('/admin/products')->assertForbidden();

    $this->actingAs($warehouse)->get('/admin/products')->assertOk();
    $this->actingAs($warehouse)->get('/admin/inventory-movements')->assertOk();
    $this->actingAs($warehouse)->get('/admin/orders')->assertForbidden();

    $this->actingAs($sales)->get('/admin/orders')->assertOk();
    $this->actingAs($sales)->get('/admin/products')->assertForbidden();

    $this->actingAs($purchasing)->get('/admin/products')->assertOk();
    $this->actingAs($purchasing)->get('/admin/resource-assets')->assertOk();
    $this->actingAs($purchasing)->get('/admin/orders')->assertForbidden();

    $this->actingAs($support)->get('/admin/forum-activity-logs')->assertOk();
    $this->actingAs($support)->get('/admin/support-tickets')->assertOk();
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
        ->assertSee('上传新封面图');
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
