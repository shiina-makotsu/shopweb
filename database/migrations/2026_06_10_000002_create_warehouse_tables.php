<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table): void {
            $table->timestamp('received_at')->nullable()->after('status');
            $table->text('warehouse_note')->nullable()->after('received_at');
        });

        Schema::create('warehouse_stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('procurement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('sku')->nullable()->index();
            $table->integer('quantity')->default(0);
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'product_variant_id']);
            $table->index(['procurement_id']);
        });

        Schema::create('warehouse_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_stock_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('procurement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->index();
            $table->integer('delta');
            $table->integer('quantity_after')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_movements');
        Schema::dropIfExists('warehouse_stocks');

        Schema::table('procurements', function (Blueprint $table): void {
            $table->dropColumn(['received_at', 'warehouse_note']);
        });
    }
};
