<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        DB::table('site_settings')
            ->where('primary_color', '#1d4ed8')
            ->update(['primary_color' => '#7CBFE2']);

        DB::table('site_settings')
            ->where('accent_color', '#047857')
            ->update(['accent_color' => '#F2A8BE']);

        DB::table('site_settings')
            ->where('background_color', '#f3f4f6')
            ->update(['background_color' => '#FFF9FC']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        DB::table('site_settings')
            ->where('primary_color', '#7CBFE2')
            ->update(['primary_color' => '#1d4ed8']);

        DB::table('site_settings')
            ->where('accent_color', '#F2A8BE')
            ->update(['accent_color' => '#047857']);

        DB::table('site_settings')
            ->where('background_color', '#FFF9FC')
            ->update(['background_color' => '#f3f4f6']);
    }
};
