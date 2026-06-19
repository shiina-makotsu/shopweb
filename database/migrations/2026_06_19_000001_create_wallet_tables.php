<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'wallet_balance_cents')) {
                $table->unsignedInteger('wallet_balance_cents')->default(0)->after('ai_quota_k');
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'wallet_payment_cents')) {
                $table->unsignedInteger('wallet_payment_cents')->default(0)->after('shipping_fee_cents');
            }

            if (! Schema::hasColumn('orders', 'wallet_recharge_cents')) {
                $table->unsignedInteger('wallet_recharge_cents')->default(0)->after('wallet_payment_cents');
            }

            if (! Schema::hasColumn('orders', 'is_wallet_recharge')) {
                $table->boolean('is_wallet_recharge')->default(false)->index()->after('wallet_recharge_cents');
            }
        });

        Schema::table('site_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('site_settings', 'wallet_recharge_success_message')) {
                $table->text('wallet_recharge_success_message')->nullable()->after('payment_fallback_config');
            }
        });

        if (! Schema::hasTable('wallet_redeem_codes')) {
            Schema::create('wallet_redeem_codes', function (Blueprint $table): void {
                $table->id();
                $table->string('code')->unique();
                $table->string('name')->nullable();
                $table->unsignedInteger('amount_cents');
                $table->unsignedInteger('usage_limit')->default(1);
                $table->unsignedInteger('redeemed_count')->default(0);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('wallet_transactions')) {
            Schema::create('wallet_transactions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('wallet_redeem_code_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type')->index();
                $table->integer('amount_cents');
                $table->unsignedInteger('balance_after_cents')->default(0);
                $table->string('source')->default('system')->index();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallet_redeem_codes');

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'wallet_balance_cents')) {
                $table->dropColumn('wallet_balance_cents');
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('orders', 'wallet_payment_cents') ? 'wallet_payment_cents' : null,
                Schema::hasColumn('orders', 'wallet_recharge_cents') ? 'wallet_recharge_cents' : null,
                Schema::hasColumn('orders', 'is_wallet_recharge') ? 'is_wallet_recharge' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('site_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('site_settings', 'wallet_recharge_success_message')) {
                $table->dropColumn('wallet_recharge_success_message');
            }
        });
    }
};
