<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->longText('digital_delivery_content')->nullable()->after('tracking_url');
            $table->string('digital_delivery_code')->nullable()->after('digital_delivery_content');
            $table->json('digital_delivery_attachment_paths')->nullable()->after('digital_delivery_code');
            $table->timestamp('digital_delivery_sent_at')->nullable()->after('digital_delivery_attachment_paths');
            $table->timestamp('digital_delivery_viewed_at')->nullable()->after('digital_delivery_sent_at');
            $table->timestamp('digital_delivery_completed_at')->nullable()->after('digital_delivery_viewed_at');
        });

        Schema::table('site_settings', function (Blueprint $table): void {
            $table->boolean('support_ai_enabled')->default(false)->after('guide_pet_context_mode');
            $table->string('support_ai_endpoint')->nullable()->after('support_ai_enabled');
            $table->string('support_ai_api_key')->nullable()->after('support_ai_endpoint');
            $table->string('support_ai_model')->nullable()->after('support_ai_api_key');
            $table->text('support_ai_system_prompt')->nullable()->after('support_ai_model');
            $table->unsignedInteger('support_ai_idle_minutes')->default(10)->after('support_ai_system_prompt');
        });

        Schema::create('friend_links', function (Blueprint $table): void {
            $table->id();
            $table->string('site_name');
            $table->string('url');
            $table->string('image_path')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('navigation_menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('navigation_menu_items')->cascadeOnDelete();
            $table->string('label');
            $table->string('url')->nullable();
            $table->string('route_name')->nullable();
            $table->json('route_parameters')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('opens_new_tab')->default(false);
            $table->timestamps();
        });

        Schema::create('support_quick_replies', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->longText('body');
            $table->string('category')->nullable()->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_quick_replies');
        Schema::dropIfExists('navigation_menu_items');
        Schema::dropIfExists('friend_links');

        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'support_ai_enabled',
                'support_ai_endpoint',
                'support_ai_api_key',
                'support_ai_model',
                'support_ai_system_prompt',
                'support_ai_idle_minutes',
            ]);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'digital_delivery_content',
                'digital_delivery_code',
                'digital_delivery_attachment_paths',
                'digital_delivery_sent_at',
                'digital_delivery_viewed_at',
                'digital_delivery_completed_at',
            ]);
        });
    }
};
