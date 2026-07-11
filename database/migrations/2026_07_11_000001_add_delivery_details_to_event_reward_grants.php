<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_reward_grants', function (Blueprint $table): void {
            $table->string('status')->default('pending')->after('subject_id')->index();
            $table->json('coupon_ids')->nullable()->after('status');
            $table->unsignedBigInteger('wallet_amount_cents')->default(0)->after('coupon_ids');
            $table->json('reward_snapshot')->nullable()->after('wallet_amount_cents');
            $table->text('error_message')->nullable()->after('reward_snapshot');
            $table->timestamp('completed_at')->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('event_reward_grants', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn([
                'status',
                'coupon_ids',
                'wallet_amount_cents',
                'reward_snapshot',
                'error_message',
                'completed_at',
            ]);
        });
    }
};
