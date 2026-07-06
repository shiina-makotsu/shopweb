<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'private_shipping_requested')) {
                $table->boolean('private_shipping_requested')->default(false)->after('requires_shipping');
            }
        });

        Schema::table('categories', function (Blueprint $table): void {
            if (! Schema::hasColumn('categories', 'private_shipping_default')) {
                $table->boolean('private_shipping_default')->nullable()->after('is_active');
            }
        });

        Schema::table('product_tags', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_tags', 'private_shipping_default')) {
                $table->boolean('private_shipping_default')->nullable()->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'private_shipping_requested')) {
                $table->dropColumn('private_shipping_requested');
            }
        });

        Schema::table('categories', function (Blueprint $table): void {
            if (Schema::hasColumn('categories', 'private_shipping_default')) {
                $table->dropColumn('private_shipping_default');
            }
        });

        Schema::table('product_tags', function (Blueprint $table): void {
            if (Schema::hasColumn('product_tags', 'private_shipping_default')) {
                $table->dropColumn('private_shipping_default');
            }
        });
    }
};
