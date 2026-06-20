<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isEffectiveAfter = Schema::hasColumn('cost_entries', 'exchange_rate')
            ? 'exchange_rate'
            : 'amount_cents';

        Schema::table('cost_entries', function (Blueprint $table) use ($isEffectiveAfter): void {
            if (! Schema::hasColumn('cost_entries', 'application_type')) {
                $table->string('application_type')->default('one_time')->after('category')->index();
            }

            if (! Schema::hasColumn('cost_entries', 'is_effective')) {
                $table->boolean('is_effective')->default(true)->after($isEffectiveAfter)->index();
            }

            if (! Schema::hasColumn('cost_entries', 'effective_times')) {
                $table->unsignedInteger('effective_times')->default(1)->after('is_effective');
            }

            if (! Schema::hasColumn('cost_entries', 'effective_quantity')) {
                $table->unsignedInteger('effective_quantity')->default(0)->after('effective_times');
            }

            if (! Schema::hasColumn('cost_entries', 'effective_at')) {
                $table->timestamp('effective_at')->nullable()->after('effective_quantity')->index();
            }
        });

        DB::table('cost_entries')->orderBy('id')->chunkById(200, function ($entries): void {
            foreach ($entries as $entry) {
                $applicationType = $entry->procurement_id
                    ? 'procurement'
                    : ($entry->category === 'other' ? 'recurring' : 'one_time');

                DB::table('cost_entries')
                    ->where('id', $entry->id)
                    ->update([
                        'application_type' => $applicationType,
                        'is_effective' => true,
                        'effective_times' => max(1, (int) ($entry->effective_times ?? 1)),
                        'effective_at' => $entry->effective_at ?? $entry->created_at,
                    ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('cost_entries', function (Blueprint $table): void {
            foreach (['effective_at', 'effective_quantity', 'effective_times', 'is_effective', 'application_type'] as $column) {
                if (Schema::hasColumn('cost_entries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
