<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('referral_reward_rules')) {
            Schema::create('referral_reward_rules', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedInteger('wallet_amount_cents')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_reward_rules');
    }
};
