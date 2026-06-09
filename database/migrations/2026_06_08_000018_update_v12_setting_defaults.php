<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SiteSetting::query()->update([
            'primary_color' => '#7CBFE2',
            'accent_color' => '#F2A8BE',
            'background_color' => '#FFF9FC',
            'show_order_numbers_to_users' => false,
            'show_tracking_numbers_to_users' => false,
            'default_locale_mode' => 'system',
            'enabled_locales' => json_encode(['zh_CN', 'en', 'ja', 'ko', 'fr']),
            'shipping_mail_subject' => '你的订单已发货',
            'page_music_enabled' => false,
            'page_music_mode' => 'manual',
            'guide_pet_enabled' => false,
            'guide_pet_context_mode' => 'storefront',
        ]);
    }

    public function down(): void
    {
        SiteSetting::query()->update([
            'primary_color' => '#2D9CDB',
            'accent_color' => '#F5A9B8',
            'background_color' => '#FFF7FB',
        ]);
    }
};
