<?php

use App\Models\Coupon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coupons')) {
            return;
        }

        $globalCouponIds = DB::table('coupons')
            ->where('scope', Coupon::SCOPE_GLOBAL)
            ->pluck('id');

        if (Schema::hasColumn('coupons', 'product_id')) {
            DB::table('coupons')
                ->where('scope', Coupon::SCOPE_GLOBAL)
                ->whereNotNull('product_id')
                ->update(['product_id' => null]);
        }

        if (Schema::hasTable('coupon_product') && $globalCouponIds->isNotEmpty()) {
            DB::table('coupon_product')
                ->whereIn('coupon_id', $globalCouponIds)
                ->delete();
        }
    }

    public function down(): void
    {
        // Data cleanup only; there is no reliable way to restore stale product links.
    }
};
