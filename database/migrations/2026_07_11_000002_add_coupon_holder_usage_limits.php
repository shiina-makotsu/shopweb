<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_coupons', function (Blueprint $table): void {
            $table->timestamp('exhausted_at')->nullable()->after('claimed_at')->index();
        });

        DB::table('coupons')->orderBy('id')->each(function (object $coupon): void {
            $holderCount = DB::table('user_coupons')->where('coupon_id', $coupon->id)->count();
            $usedCount = DB::table('coupon_redemptions')
                ->where('coupon_id', $coupon->id)
                ->whereIn('status', ['reserved', 'confirmed'])
                ->count();
            $perUserLimit = max(1, (int) ($coupon->per_user_limit ?? 1));
            $minimumTotal = max(1, $usedCount, $perUserLimit * $holderCount);

            DB::table('coupons')->where('id', $coupon->id)->update([
                'usage_limit' => max((int) ($coupon->usage_limit ?? 0), $minimumTotal),
                'per_user_limit' => $perUserLimit,
            ]);
        });

        Schema::table('coupons', function (Blueprint $table): void {
            $table->unsignedInteger('usage_limit')->default(1)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            $table->unsignedInteger('usage_limit')->nullable()->default(null)->change();
        });

        Schema::table('user_coupons', function (Blueprint $table): void {
            $table->dropIndex(['exhausted_at']);
            $table->dropColumn('exhausted_at');
        });
    }
};
