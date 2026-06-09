<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('store_email')->nullable()->after('site_name');
            $table->string('store_phone')->nullable()->after('store_email');
            $table->string('store_address')->nullable()->after('store_phone');
            $table->string('store_tax_id')->nullable()->after('store_address');
            $table->string('store_country')->nullable()->after('store_tax_id');
            $table->string('store_timezone')->nullable()->after('store_country');
            $table->string('store_currency')->default('CNY')->after('store_timezone');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'store_email',
                'store_phone',
                'store_address',
                'store_tax_id',
                'store_country',
                'store_timezone',
                'store_currency',
            ]);
        });
    }
};
