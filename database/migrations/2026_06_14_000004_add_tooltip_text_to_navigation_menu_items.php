<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('navigation_menu_items') || Schema::hasColumn('navigation_menu_items', 'tooltip_text')) {
            return;
        }

        Schema::table('navigation_menu_items', function (Blueprint $table): void {
            $table->string('tooltip_text', 500)->nullable()->after('url');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('navigation_menu_items') || ! Schema::hasColumn('navigation_menu_items', 'tooltip_text')) {
            return;
        }

        Schema::table('navigation_menu_items', function (Blueprint $table): void {
            $table->dropColumn('tooltip_text');
        });
    }
};
