<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_entries', function (Blueprint $table): void {
            if (! Schema::hasColumn('cost_entries', 'currency_code')) {
                $table->string('currency_code', 10)->default('CNY')->after('amount_cents')->index();
            }

            if (! Schema::hasColumn('cost_entries', 'currency_unit')) {
                $table->string('currency_unit', 40)->default('yuan')->after('currency_code');
            }

            if (! Schema::hasColumn('cost_entries', 'original_amount')) {
                $table->decimal('original_amount', 14, 4)->nullable()->after('currency_unit');
            }

            if (! Schema::hasColumn('cost_entries', 'exchange_rate')) {
                $table->decimal('exchange_rate', 14, 6)->default(1)->after('original_amount');
            }
        });

        DB::table('cost_entries')
            ->whereNull('original_amount')
            ->update([
                'currency_code' => 'CNY',
                'currency_unit' => 'yuan',
                'original_amount' => DB::raw('amount_cents / 100.0'),
                'exchange_rate' => 1,
            ]);
    }

    public function down(): void
    {
        Schema::table('cost_entries', function (Blueprint $table): void {
            foreach (['exchange_rate', 'original_amount', 'currency_unit', 'currency_code'] as $column) {
                if (Schema::hasColumn('cost_entries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
