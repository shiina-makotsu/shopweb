<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('analytics_events')) {
            return;
        }

        Schema::table('analytics_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('analytics_events', 'surface')) {
                $table->string('surface', 32)->default('frontend')->index()->after('source');
            }

            if (! Schema::hasColumn('analytics_events', 'visitor_type')) {
                $table->string('visitor_type', 32)->default('guest')->index()->after('surface');
            }

            if (! Schema::hasColumn('analytics_events', 'device_type')) {
                $table->string('device_type', 32)->default('desktop')->index()->after('visitor_type');
            }

            if (! Schema::hasColumn('analytics_events', 'ip_region')) {
                $table->string('ip_region', 120)->nullable()->index()->after('ip_hash');
            }

            if (! Schema::hasColumn('analytics_events', 'ip_country')) {
                $table->string('ip_country', 120)->nullable()->after('ip_region');
            }
        });

        DB::table('analytics_events')
            ->whereNull('user_id')
            ->update(['visitor_type' => 'guest']);

        DB::table('analytics_events')
            ->whereNotNull('user_id')
            ->whereIn('user_id', function ($query): void {
                $query->select('id')
                    ->from('users')
                    ->whereIn('role', ['admin', 'staff', 'support', 'finance']);
            })
            ->update(['visitor_type' => 'staff']);

        DB::table('analytics_events')
            ->whereNotNull('user_id')
            ->where('visitor_type', '!=', 'staff')
            ->update(['visitor_type' => 'customer']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('analytics_events')) {
            return;
        }

        Schema::table('analytics_events', function (Blueprint $table): void {
            foreach (['ip_country', 'ip_region', 'device_type', 'visitor_type', 'surface'] as $column) {
                if (Schema::hasColumn('analytics_events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
