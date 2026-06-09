<?php

use App\Models\Coupon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            if (! Schema::hasColumn('coupons', 'scope')) {
                $table->string('scope')->default(Coupon::SCOPE_GLOBAL)->after('value')->index();
            }

            if (! Schema::hasColumn('coupons', 'product_id')) {
                $table->foreignId('product_id')->nullable()->after('scope')->constrained()->nullOnDelete();
            }
        });

        if (! Schema::hasTable('user_addresses')) {
            Schema::create('user_addresses', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('recipient_name');
                $table->string('phone', 60);
                $table->string('country');
                $table->string('province');
                $table->string('city');
                $table->string('district');
                $table->string('street')->nullable();
                $table->text('raw_text')->nullable();
                $table->boolean('is_default')->default(false)->index();
                $table->boolean('is_visible')->default(false)->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_addresses');

        Schema::table('coupons', function (Blueprint $table): void {
            if (Schema::hasColumn('coupons', 'product_id')) {
                $table->dropConstrainedForeignId('product_id');
            }

            if (Schema::hasColumn('coupons', 'scope')) {
                $table->dropColumn('scope');
            }
        });
    }
};
