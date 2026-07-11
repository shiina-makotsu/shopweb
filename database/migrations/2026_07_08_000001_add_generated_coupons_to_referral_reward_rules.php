<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_reward_rules', function (Blueprint $table): void {
            if (! Schema::hasColumn('referral_reward_rules', 'coupon_reward_enabled')) {
                $table->boolean('coupon_reward_enabled')->default(false)->after('coupon_id')->index();
            }

            if (! Schema::hasColumn('referral_reward_rules', 'coupon_reward_rules')) {
                $table->json('coupon_reward_rules')->nullable()->after('coupon_reward_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('referral_reward_rules', function (Blueprint $table): void {
            if (Schema::hasColumn('referral_reward_rules', 'coupon_reward_rules')) {
                $table->dropColumn('coupon_reward_rules');
            }

            if (Schema::hasColumn('referral_reward_rules', 'coupon_reward_enabled')) {
                $table->dropColumn('coupon_reward_enabled');
            }
        });
    }
};
