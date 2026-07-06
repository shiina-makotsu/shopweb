<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wallet_recharge_options')) {
            Schema::create('wallet_recharge_options', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->string('currency_code', 12)->default('CNY');
                $table->string('currency_unit', 24)->default('yuan');
                $table->unsignedInteger('amount_cents');
                $table->unsignedTinyInteger('discount_percent')->nullable();
                $table->unsignedInteger('bonus_cents')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_recharge_options');
    }
};
