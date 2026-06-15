<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'shipping_city')) {
                $table->string('shipping_city')->nullable()->after('shipping_province');
            }

            if (! Schema::hasColumn('orders', 'shipping_district')) {
                $table->string('shipping_district')->nullable()->after('shipping_city');
            }

            if (! Schema::hasColumn('orders', 'shipping_street')) {
                $table->string('shipping_street')->nullable()->after('shipping_district');
            }

            if (! Schema::hasColumn('orders', 'shipping_detail')) {
                $table->string('shipping_detail')->nullable()->after('shipping_street');
            }
        });

        if (Schema::hasTable('user_addresses')) {
            Schema::table('user_addresses', function (Blueprint $table): void {
                if (! Schema::hasColumn('user_addresses', 'detail')) {
                    $table->string('detail')->nullable()->after('street');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            foreach (['shipping_detail', 'shipping_street', 'shipping_district', 'shipping_city'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (Schema::hasTable('user_addresses') && Schema::hasColumn('user_addresses', 'detail')) {
            Schema::table('user_addresses', function (Blueprint $table): void {
                $table->dropColumn('detail');
            });
        }
    }
};
