<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->boolean('home_welcome_enabled')->default(true)->after('product_card_density');
            $table->string('home_welcome_image_path')->nullable()->after('home_title');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn(['home_welcome_enabled', 'home_welcome_image_path']);
        });
    }
};
