<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pages', 'editor_mode')) {
            Schema::table('pages', function (Blueprint $table): void {
                $table->string('editor_mode')->default('traditional')->after('cover_media_asset_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pages', 'editor_mode')) {
            Schema::table('pages', function (Blueprint $table): void {
                $table->dropColumn('editor_mode');
            });
        }
    }
};
