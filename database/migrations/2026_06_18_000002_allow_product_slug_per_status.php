<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            try {
                $table->dropUnique('products_slug_unique');
            } catch (Throwable) {
                //
            }
        });

        Schema::table('products', function (Blueprint $table): void {
            try {
                $table->unique(['status', 'slug'], 'products_status_slug_unique');
            } catch (Throwable) {
                //
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            try {
                $table->dropUnique('products_status_slug_unique');
            } catch (Throwable) {
                //
            }
        });

        Schema::table('products', function (Blueprint $table): void {
            try {
                $table->unique('slug', 'products_slug_unique');
            } catch (Throwable) {
                //
            }
        });
    }
};
