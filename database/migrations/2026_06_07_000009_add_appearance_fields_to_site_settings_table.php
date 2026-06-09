<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('theme_template')->default('litecart')->after('logo_path');
            $table->string('primary_color', 20)->default('#2D9CDB')->after('theme_template');
            $table->string('accent_color', 20)->default('#F5A9B8')->after('primary_color');
            $table->string('background_color', 20)->default('#FFF7FB')->after('accent_color');
            $table->string('button_radius')->default('sm')->after('background_color');
            $table->string('product_card_density')->default('comfortable')->after('button_radius');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'theme_template',
                'primary_color',
                'accent_color',
                'background_color',
                'button_radius',
                'product_card_density',
            ]);
        });
    }
};
