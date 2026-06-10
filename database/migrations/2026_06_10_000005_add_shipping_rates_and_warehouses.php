<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('country')->default('中国');
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('street')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        $defaultWarehouseId = DB::table('warehouses')->insertGetId([
            'name' => '默认仓库',
            'country' => '中国',
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('shipping_extra_fee_cents')->default(0)->after('sort_order');
        });

        Schema::create('warehouse_shipping_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('provinces')->nullable();
            $table->unsignedInteger('fee_cents')->default(0);
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['warehouse_id', 'is_default']);
        });

        Schema::table('warehouse_stocks', function (Blueprint $table) use ($defaultWarehouseId): void {
            $table->foreignId('warehouse_id')
                ->nullable()
                ->after('id')
                ->default($defaultWarehouseId)
                ->constrained()
                ->nullOnDelete();
            $table->index(['warehouse_id', 'product_id', 'product_variant_id']);
        });

        Schema::table('warehouse_movements', function (Blueprint $table): void {
            $table->foreignId('warehouse_id')
                ->nullable()
                ->after('warehouse_stock_id')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('procurements', function (Blueprint $table) use ($defaultWarehouseId): void {
            $table->foreignId('warehouse_id')
                ->nullable()
                ->after('created_by_id')
                ->default($defaultWarehouseId)
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('shipping_province')->nullable()->after('shipping_address');
            $table->unsignedInteger('shipping_fee_cents')->default(0)->after('discount_cents');
            $table->json('shipment_plan')->nullable()->after('shipping_fee_cents');
            $table->text('shipment_notice')->nullable()->after('shipment_plan');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('warehouse_id')
                ->nullable()
                ->after('product_variant_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('warehouse_id');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['shipping_province', 'shipping_fee_cents', 'shipment_plan', 'shipment_notice']);
        });

        Schema::table('procurements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('warehouse_id');
        });

        Schema::table('warehouse_movements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('warehouse_id');
        });

        Schema::table('warehouse_stocks', function (Blueprint $table): void {
            $table->dropIndex(['warehouse_id', 'product_id', 'product_variant_id']);
            $table->dropConstrainedForeignId('warehouse_id');
        });

        Schema::dropIfExists('warehouse_shipping_rates');
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('shipping_extra_fee_cents');
        });
        Schema::dropIfExists('warehouses');
    }
};
