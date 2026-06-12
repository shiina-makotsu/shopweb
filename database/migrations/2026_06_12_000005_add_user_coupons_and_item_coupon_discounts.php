<?php

use App\Models\Coupon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coupon_product')) {
            Schema::create('coupon_product', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['coupon_id', 'product_id']);
            });
        }

        if (Schema::hasColumn('coupons', 'product_id')) {
            Coupon::query()
                ->whereNotNull('product_id')
                ->each(function (Coupon $coupon): void {
                    DB::table('coupon_product')->updateOrInsert([
                        'coupon_id' => $coupon->id,
                        'product_id' => $coupon->product_id,
                    ], [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
        }

        if (! Schema::hasTable('user_coupons')) {
            Schema::create('user_coupons', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
                $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('after_sales_request_id')->nullable()->constrained()->nullOnDelete();
                $table->string('source')->default('claimed')->index();
                $table->timestamp('claimed_at')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'coupon_id']);
            });
        }

        Schema::table('order_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('order_items', 'coupon_id')) {
                $table->foreignId('coupon_id')->nullable()->after('flash_sale_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('order_items', 'coupon_code')) {
                $table->string('coupon_code')->nullable()->after('coupon_id');
            }

            if (! Schema::hasColumn('order_items', 'discount_cents')) {
                $table->unsignedInteger('discount_cents')->default(0)->after('coupon_code');
            }
        });

        Schema::table('coupon_redemptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('coupon_redemptions', 'order_item_id')) {
                $table->foreignId('order_item_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('coupon_redemptions', 'user_coupon_id')) {
                $table->foreignId('user_coupon_id')->nullable()->after('order_item_id')->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('coupon_redemptions', function (Blueprint $table): void {
            if (Schema::hasColumn('coupon_redemptions', 'user_coupon_id')) {
                $table->dropConstrainedForeignId('user_coupon_id');
            }

            if (Schema::hasColumn('coupon_redemptions', 'order_item_id')) {
                $table->dropConstrainedForeignId('order_item_id');
            }
        });

        Schema::table('order_items', function (Blueprint $table): void {
            if (Schema::hasColumn('order_items', 'discount_cents')) {
                $table->dropColumn('discount_cents');
            }

            if (Schema::hasColumn('order_items', 'coupon_code')) {
                $table->dropColumn('coupon_code');
            }

            if (Schema::hasColumn('order_items', 'coupon_id')) {
                $table->dropConstrainedForeignId('coupon_id');
            }
        });

        Schema::dropIfExists('user_coupons');
        Schema::dropIfExists('coupon_product');
    }
};
