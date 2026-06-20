<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('site_settings', 'payment_pending_timeout_minutes')) {
                $table->unsignedSmallInteger('payment_pending_timeout_minutes')->default(10)->after('payment_auto_check_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('site_settings', 'payment_pending_timeout_minutes')) {
                $table->dropColumn('payment_pending_timeout_minutes');
            }
        });
    }
};
