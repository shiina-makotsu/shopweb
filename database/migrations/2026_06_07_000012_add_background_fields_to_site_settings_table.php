<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('favicon_path')->nullable()->after('logo_path');
            $table->string('home_background_path')->nullable()->after('background_color');
            $table->string('auth_background_path')->nullable()->after('home_background_path');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'favicon_path',
                'home_background_path',
                'auth_background_path',
            ]);
        });
    }
};
