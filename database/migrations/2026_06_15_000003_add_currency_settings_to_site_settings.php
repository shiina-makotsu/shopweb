<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('site_settings', 'currency_base_unit')) {
                $table->string('currency_base_unit', 40)->default('yuan')->after('store_currency');
            }

            if (! Schema::hasColumn('site_settings', 'currency_base_locked')) {
                $table->boolean('currency_base_locked')->default(false)->after('currency_base_unit');
            }

            if (! Schema::hasColumn('site_settings', 'currency_exchange_rates')) {
                $table->json('currency_exchange_rates')->nullable()->after('currency_base_locked');
            }

            if (! Schema::hasColumn('site_settings', 'currency_gold_price')) {
                $table->decimal('currency_gold_price', 16, 4)->nullable()->after('currency_exchange_rates');
            }

            if (! Schema::hasColumn('site_settings', 'currency_gold_unit')) {
                $table->string('currency_gold_unit', 40)->default('gram')->after('currency_gold_price');
            }

            if (! Schema::hasColumn('site_settings', 'currency_rates_updated_at')) {
                $table->timestamp('currency_rates_updated_at')->nullable()->after('currency_gold_unit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            foreach ([
                'currency_rates_updated_at',
                'currency_gold_unit',
                'currency_gold_price',
                'currency_exchange_rates',
                'currency_base_locked',
                'currency_base_unit',
            ] as $column) {
                if (Schema::hasColumn('site_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
