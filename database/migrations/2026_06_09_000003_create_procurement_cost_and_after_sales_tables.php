<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('incoming_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('purchase_amount_cents')->default(0);
            $table->unsignedInteger('shipping_amount_cents')->default(0);
            $table->string('shipping_country')->nullable()->index();
            $table->decimal('customs_tax_rate', 8, 4)->default(0);
            $table->unsignedInteger('customs_tax_cents')->default(0);
            $table->string('international_tracking_number')->nullable()->index();
            $table->string('tracking_url')->nullable();
            $table->text('note')->nullable();
            $table->string('status')->default('incoming')->index();
            $table->timestamps();
        });

        Schema::create('procurement_user_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('presale_quantity')->default(0);
            $table->unsignedInteger('allocated_quantity')->default(0);
            $table->timestamps();
            $table->unique(['procurement_id', 'order_item_id']);
        });

        Schema::create('cost_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('procurement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category')->index();
            $table->string('name');
            $table->unsignedInteger('amount_cents')->default(0);
            $table->string('country')->nullable()->index();
            $table->decimal('tax_rate', 8, 4)->nullable();
            $table->boolean('is_auto')->default(false)->index();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('after_sales_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('support_ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('support')->index();
            $table->string('status')->default('open')->index();
            $table->string('subject');
            $table->longText('message');
            $table->text('admin_note')->nullable();
            $table->string('resolution_type')->nullable()->index();
            $table->unsignedInteger('refund_amount_cents')->nullable();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->foreignId('order_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('order_id');
        });

        Schema::dropIfExists('after_sales_requests');
        Schema::dropIfExists('cost_entries');
        Schema::dropIfExists('procurement_user_allocations');
        Schema::dropIfExists('procurements');
    }
};
