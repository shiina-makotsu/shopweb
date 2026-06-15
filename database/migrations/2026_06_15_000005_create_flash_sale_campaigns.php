<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flash_sale_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('schedule_type', 40)->default('once')->index();
            $table->date('starts_on')->nullable()->index();
            $table->date('ends_on')->nullable()->index();
            $table->time('starts_at_time')->nullable();
            $table->time('ends_at_time')->nullable();
            $table->json('month_days')->nullable();
            $table->json('week_days')->nullable();
            $table->json('year_dates')->nullable();
            $table->unsignedSmallInteger('generate_days_ahead')->default(60);
            $table->timestamp('last_generated_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('flash_sale_campaign_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('flash_sale_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->json('product_variant_ids')->nullable();
            $table->unsignedInteger('sale_price_cents');
            $table->unsignedInteger('quantity_limit');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('flash_sales', function (Blueprint $table): void {
            if (! Schema::hasColumn('flash_sales', 'flash_sale_campaign_id')) {
                $table->foreignId('flash_sale_campaign_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('flash_sales', 'flash_sale_campaign_item_id')) {
                $table->foreignId('flash_sale_campaign_item_id')->nullable()->after('flash_sale_campaign_id')->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('flash_sales', function (Blueprint $table): void {
            if (Schema::hasColumn('flash_sales', 'flash_sale_campaign_item_id')) {
                $table->dropConstrainedForeignId('flash_sale_campaign_item_id');
            }

            if (Schema::hasColumn('flash_sales', 'flash_sale_campaign_id')) {
                $table->dropConstrainedForeignId('flash_sale_campaign_id');
            }
        });

        Schema::dropIfExists('flash_sale_campaign_items');
        Schema::dropIfExists('flash_sale_campaigns');
    }
};
