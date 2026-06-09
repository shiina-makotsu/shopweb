<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_media', function (Blueprint $table): void {
            $table->string('media_kind')->default('image')->after('type')->index();
            $table->string('mime_type')->nullable()->after('path');
        });

        Schema::create('flash_sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->json('product_variant_ids')->nullable();
            $table->string('name');
            $table->unsignedInteger('sale_price_cents');
            $table->unsignedInteger('quantity_limit');
            $table->unsignedInteger('sold_quantity')->default(0);
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('flash_sale_id')->nullable()->after('incoming_product_id')->constrained('flash_sales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('flash_sale_id');
        });

        Schema::dropIfExists('flash_sales');

        Schema::table('product_media', function (Blueprint $table): void {
            $table->dropColumn(['media_kind', 'mime_type']);
        });
    }
};
