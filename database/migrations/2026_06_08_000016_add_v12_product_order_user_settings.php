<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('source_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedInteger('incoming_quantity')->default(0);
            $table->text('incoming_note')->nullable();
            $table->foreignId('shipping_carrier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tracking_number')->nullable();
            $table->string('tracking_url', 500)->nullable();
            $table->boolean('crowdfunding_enabled')->default(false);
            $table->unsignedInteger('crowdfunding_goal_cents')->nullable();
            $table->text('crowdfunding_reward')->nullable();
            $table->timestamp('crowdfunding_cancelled_at')->nullable();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('stock_deducted_at')->nullable();
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('product_status')->nullable()->index();
            $table->string('status')->default('pending_payment')->index();
            $table->foreignId('incoming_product_id')->nullable()->constrained('products')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('nickname')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('preferred_locale')->default('system');
            $table->json('interface_settings')->nullable();
            $table->json('privacy_settings')->nullable();
            $table->boolean('can_view_order_numbers')->default(false);
            $table->boolean('can_view_tracking_numbers')->default(false);
        });

        Schema::table('site_settings', function (Blueprint $table): void {
            $table->boolean('show_order_numbers_to_users')->default(false);
            $table->boolean('show_tracking_numbers_to_users')->default(false);
            $table->string('default_locale_mode')->default('system');
            $table->json('enabled_locales')->nullable();
            $table->string('mail_host')->nullable();
            $table->unsignedInteger('mail_port')->nullable();
            $table->string('mail_encryption')->nullable();
            $table->string('mail_username')->nullable();
            $table->string('mail_password')->nullable();
            $table->string('mail_from_address')->nullable();
            $table->string('mail_from_name')->nullable();
            $table->string('shipping_mail_subject')->nullable();
            $table->text('shipping_mail_template')->nullable();
            $table->boolean('page_music_enabled')->default(false);
            $table->string('page_music_asset_path')->nullable();
            $table->string('page_music_mode')->default('manual');
            $table->boolean('guide_pet_enabled')->default(false);
            $table->string('guide_pet_asset_path')->nullable();
            $table->string('guide_pet_api_endpoint')->nullable();
            $table->string('guide_pet_model')->nullable();
            $table->string('guide_pet_context_mode')->default('storefront');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'show_order_numbers_to_users',
                'show_tracking_numbers_to_users',
                'default_locale_mode',
                'enabled_locales',
                'mail_host',
                'mail_port',
                'mail_encryption',
                'mail_username',
                'mail_password',
                'mail_from_address',
                'mail_from_name',
                'shipping_mail_subject',
                'shipping_mail_template',
                'page_music_enabled',
                'page_music_asset_path',
                'page_music_mode',
                'guide_pet_enabled',
                'guide_pet_asset_path',
                'guide_pet_api_endpoint',
                'guide_pet_model',
                'guide_pet_context_mode',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'nickname',
                'avatar_path',
                'preferred_locale',
                'interface_settings',
                'privacy_settings',
                'can_view_order_numbers',
                'can_view_tracking_numbers',
            ]);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('incoming_product_id');
            $table->dropColumn(['product_status', 'status']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('stock_deducted_at');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_product_id');
            $table->dropConstrainedForeignId('shipping_carrier_id');
            $table->dropColumn([
                'incoming_quantity',
                'incoming_note',
                'tracking_number',
                'tracking_url',
                'crowdfunding_enabled',
                'crowdfunding_goal_cents',
                'crowdfunding_reward',
                'crowdfunding_cancelled_at',
            ]);
        });
    }
};
