<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table): void {
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->foreignId('after_sales_request_id')->nullable()->unique()
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table): void {
            $table->dropForeign(['after_sales_request_id']);
            $table->dropUnique(['after_sales_request_id']);
            $table->dropColumn('after_sales_request_id');
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
