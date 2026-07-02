<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_shipping_rates', function (Blueprint $table): void {
            if (! Schema::hasColumn('warehouse_shipping_rates', 'shipping_carrier_id')) {
                $table->foreignId('shipping_carrier_id')
                    ->nullable()
                    ->after('warehouse_id')
                    ->constrained('shipping_carriers')
                    ->nullOnDelete();
            }
        });

        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'presale_shipping_warehouse_id')) {
                $table->foreignId('presale_shipping_warehouse_id')
                    ->nullable()
                    ->after('shipping_extra_fee_cents')
                    ->constrained('warehouses')
                    ->nullOnDelete();
            }
        });

        Schema::table('site_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('site_settings', 'presale_default_warehouse_id')) {
                $table->foreignId('presale_default_warehouse_id')
                    ->nullable()
                    ->after('payment_pending_timeout_minutes')
                    ->constrained('warehouses')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('site_settings', 'presale_default_warehouse_id')) {
                $table->dropConstrainedForeignId('presale_default_warehouse_id');
            }
        });

        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'presale_shipping_warehouse_id')) {
                $table->dropConstrainedForeignId('presale_shipping_warehouse_id');
            }
        });

        Schema::table('warehouse_shipping_rates', function (Blueprint $table): void {
            if (Schema::hasColumn('warehouse_shipping_rates', 'shipping_carrier_id')) {
                $table->dropConstrainedForeignId('shipping_carrier_id');
            }
        });
    }
};
