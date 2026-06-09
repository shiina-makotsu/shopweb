<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\DeliveryStatus;
use App\Models\Manufacturer;
use App\Models\OrderStatusSetting;
use App\Models\Page;
use App\Models\PriceVoteOption;
use App\Models\Product;
use App\Models\ProductTag;
use App\Models\ProductVariant;
use App\Models\QuantityUnit;
use App\Models\SiteSetting;
use App\Models\SoldOutStatus;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        SiteSetting::query()->firstOrCreate([], [
            'site_name' => 'ShopWeb',
            'store_email' => 'admin@example.com',
            'store_timezone' => 'Asia/Shanghai',
            'store_currency' => 'CNY',
            'theme_template' => 'default',
            'primary_color' => '#7CBFE2',
            'accent_color' => '#F2A8BE',
            'background_color' => '#FFF9FC',
            'button_radius' => 'sm',
            'product_card_density' => 'comfortable',
            'home_welcome_enabled' => true,
            'show_order_numbers_to_users' => false,
            'show_tracking_numbers_to_users' => true,
            'default_locale_mode' => 'system',
            'enabled_locales' => ['zh_CN', 'en', 'ja', 'ko', 'fr'],
            'shipping_mail_subject' => '你的订单已发货',
            'page_music_enabled' => false,
            'page_music_mode' => 'manual',
            'guide_pet_enabled' => false,
            'guide_pet_context_mode' => 'storefront',
            'payment_gateway_provider' => 'manual',
            'payment_enabled_methods' => ['alipay_qr'],
            'home_title' => '轻量自研商城',
            'home_content' => '这是一个基于 Laravel + Filament 的极简交易系统。',
            'contact_info' => '请在后台站点设置中填写联系方式。',
            'payment_instructions' => '请在后台站点设置中填写付款账号、转账备注和审核说明。',
        ]);

        OrderStatusSetting::seedDefaults();

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'public_id' => 'admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'account_type' => 'regular',
            ],
        );

        if (blank($admin->public_id)) {
            $admin->forceFill(['public_id' => 'admin'])->save();
        }

        $category = Category::query()->firstOrCreate(
            ['slug' => 'demo'],
            ['name' => '演示分类', 'is_active' => true],
        );

        $manufacturer = Manufacturer::query()->firstOrCreate(
            ['slug' => 'demo-manufacturer'],
            ['name' => '演示制造商', 'is_active' => true],
        );

        $supplier = Supplier::query()->firstOrCreate(
            ['code' => 'DEMO_SUPPLIER'],
            ['name' => '演示供应商', 'is_active' => true],
        );

        $deliveryStatus = DeliveryStatus::query()->firstOrCreate(
            ['code' => 'contact_after_payment'],
            ['name' => '付款后联系交付', 'description' => '人工确认付款后联系客户交付', 'is_active' => true],
        );

        $soldOutStatus = SoldOutStatus::query()->firstOrCreate(
            ['code' => 'show_sold_out'],
            ['name' => '展示售罄', 'behavior' => SoldOutStatus::BEHAVIOR_SHOW, 'is_active' => true],
        );

        $quantityUnit = QuantityUnit::query()->firstOrCreate(
            ['code' => 'piece'],
            ['name' => '件', 'precision' => 0, 'is_active' => true],
        );

        $product = Product::query()->firstOrCreate(
            ['slug' => 'demo-product'],
            [
                'category_id' => $category->id,
                'manufacturer_id' => $manufacturer->id,
                'supplier_id' => $supplier->id,
                'title' => '演示商品',
                'summary' => '一个用于验证下单流程的演示商品。',
                'description' => '<p>管理员可以在后台修改商品详情、SKU、库存和图片。</p>',
                'status' => Product::STATUS_PUBLISHED,
                'is_featured' => true,
                'fulfillment_type' => Product::FULFILLMENT_CONTACT,
                'delivery_status_id' => $deliveryStatus->id,
                'sold_out_status_id' => $soldOutStatus->id,
                'quantity_unit_id' => $quantityUnit->id,
            ],
        );

        ProductVariant::query()->firstOrCreate(
            ['sku' => 'DEMO-001'],
            [
                'product_id' => $product->id,
                'specs' => ['版本' => '标准版'],
                'price_cents' => 9900,
                'stock' => 20,
                'is_active' => true,
            ],
        );

        $tag = ProductTag::query()->firstOrCreate(
            ['slug' => 'demo-tag'],
            [
                'name' => '演示标签',
                'meta_title' => '演示标签商品',
                'meta_description' => '用于验证商品标签分页与搜索的演示标签。',
                'is_active' => true,
            ],
        );

        $product->tags()->syncWithoutDetaching([$tag->id]);

        foreach ([
            ['label' => '¥50 - ¥99', 'min_cents' => 5000, 'max_cents' => 9900],
            ['label' => '¥100 - ¥199', 'min_cents' => 10000, 'max_cents' => 19900],
            ['label' => '¥200 以上', 'min_cents' => 20000, 'max_cents' => null],
        ] as $index => $option) {
            PriceVoteOption::query()->firstOrCreate(
                ['product_id' => $product->id, 'label' => $option['label']],
                [...$option, 'sort_order' => $index],
            );
        }

        Page::query()->firstOrCreate(
            ['slug' => 'about'],
            [
                'title' => '关于我们',
                'body' => '<p>这是一个简单页面示例。</p>',
                'is_published' => true,
            ],
        );
    }
}
