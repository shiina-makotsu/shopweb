<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_status_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('order_status_settings', 'icon')) {
                $table->string('icon')->nullable()->after('label');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_status_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('order_status_settings', 'icon')) {
                $table->dropColumn('icon');
            }
        });
    }
};
