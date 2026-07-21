<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->integer('wallet_balance_cents')->default(0)->change();
        });

        Schema::table('wallet_transactions', function (Blueprint $table): void {
            $table->integer('balance_after_cents')->default(0)->change();
        });
    }

    public function down(): void
    {
        DB::table('users')->where('wallet_balance_cents', '<', 0)->update(['wallet_balance_cents' => 0]);
        DB::table('wallet_transactions')->where('balance_after_cents', '<', 0)->update(['balance_after_cents' => 0]);

        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('wallet_balance_cents')->default(0)->change();
        });

        Schema::table('wallet_transactions', function (Blueprint $table): void {
            $table->unsignedInteger('balance_after_cents')->default(0)->change();
        });
    }
};
