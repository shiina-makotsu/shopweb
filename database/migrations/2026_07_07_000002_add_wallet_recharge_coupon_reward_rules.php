<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_recharge_options', function (Blueprint $table): void {
            if (! Schema::hasColumn('wallet_recharge_options', 'coupon_reward_rules')) {
                $table->json('coupon_reward_rules')->nullable()->after('coupon_reward_usage_limit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wallet_recharge_options', function (Blueprint $table): void {
            if (Schema::hasColumn('wallet_recharge_options', 'coupon_reward_rules')) {
                $table->dropColumn('coupon_reward_rules');
            }
        });
    }
};
