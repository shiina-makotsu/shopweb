<?php

use App\Models\Coupon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'wallet_recharge_option_id')) {
                $table->foreignId('wallet_recharge_option_id')
                    ->nullable()
                    ->after('is_wallet_recharge')
                    ->constrained('wallet_recharge_options')
                    ->nullOnDelete();
            }
        });

        Schema::table('wallet_recharge_options', function (Blueprint $table): void {
            if (! Schema::hasColumn('wallet_recharge_options', 'coupon_reward_enabled')) {
                $table->boolean('coupon_reward_enabled')->default(false)->after('sort_order')->index();
            }

            if (! Schema::hasColumn('wallet_recharge_options', 'coupon_reward_currency_code')) {
                $table->string('coupon_reward_currency_code', 12)->default('CNY')->after('coupon_reward_enabled');
            }

            if (! Schema::hasColumn('wallet_recharge_options', 'coupon_reward_currency_unit')) {
                $table->string('coupon_reward_currency_unit', 24)->default('yuan')->after('coupon_reward_currency_code');
            }

            if (! Schema::hasColumn('wallet_recharge_options', 'coupon_reward_type')) {
                $table->string('coupon_reward_type')->default(Coupon::TYPE_FIXED)->after('coupon_reward_currency_unit');
            }

            if (! Schema::hasColumn('wallet_recharge_options', 'coupon_reward_value')) {
                $table->unsignedInteger('coupon_reward_value')->default(0)->after('coupon_reward_type');
            }

            if (! Schema::hasColumn('wallet_recharge_options', 'coupon_reward_valid_days')) {
                $table->unsignedInteger('coupon_reward_valid_days')->nullable()->after('coupon_reward_value');
            }

            if (! Schema::hasColumn('wallet_recharge_options', 'coupon_reward_scope')) {
                $table->string('coupon_reward_scope')->default(Coupon::SCOPE_GLOBAL)->after('coupon_reward_valid_days');
            }

            if (! Schema::hasColumn('wallet_recharge_options', 'coupon_reward_product_ids')) {
                $table->json('coupon_reward_product_ids')->nullable()->after('coupon_reward_scope');
            }

            if (! Schema::hasColumn('wallet_recharge_options', 'coupon_reward_minimum_order_cents')) {
                $table->unsignedInteger('coupon_reward_minimum_order_cents')->default(0)->after('coupon_reward_product_ids');
            }

            if (! Schema::hasColumn('wallet_recharge_options', 'coupon_reward_quantity')) {
                $table->unsignedInteger('coupon_reward_quantity')->default(1)->after('coupon_reward_minimum_order_cents');
            }

            if (! Schema::hasColumn('wallet_recharge_options', 'coupon_reward_usage_limit')) {
                $table->unsignedInteger('coupon_reward_usage_limit')->default(1)->after('coupon_reward_quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wallet_recharge_options', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('wallet_recharge_options', 'coupon_reward_usage_limit') ? 'coupon_reward_usage_limit' : null,
                Schema::hasColumn('wallet_recharge_options', 'coupon_reward_quantity') ? 'coupon_reward_quantity' : null,
                Schema::hasColumn('wallet_recharge_options', 'coupon_reward_minimum_order_cents') ? 'coupon_reward_minimum_order_cents' : null,
                Schema::hasColumn('wallet_recharge_options', 'coupon_reward_product_ids') ? 'coupon_reward_product_ids' : null,
                Schema::hasColumn('wallet_recharge_options', 'coupon_reward_scope') ? 'coupon_reward_scope' : null,
                Schema::hasColumn('wallet_recharge_options', 'coupon_reward_valid_days') ? 'coupon_reward_valid_days' : null,
                Schema::hasColumn('wallet_recharge_options', 'coupon_reward_value') ? 'coupon_reward_value' : null,
                Schema::hasColumn('wallet_recharge_options', 'coupon_reward_type') ? 'coupon_reward_type' : null,
                Schema::hasColumn('wallet_recharge_options', 'coupon_reward_currency_unit') ? 'coupon_reward_currency_unit' : null,
                Schema::hasColumn('wallet_recharge_options', 'coupon_reward_currency_code') ? 'coupon_reward_currency_code' : null,
                Schema::hasColumn('wallet_recharge_options', 'coupon_reward_enabled') ? 'coupon_reward_enabled' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'wallet_recharge_option_id')) {
                $table->dropConstrainedForeignId('wallet_recharge_option_id');
            }
        });
    }
};
