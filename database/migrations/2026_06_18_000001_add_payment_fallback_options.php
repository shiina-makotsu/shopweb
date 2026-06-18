<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('site_settings', 'payment_fallback_config')) {
                $table->json('payment_fallback_config')->nullable()->after('payment_gateway_notes');
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'payment_text_proof')) {
                $table->text('payment_text_proof')->nullable()->after('payment_proof_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'payment_text_proof')) {
                $table->dropColumn('payment_text_proof');
            }
        });

        Schema::table('site_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('site_settings', 'payment_fallback_config')) {
                $table->dropColumn('payment_fallback_config');
            }
        });
    }
};
